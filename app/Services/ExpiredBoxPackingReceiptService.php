<?php

namespace App\Services;

use App\Models\Box;
use App\Models\ExpiredBoxHandling;
use App\Models\ExpiredBoxReceipt;
use App\Models\ExpiredBoxTransaction;
use App\Support\BoxIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExpiredBoxPackingReceiptService
{
    public function receive(array $input, int $userId, string $scope = 'domestic'): array
    {
        $export = $scope === 'export';
        $transactionModel = $export ? \App\Models\ExportExpiredBoxTransaction::class : ExpiredBoxTransaction::class;
        $receiptModel = $export ? \App\Models\ExportExpiredBoxReceipt::class : ExpiredBoxReceipt::class;
        $handlingModel = $export ? \App\Models\ExportExpiredBoxHandling::class : ExpiredBoxHandling::class;
        $workflow = app($export ? ExportExpiredBoxHandlingService::class : ExpiredBoxHandlingService::class);
        $data = Validator::make($input, [
            'transaction_id' => ['required', 'integer', 'min:1'],
            'receipt_token' => ['required', 'uuid'],
            'new_box_numbers' => ['required', 'array', 'min:1', 'max:'.ExpiredBoxBulkService::LIMIT],
            'new_box_numbers.*' => ['required', 'string', 'regex:/^[0-9]{8}$/D', 'distinct'],
            'group_key' => ['nullable', 'string', 'size:64'],
            'pallet_id' => ['nullable', 'integer', 'min:1'],
            'location_id' => ['nullable', 'required_without:pallet_id', 'integer', 'min:1'],
            'confirmed' => ['accepted'],
        ])->validate();
        $numbers = array_values($data['new_box_numbers']);
        $hash = hash('sha256', json_encode([
            (int) $data['transaction_id'], $numbers, $data['group_key'] ?? '',
            ! empty($data['pallet_id']) ? (int) $data['pallet_id'] : null,
            empty($data['pallet_id']) ? (int) $data['location_id'] : null,
        ], JSON_THROW_ON_ERROR));

        $run = $export ? fn ($work) => app(ExportInventoryService::class)->transaction($work) : fn ($work) => DB::transaction($work);

        return $run(function () use ($data, $numbers, $hash, $userId, $export, $transactionModel, $receiptModel, $handlingModel, $workflow) {
            $transaction = $transactionModel::whereKey($data['transaction_id'])->lockForUpdate()->firstOrFail();
            $existing = $receiptModel::where('request_token', $data['receipt_token'])->first();
            if ($existing) {
                if ((int) $existing->transaction_id !== $transaction->id || (int) $existing->received_by !== $userId
                    || ! hash_equals($existing->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['receipt_token' => 'Token penerimaan sudah digunakan untuk isian lain. Muat ulang transaksi.']);
                }

                return $existing->handlings()->pluck('id')->all();
            }
            $members = $transaction->handlings()->orderBy('id')->lockForUpdate()->get();
            if ($members->contains(fn ($item) => in_array($item->status, ['at_qa', 'awaiting_packing']))) {
                throw ValidationException::withMessages(['new_box_numbers' => 'Selesaikan hasil QA dan penyerahan ke packing terlebih dahulu.']);
            }
            $pending = $members->where('status', 'at_packing');
            $groups = $pending->groupBy('group_key');
            $key = $data['group_key'] ?? ($groups->count() === 1 ? $groups->keys()->first() : null);
            if (! $key || ! $groups->has($key)) {
                throw ValidationException::withMessages(['group_key' => 'Pilih kelompok part dan PCS yang masih menunggu packing.']);
            }
            $sources = $groups[$key]->sort(function ($a, $b) {
                return [$a->original_stored_at->getTimestamp(), $a->source_box_id, $a->id]
                    <=> [$b->original_stored_at->getTimestamp(), $b->source_box_id, $b->id];
            })->values();
            if (count($numbers) > $sources->count()) {
                throw ValidationException::withMessages(['new_box_numbers' => "Sisa kelompok ini hanya {$sources->count()} box. Periksa penerimaan oleh operator lain."]);
            }
            foreach ($numbers as $index => $number) {
                $this->assertNewNumber($number, "new_box_numbers.$index");
            }

            // One physical destination per receipt; later receipts may choose another destination.
            if ($export) {
                $target = ! empty($data['pallet_id']) ? ['pallet_id' => $data['pallet_id']] : ['location_id' => $data['location_id']];
                if (empty($data['pallet_id'])) {
                    Validator::make($data, ['location_id' => ['required', 'exists:master_locations,id,scope,export']])->validate();
                }
            } elseif (! empty($data['pallet_id'])) {
                $target = ['pallet_id' => $data['pallet_id']];
            } else {
                Validator::make($data, ['location_id' => ['required', 'exists:master_locations,id,scope,domestic']])->validate();
                $pallet = app(PalletNumberService::class)->create();
                app(LocationAssignmentService::class)->assign((int) $data['location_id'], $pallet);
                $target = ['pallet_id' => $pallet->id];
            }
            $receivedAt = now();
            $receipt = $receiptModel::create([
                'transaction_id' => $transaction->id, 'request_token' => $data['receipt_token'],
                'payload_hash' => $hash, 'received_by' => $userId, 'received_at' => $receivedAt,
            ]);
            $ids = [];
            foreach ($numbers as $index => $number) {
                $source = $sources[$index];
                $workflow->receive($source->id, array_merge($target, [
                    'box_number' => $number, 'part_number' => $source->part_number,
                    'pcs_quantity' => $source->pcs_quantity, 'received_at' => $receivedAt,
                    'receipt_id' => $receipt->id, 'replacement_mapping' => 'automatic_fifo',
                ]), $userId, false);
                $ids[] = $source->id;
                if ($export && isset($target['location_id'])) {
                    $received = \App\Models\ExportStockBox::findOrFail($handlingModel::findOrFail($source->id)->replacement_box_id);
                    $target = ['pallet_id' => $received->current_pallet_id];
                }
            }
            $first = $handlingModel::findOrFail($ids[0]);
            $receipt->update(['destination_pallet' => $first->destination_pallet, 'destination_location' => $first->destination_location]);
            if (! $export) {
                $replacement = Box::findOrFail($first->replacement_box_id);
                app(ExpiredBoxService::class)->syncLinkedPalletsAfterBoxDeactivation($replacement);
            }
            AuditService::record($export ? 'export_expired_box' : 'expired_box', 'bulk_receive', class_basename($handlingModel), $first->id, $userId, [], [
                'transaction_id' => $transaction->id, 'receipt_id' => $receipt->id, 'handling_ids' => $ids,
                'count' => count($ids), 'replacement_mapping' => 'automatic_fifo',
                'remaining_at_packing' => $pending->count() - count($ids),
            ], count($ids).' box hasil packing diterima. Acuan asal dipasangkan otomatis menurut FIFO, bukan konfirmasi pasangan label fisik.');

            return $ids;
        });
    }

    public function assertNewNumber(string $number, string $field = 'box_number'): void
    {
        if (! preg_match('/^[0-9]{8}$/D', $number)) {
            throw ValidationException::withMessages([$field => 'ID Box harus tepat 8 angka.']);
        }
        if (BoxIdentity::unavailable($number)
            || DB::table('not_full_box_requests')->where('box_number', $number)->exists()) {
            throw ValidationException::withMessages([$field => 'ID Box sudah pernah digunakan atau sedang diajukan. Scan ID baru dari packing.']);
        }
    }
}
