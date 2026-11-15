<?php

namespace App\Support;

use App\Models\Box;
use App\Models\ExportFulfillmentBox;
use App\Models\ExportStockBox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class BoxIdentity
{
    public static function registered(string $number): bool
    {
        return DB::table('box_identities')->where('box_number', $number)->exists();
    }

    public static function unavailable(string $number, ?int $exceptExportRequest = null): bool
    {
        return self::occupied($number)
            || DB::table('not_full_box_requests')->where('box_number', $number)->where('status', 'pending')->exists()
            || DB::table('export_not_full_requests')->where('status', 'prepared')->when($exceptExportRequest, fn ($q) => $q->where('id', '!=', $exceptExportRequest))->where('preparation->box_number', $number)->exists()
            || \App\Models\ExportPickingIssue::open()->where('box_number', $number)->exists();
    }

    public static function legacyCommitment(?string $number = null)
    {
        return ExportFulfillmentBox::when($number !== null, fn ($q) => $q->where('box_number', $number))->whereNull('export_stock_box_id')
            ->whereHas('plannedItem.pallet.palletPlan.deliveryPlan', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']));
    }

    /** Expired stock and ongoing QA still own their labels. Departed/deleted stock does not. */
    public static function occupied(string $number, ?Model $except = null, bool $lock = false): bool
    {
        foreach ([Box::class, ExportStockBox::class] as $model) {
            $query = $model::identityHeld()->where('box_number', $number);
            if ($except instanceof $model && $except->exists) {
                $query->whereKeyNot($except->getKey());
            }
            if ($lock) {
                $query->lockForUpdate();
            }
            if ($query->first(['id'])) {
                return true;
            }
        }
        $query = self::legacyCommitment($number);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first(['id']) !== null;
    }

    public static function lastCycle(string $number): int
    {
        return (int) DB::table('box_identities')->where('box_number', $number)->value('last_cycle');
    }

    /** Called inside the same transaction as the receipt. The persistent row serializes reuse globally. */
    public static function claim(Model $box, bool $legacy = false): int
    {
        DB::table('box_identities')->insertOrIgnore([
            'box_number' => $box->box_number, 'origin_scope' => $box::INVENTORY_SCOPE, 'last_cycle' => 0,
        ]);
        $registry = DB::table('box_identities')->where('box_number', $box->box_number)->lockForUpdate()->first();
        if (! $legacy && self::occupied($box->box_number, null, true)) {
            throw new \RuntimeException('ID Box masih digunakan oleh box atau proses aktif pada lokal/ekspor.');
        }
        $cycle = $legacy ? (int) $registry->last_cycle : (int) $registry->last_cycle + 1;
        DB::table('box_identities')->where('box_number', $box->box_number)->update([
            'last_cycle' => $cycle, 'origin_scope' => $box::INVENTORY_SCOPE,
        ]);

        return $cycle;
    }

    public static function guardSnapshot(string $number, int $expectedCycle): void
    {
        DB::table('box_identities')->insertOrIgnore(['box_number' => $number, 'origin_scope' => 'domestic', 'last_cycle' => 0]);
        $current = DB::table('box_identities')->where('box_number', $number)->lockForUpdate()->first();
        if ((int) $current->last_cycle !== $expectedCycle) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'box_number' => 'Penggunaan ID Box '.$number.' berubah sejak scan. Hapus dari daftar lalu scan ulang.',
            ]);
        }
    }

    public static function assertCanReactivate(Model $box): void
    {
        $registry = DB::table('box_identities')->where('box_number', $box->box_number)->lockForUpdate()->first();
        if (! $registry || (int) $registry->last_cycle !== (int) $box->identity_cycle
            || self::occupied($box->box_number, $box, true)) {
            throw new \RuntimeException('Box '.$box->box_number.' tidak dapat diaktifkan kembali karena nomornya telah digunakan untuk penerimaan lain. Riwayat lama tetap disimpan.');
        }
    }
}
