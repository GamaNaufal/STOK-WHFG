<?php

namespace App\Services;

use App\Models\{ExportFulfillmentBox, ExportReceivingBatch, ExportReceivingBatchItem, ExportStockBox, User};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExportReceivingBatchService
{
    private function fail(string $field, string $message): never { throw ValidationException::withMessages([$field => $message]); }

    public function owned(int $id, User $actor, bool $lock = false): ExportReceivingBatch
    {
        $query = ExportReceivingBatch::where('created_by', $actor->id)->whereKey($id);
        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    public function state(ExportReceivingBatch $batch): array
    {
        $batch->load('items');
        return $batch->only(['id', 'status', 'revision']) + ['items' => $batch->items->map(fn ($item) =>
            $item->only(['id', 'box_number', 'part_number', 'pcs_quantity']))->values()->all()];
    }

    public function start(User $actor): ExportReceivingBatch
    {
        return app(ExportInventoryService::class)->transaction(fn () =>
            ExportReceivingBatch::where('created_by', $actor->id)->where('status', 'draft')->orderByDesc('id')->first()
            ?? ExportReceivingBatch::create(['token' => (string) Str::uuid(), 'created_by' => $actor->id]));
    }

    private function editable(ExportReceivingBatch $batch, int $revision): void
    {
        if ($batch->status !== 'draft') $this->fail('batch', 'Daftar scan sudah diproses atau ditutup. Mulai daftar baru.');
        if ($batch->revision !== $revision) $this->fail('revision', 'Daftar scan berubah di tab lain. Data terbaru sudah dimuat; periksa lalu scan kembali.');
    }

    public function scan(int $id, array $data, User $actor): ExportReceivingBatch
    {
        return app(ExportInventoryService::class)->transaction(function () use ($id, $data, $actor) {
            $batch = $this->owned($id, $actor, true);
            $replay = ExportReceivingBatchItem::where('receive_token', $data['scan_token'])->first();
            if ($replay) {
                if ((int) $replay->export_receiving_batch_id !== $batch->id || $replay->box_number !== $data['box_number'] || $replay->part_number !== $data['part_number']) $this->fail('scan_token', 'Token scan sudah dipakai untuk data lain.');
                return $batch;
            }
            $this->editable($batch, (int) $data['revision']);
            if ($batch->items()->count() >= 1000) $this->fail('batch', 'Maksimal 1.000 box dalam satu daftar scan. Proses dahulu daftar ini.');
            if ($batch->items()->where('box_number', $data['box_number'])->exists()) $this->fail('box_number', 'Nomor box sudah ada dalam daftar scan ini.');
            if (ExportStockBox::withTrashed()->where('box_number', $data['box_number'])->exists() || ExportFulfillmentBox::where('box_number', $data['box_number'])->exists()) {
                $this->fail('box_number', 'Nomor box sudah diterima di ekspor.');
            }
            $part = app(ExportStockReceivingService::class)->part($data['part_number'], true);
            $batch->items()->create(['box_number' => $data['box_number'], 'part_setting_id' => $part->id,
                'part_number' => $part->part_number, 'pcs_quantity' => $part->qty_box,
                'position' => ((int) $batch->items()->max('position')) + 1, 'receive_token' => $data['scan_token']]);
            $batch->increment('revision');
            return $batch;
        });
    }

    public function remove(int $id, int $itemId, int $revision, User $actor): ExportReceivingBatch
    {
        return app(ExportInventoryService::class)->transaction(function () use ($id, $itemId, $revision, $actor) {
            $batch = $this->owned($id, $actor, true);
            $this->editable($batch, $revision);
            $batch->items()->whereKey($itemId)->firstOrFail()->delete();
            $batch->increment('revision');
            return $batch;
        });
    }

    public function discard(int $id, int $revision, User $actor): ExportReceivingBatch
    {
        return app(ExportInventoryService::class)->transaction(function () use ($id, $revision, $actor) {
            $batch = $this->owned($id, $actor, true);
            $this->editable($batch, $revision);
            $batch->update(['status' => 'discarded', 'revision' => $batch->revision + 1]);
            return $batch;
        });
    }

    public function process(int $id, int $revision, User $actor): ExportReceivingBatch
    {
        return app(ExportInventoryService::class)->transaction(function () use ($id, $revision, $actor) {
            $batch = $this->owned($id, $actor, true);
            if ($batch->status === 'processed') return $batch;
            $this->editable($batch, $revision);
            $items = $batch->items()->get();
            if ($items->isEmpty()) $this->fail('batch', 'Scan setidaknya satu box sebelum memproses penerimaan.');
            $boxes = app(ExportStockReceivingService::class)->receiveMany($items->map(fn ($item) =>
                $item->only(['box_number', 'part_number', 'receive_token']) + ['expected_pcs_quantity' => $item->pcs_quantity, 'scanned_at' => $item->created_at])->all(), $actor);
            foreach ($items as $index => $item) $item->update(['export_stock_box_id' => $boxes[$index]->id]);
            $batch->update(['status' => 'processed', 'processed_at' => now(), 'revision' => $batch->revision + 1]);
            AuditService::record('export_receiving_batch', 'processed', 'ExportReceivingBatch', $batch->id, $actor->id,
                [], ['box_count' => $items->count(), 'stock_box_ids' => $boxes->modelKeys()], 'Daftar scan ekspor diterima sekaligus; tujuan sudah dihitung berdasarkan FIFO.');
            return $batch;
        });
    }
}
