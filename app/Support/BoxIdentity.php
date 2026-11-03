<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class BoxIdentity
{
    public static function registered(string $number): bool
    {
        return DB::table('box_identities')->where('box_number', $number)->exists();
    }

    public static function unavailable(string $number, ?int $exceptExportRequest = null): bool
    {
        return self::registered($number)
            || DB::table('not_full_box_requests')->where('box_number', $number)->where('status', 'pending')->exists()
            || DB::table('export_not_full_requests')->where('status', 'prepared')->when($exceptExportRequest, fn ($q) => $q->where('id', '!=', $exceptExportRequest))->where('preparation->box_number', $number)->exists();
    }
}
