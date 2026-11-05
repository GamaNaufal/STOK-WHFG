<?php

namespace App\Services;

use App\Models\Box;
use App\Models\ExpiredBoxHandling;
use App\Models\ExpiredBoxTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExpiredBoxBulkService
{
    public const LIMIT = 50;

    public function __construct(private readonly ExpiredBoxHandlingService $workflow) {}

    /** Every selected row commits together; nested workflow transactions use savepoints. */
    public function execute(array $input, int $userId, string $scope = 'domestic'): array
    {
        if (($input['action'] ?? '') === 'receive' && array_key_exists('new_box_numbers', $input)) {
            return app(ExpiredBoxPackingReceiptService::class)->receive($input, $userId, $scope);
        }
        $export = $scope === 'export';
        if ($export && ($input['action'] ?? '') === 'receive') {
            throw ValidationException::withMessages(['new_box_numbers' => 'Gunakan daftar scan ID Box baru untuk penerimaan packing ekspor.']);
        }
        $handlingModel = $export ? \App\Models\ExportExpiredBoxHandling::class : ExpiredBoxHandling::class;
        $transactionModel = $export ? \App\Models\ExportExpiredBoxTransaction::class : ExpiredBoxTransaction::class;
        $stockModel = $export ? \App\Models\ExportStockBox::class : Box::class;
        $workflow = $export ? app(ExportExpiredBoxHandlingService::class) : $this->workflow;
        $rules = [
            'action' => ['required', 'in:start,qa,packing,receive'],
            'rows' => ['required', 'array', 'min:1', 'max:'.self::LIMIT],
            'rows.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'confirmed' => ['accepted'],
            'transaction_id' => ['nullable', 'integer', 'min:1'],
        ];
        if (($input['action'] ?? '') === 'qa') {
            $rules += [
                'rows.*.decision' => ['required', 'in:passed,rejected'],
                'rows.*.notes' => ['nullable', 'string', 'max:2000', 'required_if:rows.*.decision,rejected'],
            ];
        }
        if (($input['action'] ?? '') === 'receive') {
            $rules += [
                'rows.*.box_number' => ['required', 'string', 'regex:/^\d{8}$/', 'distinct'],
                'rows.*.part_number' => ['required', 'string', 'max:255'],
                'rows.*.pcs_quantity' => ['required', 'integer', 'min:1'],
                'rows.*.label_issued_at' => ['required', 'date', 'before_or_equal:now'],
                'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
                'location_id' => ['nullable', 'required_without:pallet_id', 'integer', 'exists:master_locations,id,scope,domestic'],
            ];
        }
        $data = Validator::make($input, $rules)->validate();
        // Expiry synchronization is independent housekeeping, not a batch transition.
        if ($data['action'] === 'start') {
            app($export ? ExportExpiredBoxService::class : ExpiredBoxService::class)->syncStatuses();
        }

        $run = $export ? fn ($work) => app(ExportInventoryService::class)->transaction($work) : fn ($work) => DB::transaction($work);

        return $run(function () use ($data, $userId, $export, $handlingModel, $transactionModel, $stockModel, $workflow) {
            $rows = collect($data['rows'])->sortBy('id');
            $ids = $rows->pluck('id')->all();
            if ($data['action'] === 'start') {
                // Serialize overlapping selections before checking existing membership.
                $sourceBoxes = $stockModel::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            }
            $before = $data['action'] === 'start'
                ? $handlingModel::whereIn('source_box_id', $ids)->get()->keyBy('source_box_id')
                : $handlingModel::whereIn('id', $ids)->orderBy('id')->get()->keyBy('id');
            if ($data['action'] !== 'start' && $before->count() !== count($ids)) {
                throw ValidationException::withMessages(['rows' => 'Ada penanganan yang tidak ditemukan. Pilih ulang dari daftar.']);
            }
            if ($data['action'] === 'start') {
                if ($before->isNotEmpty()) {
                    $groups = $before->pluck('transaction_id')->unique();
                    if ($before->count() === count($ids) && $groups->count() === 1
                        && $handlingModel::where('transaction_id', $groups->first())->count() === count($ids)) {
                        return $before->pluck('id')->all();
                    }
                    throw ValidationException::withMessages(['rows' => 'Sebagian box sudah menjadi anggota transaksi lain. Buka transaksi tersebut atau pilih box yang belum ditangani.']);
                }
                if ($sourceBoxes->count() !== count($ids)
                    || $sourceBoxes->map(fn ($box) => ExpiredBoxHandling::groupKey($box->part_number, (int) $box->pcs_quantity))->unique()->count() !== 1) {
                    throw ValidationException::withMessages(['rows' => 'Satu transaksi hanya boleh berisi satu nomor part dan jumlah PCS per box yang sama. Pilih satu kelompok.']);
                }
                $transaction = $transactionModel::create(['created_by' => $userId]);
            } else {
                $groups = $before->pluck('transaction_id')->unique();
                if ($groups->count() !== 1 || ! $groups->first()
                    || (! empty($data['transaction_id']) && (int) $data['transaction_id'] !== (int) $groups->first())) {
                    throw ValidationException::withMessages(['rows' => 'Box harus berasal dari satu transaksi yang sama.']);
                }
                $transaction = $transactionModel::whereKey($groups->first())->lockForUpdate()->firstOrFail();
                $members = $transaction->handlings()->lockForUpdate()->get();
                $expected = $data['action'] === 'qa' ? $members : $members->where('status', '!=', 'rejected');
                if ($expected->pluck('id')->sort()->values()->all() !== $rows->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all()) {
                    throw ValidationException::withMessages(['rows' => 'Proses seluruh box pada tahap transaksi ini. Buka kembali transaksi untuk melihat daftar lengkap.']);
                }
                $before = $members->whereIn('id', $ids)->keyBy('id');
            }
            $target = [];
            if ($data['action'] === 'receive') {
                // A retry after successful receipt must not allocate another pallet.
                if ($before->every(fn ($handling) => $handling->status === 'completed')) {
                    foreach ($rows as $index => $row) {
                        $handling = $before[$row['id']];
                        if ($handling->replacement_box_number !== $row['box_number']) {
                            throw ValidationException::withMessages(["rows.$index.box_number" => "Box {$handling->source_box_number} sudah diterima dengan ID lain."]);
                        }
                    }

                    return $before->keys()->all();
                }
                foreach ($rows as $index => $row) {
                    if ($before[$row['id']]->status !== 'at_packing') {
                        throw ValidationException::withMessages(["rows.$index.id" => "Box {$before[$row['id']]->source_box_number} tidak lagi di packing. Pilih ulang daftar penerimaan."]);
                    }
                }
                if (! empty($data['pallet_id'])) {
                    $target = ['pallet_id' => $data['pallet_id']];
                } else {
                    $pallet = app(PalletNumberService::class)->create();
                    app(LocationAssignmentService::class)->assign((int) $data['location_id'], $pallet);
                    $target = ['pallet_id' => $pallet->id];
                }
            }
            $result = [];
            $changed = false;
            foreach ($rows as $index => $row) {
                $label = $data['action'] === 'start'
                    ? ($stockModel::withTrashed()->whereKey($row['id'])->value('box_number') ?? 'ID #'.$row['id'])
                    : $before[$row['id']]->source_box_number;
                try {
                    if ($data['action'] === 'start') {
                        $handling = $workflow->start((int) $row['id'], $userId, false, $transaction->id);
                    } else {
                        match ($data['action']) {
                            'qa' => $workflow->decideQa((int) $row['id'], $row['decision'], $row['notes'] ?? null, $userId),
                            'packing' => $workflow->sendToPacking((int) $row['id'], $userId),
                            'receive' => $workflow->receive((int) $row['id'], array_merge($row, $target), $userId, false),
                        };
                        $handling = $handlingModel::findOrFail($row['id']);
                    }
                } catch (ValidationException $e) {
                    $errors = [];
                    foreach ($e->errors() as $field => $messages) {
                        $errors["rows.$index.$field"] = array_map(fn ($message) => "Box $label: $message", $messages);
                    }
                    throw ValidationException::withMessages($errors);
                } catch (\Illuminate\Database\QueryException $e) {
                    throw $e;
                } catch (\RuntimeException $e) {
                    throw ValidationException::withMessages(["rows.$index.id" => "Box $label: {$e->getMessage()} Seluruh pilihan belum disimpan."]);
                }
                $changed = $changed || ! isset($before[$row['id']]) || $before[$row['id']]->status !== $handling->status;
                $result[] = $handling->id;
            }
            if ($data['action'] === 'receive') {
                // Defer synchronization until every row exists, including an expired first row.
                $replacementIds = ExpiredBoxHandling::whereIn('id', $result)->pluck('replacement_box_id');
                foreach (Box::whereIn('id', $replacementIds)->get()->unique('part_number') as $box) {
                    app(ExpiredBoxService::class)->syncLinkedPalletsAfterBoxDeactivation($box);
                }
            }
            if ($changed) {
                AuditService::record($export ? 'export_expired_box' : 'expired_box', 'bulk_'.$data['action'], class_basename($handlingModel), $result[0], $userId, [],
                    ['transaction_id' => $transaction->id, 'handling_ids' => $result, 'count' => count($result)],
                    'Penanganan massal '.$data['action'].': '.count($result).' box, seluruhnya tersimpan bersama.');
            }

            return $result;
        });
    }
}
