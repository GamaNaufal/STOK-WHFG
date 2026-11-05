<?php

namespace App\Http\Controllers;

use App\Models\ExpiredBoxHandling;
use App\Models\ExpiredBoxTransaction;
use App\Models\ExportExpiredBoxHandling;
use App\Models\ExportExpiredBoxTransaction;
use App\Models\ExportMasterLocation;
use App\Models\MasterLocation;
use App\Models\User;
use App\Services\ExpiredBoxBulkService;
use App\Services\ExpiredBoxPackingReceiptService;
use App\Services\ExpiredBoxService;
use App\Services\ExportExpiredBoxService;
use App\Services\ExportStockViewService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpiredBoxWorkspaceController extends Controller
{
    private function export(): bool
    {
        return request()->routeIs('export.expired-box.*');
    }

    private function prefix(): string
    {
        return $this->export() ? 'export.expired-box.' : 'expired-box.';
    }

    private function scope(): string
    {
        return $this->export() ? 'export' : 'domestic';
    }

    private function handlingModel(): string
    {
        return $this->export() ? ExportExpiredBoxHandling::class : ExpiredBoxHandling::class;
    }

    private function transactionModel(): string
    {
        return $this->export() ? ExportExpiredBoxTransaction::class : ExpiredBoxTransaction::class;
    }

    private function inventory()
    {
        $service = app($this->export() ? ExportExpiredBoxService::class : ExpiredBoxService::class);
        $service->syncStatuses();
        $table = $this->export() ? 'b' : 'boxes';
        $query = $service->getExpirableBoxesQuery()->whereIn($table.'.expired_status', ['warning', 'expired']);
        if (! $this->export()) {
            $query->addSelect(['boxes.is_not_full', 'boxes.assigned_delivery_order_id as delivery_id']);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'tab' => ['nullable', Rule::in(['expired', 'process', 'history', 'warning'])],
            'search' => ['nullable', 'string', 'max:100'],
            'location_id' => ['nullable', 'integer', Rule::exists('master_locations', 'id')->where('scope', $this->scope())],
        ]);
        $tab = $data['tab'] ?? 'expired';
        $search = trim($data['search'] ?? '');
        $table = $this->export() ? 'b' : 'boxes';
        $locationTable = $this->export() ? 'p' : 'stock_locations';
        $query = $this->inventory();
        $counts = (clone $query)->select($table.'.expired_status')->selectRaw('COUNT(*) as aggregate')->groupBy($table.'.expired_status')->pluck('aggregate', 'expired_status');
        $warningCount = (int) ($counts['warning'] ?? 0);
        $expiredCount = (int) ($counts['expired'] ?? 0);
        $boxes = $query->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
            ->where($table.'.box_number', 'like', '%'.$search.'%')->orWhere($table.'.part_number', 'like', '%'.$search.'%')
            ->orWhere($this->export() ? 'p.location_code' : 'stock_locations.warehouse_location', 'like', '%'.$search.'%')
            ->orWhere($this->export() ? 'd.plan_code' : 'pallets.pallet_number', 'like', '%'.$search.'%')))
            ->when(! empty($data['location_id']), fn ($q) => $q->where($locationTable.'.master_location_id', $data['location_id']))
            ->orderBy('stored_at')->get();
        $expiredBoxes = $boxes->where('expired_status', 'expired');
        $warningBoxes = $boxes->where('expired_status', 'warning');
        $inventoryGroups = ($tab === 'warning' ? $warningBoxes : $expiredBoxes)
            ->groupBy(fn ($box) => ExpiredBoxHandling::groupKey($box->part_number, (int) $box->pcs_quantity));
        $transactionModel = $this->transactionModel();
        $open = fn ($query) => $query->whereNotIn('status', ['completed', 'rejected']);
        $processCount = $transactionModel::whereHas('handlings', $open)->count();
        $historyCount = $transactionModel::whereDoesntHave('handlings', $open)->count();
        $transactions = $transactionModel::with('handlings')->when($tab === 'process', fn ($q) => $q->whereHas('handlings', $open))
            ->when($tab === 'history', fn ($q) => $q->whereDoesntHave('handlings', $open))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    if (preg_match('/^EXP-\d{8}-(\d+)$/i', $search, $matches)) {
                        $query->where('id', (int) $matches[1]);
                    }
                    $query->orWhereHas('handlings', fn ($q) => $q->where('source_box_number', 'like', '%'.$search.'%')
                        ->orWhere('replacement_box_number', 'like', '%'.$search.'%')->orWhere('part_number', 'like', '%'.$search.'%'));
                });
            })->latest('id')->paginate(15, ['*'], 'transaction_page')->withQueryString();
        $locations = ($this->export() ? ExportMasterLocation::query() : MasterLocation::query())->orderBy('code')->get(['id', 'code']);
        $handledHistory = $this->export() ? collect() : \App\Models\ExpiredBoxReport::with('handler')
            ->where('status', 'handled')->latest('handled_at')->paginate(15)->withQueryString();
        $scopeUrls = [];
        foreach (['domestic' => 'expired-box', 'export' => 'export.expired-box'] as $scope => $prefix) {
            if ($request->user()->canAccess($scope === 'export' ? 'export_expired_box' : 'expired_box')) {
                $scopeUrls[$scope] = route($prefix.'.index', ['tab' => $tab, 'search' => $search]);
            }
        }
        $vars = compact('tab', 'search', 'scopeUrls', 'locations', 'inventoryGroups', 'expiredBoxes', 'warningBoxes', 'transactions',
            'expiredCount', 'warningCount', 'processCount', 'historyCount', 'handledHistory');
        if ($request->expectsJson()) {
            return response()->json(['html' => view('warehouse.expired-box.workspace', $vars)->render(), 'url' => $request->fullUrl()]);
        }

        return view('warehouse.expired-box.index', $vars);
    }

    public function create(Request $request)
    {
        $expiredBoxes = $this->inventory()->where('b.expired_status', 'expired')->orderBy('b.first_received_at')->get();

        return view('warehouse.expired-box.create', compact('expiredBoxes'));
    }

    public function review(Request $request)
    {
        $data = $request->validate(['action' => ['required', 'in:start'], 'ids' => ['required', 'array', 'min:1', 'max:50'], 'ids.*' => ['required', 'integer', 'distinct']]);
        $action = 'start';
        $records = $this->inventory()->where('b.expired_status', 'expired')->whereIn('b.id', $data['ids'])->orderBy('b.id')->get();
        if ($records->count() !== count($data['ids']) || $records->map(fn ($box) => ExpiredBoxHandling::groupKey($box->part_number, $box->pcs_quantity))->unique()->count() !== 1) {
            return redirect()->route($this->prefix().'transactions.create')->with('error', 'Pilih box expired dalam satu kelompok part dan PCS.');
        }

        return view('warehouse.expired-box.bulk-review', compact('action', 'records'));
    }

    public function store(Request $request, ExpiredBoxBulkService $service)
    {
        try {
            $ids = $service->execute($request->all(), $request->user()->id, $this->scope());
            $model = $this->handlingModel();

            return redirect()->route($this->prefix().'transactions.show', $model::findOrFail($ids[0])->transaction_id)->with('success', 'Transaksi penanganan tersimpan.');
        } catch (\Illuminate\Database\QueryException $error) {
            if (! in_array((string) $error->getCode(), ['23000', '23505', '40001'])) {
                throw $error;
            }

            return back()->withInput()->with('error', 'ID atau lokasi sedang dipakai. Periksa kembali; penerimaan belum disimpan.');
        } catch (\RuntimeException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }
    }

    public function show(int $transaction)
    {
        $model = $this->transactionModel();
        $transaction = $model::with(['handlings', 'receipts.receiver', 'receipts.handlings'])->findOrFail($transaction);
        $action = $transaction->next_action;
        $records = $transaction->handlings->where('status', $transaction->stage)->values();
        $choices = app(ExportStockViewService::class)->choices();
        $availableLocations = $choices['locations'];
        $pallets = $choices['pallets'];

        return view('warehouse.expired-box.transaction', compact('transaction', 'action', 'records', 'availableLocations', 'pallets'));
    }

    public function update(Request $request, int $transaction, ExpiredBoxBulkService $service)
    {
        $model = $this->transactionModel();
        $model::findOrFail($transaction);
        $request->validate(['action' => ['required', 'in:qa,packing,receive']]);
        // Route identity and scope are authoritative, never payload fields.
        $request->merge(['transaction_id' => $transaction]);

        return $this->store($request, $service);
    }

    public function scan(Request $request, int $transaction, ExpiredBoxPackingReceiptService $service)
    {
        $model = $this->transactionModel();
        $transaction = $model::with('handlings')->findOrFail($transaction);
        $data = $request->validate(['box_number' => ['required', 'string', 'regex:/^[0-9]{8}$/D'], 'group_key' => ['required', 'string', 'size:64']]);
        abort_unless($transaction->stage === 'at_packing' && $transaction->handlings->where('status', 'at_packing')->contains('group_key', $data['group_key']), 409, 'Kelompok tidak lagi menunggu hasil packing. Muat ulang transaksi.');
        $service->assertNewNumber($data['box_number']);

        return response()->json(['outcome' => 'ok', 'box_number' => $data['box_number']]);
    }

    public function handling(int $handling)
    {
        $model = $this->handlingModel();
        $handling = $model::with('transaction')->findOrFail($handling);
        $users = User::whereIn('id', collect(['sent_qa_by', 'qa_decided_by', 'sent_packing_by', 'received_by'])->map(fn ($key) => $handling->$key)->filter())->pluck('name', 'id');
        $originHandling = $model::where('replacement_box_id', $handling->source_box_id)->first();
        $nextHandling = $handling->replacement_box_id ? $model::where('source_box_id', $handling->replacement_box_id)->first() : null;

        return view('warehouse.expired-box.show', compact('handling', 'users', 'originHandling', 'nextHandling'));
    }

    public function trace(int $handling)
    {
        $model = $this->handlingModel();

        return response()->json($model::findOrFail($handling)->toArray());
    }
}
