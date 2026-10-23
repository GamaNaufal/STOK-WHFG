<?php

namespace App\Services;

use App\Models\Box;
use App\Models\ExpiredBoxHandling;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExpiredBoxBulkService
{
    public const LIMIT = 50;

    public function __construct(private readonly ExpiredBoxHandlingService $workflow) {}

    /** Every selected row commits together; nested workflow transactions use savepoints. */
    public function execute(array $input, int $userId): array
    {
        $rules = [
            'action' => ['required', 'in:start,qa,packing,receive'],
            'rows' => ['required', 'array', 'min:1', 'max:'.self::LIMIT],
            'rows.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'confirmed' => ['accepted'],
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
                'location_id' => ['nullable', 'required_without:pallet_id', 'integer', 'exists:master_locations,id'],
            ];
        }
        $data = Validator::make($input, $rules)->validate();
        // Expiry synchronization is independent housekeeping, not a batch transition.
        if ($data['action'] === 'start') {
            app(ExpiredBoxService::class)->syncStatuses();
        }

        return DB::transaction(function () use ($data, $userId) {
            $rows = collect($data['rows'])->sortBy('id');
            $ids = $rows->pluck('id')->all();
            $before = $data['action'] === 'start'
                ? ExpiredBoxHandling::whereIn('source_box_id', $ids)->get()->keyBy('source_box_id')
                : ExpiredBoxHandling::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($data['action'] !== 'start' && $before->count() !== count($ids)) {
                throw ValidationException::withMessages(['rows' => 'Ada penanganan yang tidak ditemukan. Pilih ulang dari daftar.']);
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
                    ? (Box::withTrashed()->whereKey($row['id'])->value('box_number') ?? 'ID #'.$row['id'])
                    : $before[$row['id']]->source_box_number;
                try {
                    if ($data['action'] === 'start') {
                        $handling = $this->workflow->start((int) $row['id'], $userId, false);
                    } else {
                        match ($data['action']) {
                            'qa' => $this->workflow->decideQa((int) $row['id'], $row['decision'], $row['notes'] ?? null, $userId),
                            'packing' => $this->workflow->sendToPacking((int) $row['id'], $userId),
                            'receive' => $this->workflow->receive((int) $row['id'], array_merge($row, $target), $userId, false),
                        };
                        $handling = ExpiredBoxHandling::findOrFail($row['id']);
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
                AuditService::record('expired_box', 'bulk_'.$data['action'], 'ExpiredBoxHandling', $result[0], $userId, [],
                    ['handling_ids' => $result, 'count' => count($result)],
                    'Penanganan massal '.$data['action'].': '.count($result).' box, seluruhnya tersimpan bersama.');
            }

            return $result;
        });
    }
}
