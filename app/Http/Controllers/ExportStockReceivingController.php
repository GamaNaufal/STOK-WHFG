<?php

namespace App\Http\Controllers;

use App\Models\ExportFulfillmentBox;
use App\Models\ExportReceivingBatch;
use App\Models\ExportReceivingBatchItem;
use App\Models\ExportStockBox;
use App\Services\ExportReceivingBatchService;
use App\Services\ExportReceivingTransactionService;
use App\Services\ExportStockReceivingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ExportStockReceivingController extends Controller
{
    private function normalize(Request $request): void
    {
        foreach (['box_number', 'part_number'] as $key) {
            if (is_string($request->input($key))) {
                $request->merge([$key => trim($request->input($key))]);
            }
        }
    }

    public function index(Request $request, ExportReceivingBatchService $service, ExportReceivingTransactionService $transactionsService)
    {
        $data = $request->validate(['batch' => 'nullable|integer|min:1', 'box' => 'nullable|integer|min:1']);
        if (! empty($data['box'])) {
            return redirect()->route('export.stock.index', ['box' => $data['box']]);
        }
        $batch = ! empty($data['batch']) ? $service->owned((int) $data['batch'], $request->user())
            : ExportReceivingBatch::where('created_by', $request->user()->id)->where('status', 'draft')->orderByDesc('id')->first();
        if ($batch?->status === 'processed') {
            return redirect()->route('export.receiving.transactions.show', $batch);
        }
        $state = $batch ? $service->state($batch) : ['id' => null, 'status' => 'draft', 'revision' => 0, 'items' => []];
        $sort = in_array($request->query('pending_sort'), ['newest', 'oldest'], true) ? $request->query('pending_sort') : 'newest';
        $transactions = $transactionsService->queue($sort)->paginate(20, ['*'], 'pending_page')
            ->withQueryString()->fragment('receiving-pending-queue');
        foreach ($transactions as $transaction) {
            $transaction->code = $transactionsService::code($transaction->kind, (int) $transaction->transaction_id, $transaction->planned_pallet_id);
            $transaction->received_at = \Illuminate\Support\Carbon::parse($transaction->received_at);
            $transaction->placed_boxes = (int) $transaction->total_boxes - (int) $transaction->pending_boxes - (int) $transaction->waiting_boxes - (int) $transaction->recommendation_boxes;
        }

        return view('export.receiving.index', compact('batch', 'state', 'transactions', 'sort'));
    }

    public function checkBox(Request $request)
    {
        $this->normalize($request);
        $data = $request->validate(['box_number' => ['required', 'string', 'regex:/^[0-9]{8}$/'], 'batch_id' => 'nullable|integer|min:1']);
        $box = ExportStockBox::where('box_number', $data['box_number'])->first();
        $inDraft = false;
        if (! empty($data['batch_id'])) {
            $batch = app(ExportReceivingBatchService::class)->owned((int) $data['batch_id'], $request->user());
            $inDraft = $batch->items()->where('box_number', $data['box_number'])->exists();
        }
        $held = \App\Models\ExportPickingIssue::open()->where('box_number', $data['box_number'])->exists();
        $exists = $box || $inDraft || $held || \App\Support\BoxIdentity::unavailable($data['box_number']) || ExportFulfillmentBox::where('box_number', $data['box_number'])->exists();

        return response()->json(['available' => ! $exists, 'box_id' => $box?->id,
            'message' => $held ? 'Box sedang ditahan oleh issue picking ekspor; selesaikan pengembalian.' : ($inDraft ? 'ID Box sudah ada dalam daftar scan.' : ($exists ? 'ID Box sudah tercatat atau disiapkan pada lokal/ekspor.' : 'ID Box siap. Scan nomor part.'))]);
    }

    public function part(Request $request, ExportStockReceivingService $service)
    {
        $this->normalize($request);
        $data = $request->validate(['part_number' => 'required|string|max:100']);
        $part = $service->part($data['part_number']);

        return response()->json(['part_number' => $part->part_number, 'pcs_quantity' => $part->qty_box]);
    }

    public function start(Request $request, ExportReceivingBatchService $service)
    {
        return response()->json($service->state($service->start($request->user())));
    }

    public function batchState(Request $request, int $batch, ExportReceivingBatchService $service)
    {
        return response()->json($service->state($service->owned($batch, $request->user())));
    }

    public function scan(Request $request, int $batch, ExportReceivingBatchService $service)
    {
        $this->normalize($request);
        $data = $request->validate(['box_number' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
            'part_number' => 'required|string|max:100', 'revision' => 'required|integer|min:1', 'scan_token' => 'required|uuid']);

        return response()->json($service->state($service->scan($batch, $data, $request->user())));
    }

    public function remove(Request $request, int $batch, int $item, ExportReceivingBatchService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1']);

        return response()->json($service->state($service->remove($batch, $item, (int) $data['revision'], $request->user())));
    }

    public function discard(Request $request, int $batch, ExportReceivingBatchService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1']);
        $service->discard($batch, (int) $data['revision'], $request->user());

        return response()->json(['id' => null, 'status' => 'draft', 'revision' => 0, 'items' => []]);
    }

    public function process(Request $request, int $batch, ExportReceivingBatchService $service)
    {
        $data = $request->validate([
            'revision' => 'required|integer|min:1',
            'input_date' => ['bail', 'nullable', 'date_format:Y-m-d', 'after:1970-01-01', 'before_or_equal:'.today()->toDateString()],
        ], [
            'input_date.date_format' => 'Pilih tanggal masuk yang valid.',
            'input_date.after' => 'Tanggal masuk harus 2 Januari 1970 atau sesudahnya.',
            'input_date.before_or_equal' => 'Tanggal masuk tidak boleh melewati hari ini.',
        ]);
        $receivedAt = isset($data['input_date']) ? Carbon::createFromFormat('!Y-m-d', $data['input_date']) : null;
        $result = $service->process($batch, (int) $data['revision'], $request->user(), $receivedAt);

        return redirect()->route('export.receiving.transactions.show', $result)
            ->with('success', 'Daftar scan diterima. Periksa pembagian lokasi dan konfirmasikan penempatan box.');
    }

    // Retained for clients already using the single-receipt API; the UI uses batches.
    public function store(Request $request, ExportStockReceivingService $service)
    {
        $this->normalize($request);
        $data = $request->validate(['box_number' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
            'part_number' => 'required|string|max:100', 'receive_token' => 'required|uuid', 'pcs_quantity' => 'prohibited']);
        $box = $service->receive($data, $request->user());

        return redirect()->route('export.stock.index', ['box' => $box->id])->with('success', 'Box diterima. Konfirmasikan penempatan fisiknya.');
    }

    private function destination(?string $destination): array
    {
        [$type, $id] = $destination ? explode(':', $destination, 2) : [null, null];

        return ['pallet_id' => $type === 'pallet' ? (int) $id : null, 'location_id' => $type === 'location' ? (int) $id : null];
    }

    public function place(Request $request, ExportStockBox $stockBox, ExportStockReceivingService $service)
    {
        try {
            $data = $request->validate(['revision' => 'required|integer|min:1', 'request_token' => 'required|uuid',
                'destination' => ['nullable', 'string', 'regex:/^(pallet|location):[1-9][0-9]*$/']]);
            $box = $service->place($stockBox->id, $data + $this->destination($data['destination'] ?? null), $request->user());
        } catch (ValidationException $exception) {
            if (! $request->expectsJson()) {
                $exception->redirectTo = route('export.stock.index', ['box' => $stockBox->id]);
            }
            throw $exception;
        }

        return redirect()->route('export.stock.index', ['box' => $box->id])->with('success', 'Penempatan box dikonfirmasi.');
    }

    public function placeBulk(Request $request, ExportStockReceivingService $service)
    {
        $request->validate(['placements_json' => 'required|string|max:1048576']);
        try {
            $rows = json_decode($request->placements_json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages(['placements' => 'Daftar penempatan tidak dapat dibaca.']);
        }
        $request->merge(['placements' => $rows]);
        $data = $request->validate(['placements' => 'required|array|list|min:1|max:1000',
            'placements.*.id' => 'required|integer|min:1|distinct', 'placements.*.revision' => 'required|integer|min:1',
            'placements.*.destination' => ['nullable', 'string', 'regex:/^(pallet|location):[1-9][0-9]*$/'],
            'placements.*.request_token' => 'required|uuid|distinct', 'return_batch' => 'nullable|integer|min:1', 'return_box' => 'nullable|integer|min:1',
            'return_notice' => 'nullable|required_with:return_pallet|integer|min:1', 'return_pallet' => 'nullable|required_with:return_notice|integer|min:1',
            'destination' => ['nullable', 'string', 'regex:/^(pallet|location):[1-9][0-9]*$/']]);
        if (count(array_filter([$data['return_batch'] ?? null, $data['return_box'] ?? null, $data['return_notice'] ?? null])) > 1) {
            throw ValidationException::withMessages(['placements' => 'Pilih satu transaksi penempatan.']);
        }
        $ids = array_column($data['placements'], 'id');
        if (! empty($data['return_batch'])) {
            $batch = ExportReceivingBatch::where('status', 'processed')->findOrFail($data['return_batch']);
            if ($batch->items()->whereIn('export_stock_box_id', $ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['placements' => 'Box harus berasal dari transaksi penerimaan ini.']);
            }
        } elseif (! empty($data['return_box'])) {
            ExportStockBox::findOrFail($data['return_box']);
            if (count($ids) !== 1 || (int) $ids[0] !== (int) $data['return_box'] || ExportReceivingBatchItem::where('export_stock_box_id', $data['return_box'])->exists()) {
                throw ValidationException::withMessages(['placements' => 'Box harus berasal dari transaksi penerimaan ini.']);
            }
        }
        if ((! empty($data['return_batch']) || ! empty($data['return_box'])) && ExportFulfillmentBox::whereIn('export_stock_box_id', $ids)
            ->whereNotNull('allocation_notice_id')->whereHas('allocationNotice', fn ($q) => $q->whereNotNull('acknowledged_at'))->exists()) {
            throw ValidationException::withMessages(['placements' => 'Box rekomendasi harus diproses melalui transaksi FIFO per pallet tujuan.']);
        }
        if (! empty($data['destination']) && collect($data['placements'])->contains(fn ($row) => ! empty($row['destination']))) {
            throw ValidationException::withMessages(['placements' => 'Gunakan tujuan umum atau pembagian tujuan per box, bukan keduanya.']);
        }
        $place = fn () => $service->placeMany(array_map(fn ($row) => ['id' => (int) $row['id'], 'revision' => (int) $row['revision'],
            'request_token' => $row['request_token']] + $this->destination($row['destination'] ?? $data['destination'] ?? null), $data['placements']), $request->user());
        if (! empty($data['return_notice'])) {
            app(\App\Services\ExportInventoryService::class)->transaction(function () use ($data, $ids, $place) {
                $notice = \App\Models\ExportStockAllocationNotice::findOrFail($data['return_notice']);
                $pallet = \App\Models\ExportPlannedPallet::findOrFail($data['return_pallet']);
                $memberCount = app(ExportReceivingTransactionService::class)->recommendationQuery($notice, $pallet)->whereIn('id', $ids)->count();
                if ($memberCount !== count($ids)) {
                    throw ValidationException::withMessages(['placements' => 'Box harus berasal dari transaksi rekomendasi dan pallet tujuan ini.']);
                }
                $place();
            });
            $route = 'export.receiving.transactions.recommendation';
            $parameters = ['notice' => $data['return_notice'], 'pallet' => $data['return_pallet']];
        } else {
            $route = ! empty($data['return_batch']) ? 'export.receiving.transactions.show' : (! empty($data['return_box']) ? 'export.receiving.transactions.legacy' : 'export.stock.index');
            $parameters = ! empty($data['return_batch']) ? ['batch' => $data['return_batch']] : (! empty($data['return_box']) ? ['stockBox' => $data['return_box']] : []);
            $place();
        }

        return redirect()->route($route, $parameters)
            ->with('success', 'Penempatan '.count($rows).' box dikonfirmasi.');
    }
}
