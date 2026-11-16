<?php

namespace App\Http\Controllers;

use App\Models\{ExportDeliveryCustomerLink, ExportDeliveryPlan, ExportFulfillmentBox, ExportStockBox};
use App\Services\{AuditService, ExportCustomerReservationService, ExportCustomerService, ExportCustomerStockExceptionService, ExportCustomerStockPolicy, ExportInventoryService};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExportCustomerStockController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(app(ExportCustomerStockPolicy::class)->ready(), 503);
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'owner' => ['nullable', 'regex:/^(general|[1-9][0-9]*)$/D'], 'process' => 'nullable|in:free,locked']);
        $q = trim($filters['q'] ?? ''); $owner = $filters['owner'] ?? ''; $process = $filters['process'] ?? '';
        $query = ExportStockBox::with(['customerReservation.customer', 'currentPallet', 'fulfillment.plannedItem.pallet.palletPlan.deliveryPlan'])
            ->whereIn('state', ['received', 'stored'])
            ->when($q !== '', fn ($r) => $r->where(fn ($s) => $s->where('box_number', 'like', '%'.$q.'%')->orWhere('part_number', 'like', '%'.$q.'%')->orWhereHas('currentPallet', fn ($p) => $p->where('number', 'like', '%'.$q.'%'))))
            ->when($owner === 'general', fn ($r) => $r->whereDoesntHave('customerReservation'))
            ->when(ctype_digit($owner), fn ($r) => $r->whereHas('customerReservation', fn ($s) => $s->where('export_customer_id', $owner)));
        if ($process !== '') {
            $free = app(\App\Services\ExportStockQueryService::class)->freeBoxes(ExportStockBox::query())
                ->whereNotIn('export_stock_boxes.id', \App\Models\ExportNotFullRequest::where('status', 'prepared')->whereNotNull('preparation->source_box_id')->select('preparation->source_box_id'))
                ->whereDoesntHave('currentPallet.boxes.fulfillment', fn ($q) => $q->whereNotNull('picking_started_at')->whereNull('placed_at'));
            $query->{$process === 'free' ? 'whereIn' : 'whereNotIn'}('export_stock_boxes.id', $free->select('export_stock_boxes.id'));
        }
        $boxes = $query->orderBy('first_received_at')->orderBy('id')->paginate(20)->withQueryString();
        // Reuse the mutation guard for the selection state; the POST always checks again under lock.
        foreach ($boxes as $box) {
            try { app(ExportCustomerReservationService::class)->assertEditable($box); $box->customer_editable = true; }
            catch (ValidationException) { $box->customer_editable = false; }
        }
        return view('export.stock.customer-reservations', compact('boxes', 'q', 'owner', 'process') + ['masterCustomers' => app(ExportCustomerService::class)->options(),
            'ownerCustomers' => \App\Models\ExportCustomer::orderBy('name')->get()]);
    }

    public function change(Request $request, ExportCustomerReservationService $service)
    {
        $data = $request->validate(['request_token' => 'required|uuid', 'boxes' => 'required|array|min:1|max:100', 'boxes.*' => 'required|integer|min:1',
            'reservation_action' => 'required|in:assign,release', 'customer_id' => 'nullable|integer|min:1', 'reason' => 'required|string|max:1000']);
        if ($data['reservation_action'] === 'assign' && empty($data['customer_id'])) throw ValidationException::withMessages(['customer_id' => 'Pilih customer tujuan.']);
        if ($data['reservation_action'] === 'release') $data['customer_id'] = null;
        $result = $service->change($data, $request->user());
        return redirect()->route('export.stock.index', ['customer_view' => 'reservations'])->with('success', $result['count'].' box diperbarui, lokasi, PCS dan tanggal FIFO tetap.');
    }

    public function exception(Request $request, ExportDeliveryPlan $deliveryPlan, ExportCustomerStockExceptionService $service)
    {
        abort_unless(app(ExportCustomerStockPolicy::class)->ready(), 503);
        $data = $request->validate(['item_id' => 'required|integer|min:1', 'source_customer_id' => 'nullable|integer|min:1']);
        return view('export.fulfillment.customer-exception', $service->preview($deliveryPlan->id, (int) $data['item_id'], isset($data['source_customer_id']) ? (int) $data['source_customer_id'] : null));
    }

    public function createException(Request $request, ExportDeliveryPlan $deliveryPlan, ExportCustomerStockExceptionService $service)
    {
        $data = $request->validate(['request_token' => 'required|uuid', 'item_id' => 'required|integer|min:1', 'source_customer_id' => 'required|integer|min:1',
            'delivery_revision' => 'required|integer|min:1', 'plan_revision' => 'required|integer|min:1', 'pallet_revision' => 'required|integer|min:1',
            'boxes' => 'required|array|min:1|max:100', 'boxes.*' => 'required|integer|min:1', 'reason' => 'required|string|max:1000']);
        $service->create($deliveryPlan->id, $data, $request->user());
        return redirect()->route('export.pallet-plans.show', $deliveryPlan)->with('success', 'Pengecualian tersimpan. Tinjau rekomendasi FIFO dan klik Oke sebelum picking. Pemilik customer tetap.');
    }

    public function cancelException(Request $request, ExportDeliveryPlan $deliveryPlan, int $exception, ExportCustomerStockExceptionService $service)
    {
        $service->cancel($deliveryPlan->id, $exception, $request->validate(['request_token' => 'required|uuid', 'revision' => 'required|integer|min:1']), $request->user());
        return redirect()->route('export.fulfillment.show', $deliveryPlan)->with('success', 'Pengecualian dibatalkan, box tetap milik customer asal.');
    }

    public function link(Request $request, ExportDeliveryPlan $deliveryPlan, ExportCustomerReservationService $service)
    {
        abort_unless($request->user()->canAccess('export_stock_customer_manage'), 403);
        $data = $request->validate(['request_token' => 'required|uuid', 'customer_id' => 'required|integer|min:1', 'revision' => 'required|integer|min:1', 'reason' => 'required|string|max:1000']);
        $service->request($data['request_token'], 'delivery_customer_link', ['delivery_id' => $deliveryPlan->id] + $data, $request->user(), function () use ($data, $request, $deliveryPlan) {
            $delivery = ExportDeliveryPlan::lockForUpdate()->findOrFail($deliveryPlan->id);
            if ($delivery->revision !== (int) $data['revision'] || $delivery->customerLink()->exists()) throw ValidationException::withMessages(['customer_id' => 'Customer/revisi delivery sudah berubah atau sudah dipetakan.']);
            $customer = app(ExportCustomerService::class)->active((int) $data['customer_id']);
            $boxes = ExportFulfillmentBox::whereHas('plannedItem.pallet.palletPlan', fn ($q) => $q->where('export_delivery_plan_id', $delivery->id))->with('stockBox')->get();
            foreach ($boxes as $f) {
                $owner = $f->stockBox ? app(ExportCustomerStockPolicy::class)->ownerId($f->stockBox) : null;
                if ($owner && $owner !== $customer->id) throw ValidationException::withMessages(['customer_id' => 'Komitmen existing bertentangan dengan customer pilihan.']);
            }
            ExportDeliveryCustomerLink::create(['export_delivery_plan_id' => $delivery->id, 'export_customer_id' => $customer->id, 'source_name' => $delivery->customer_name]);
            AuditService::record('export_customer', 'delivery_linked', 'ExportDeliveryPlan', $delivery->id, $request->user()->id, [], ['customer_id' => $customer->id, 'customer_name' => $customer->name], $data['reason']);
            return ['delivery_id' => $delivery->id];
        });
        return back()->with('success', 'Customer delivery dipetakan, nama historis dan komitmen existing tetap.');
    }
}
