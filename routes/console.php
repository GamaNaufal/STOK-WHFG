<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('locations:sync', function () {
    $this->info('Syncing master_locations with stock_locations...');

    \Illuminate\Support\Facades\DB::transaction(function () {
        \App\Models\MasterLocation::query()->update([
            'is_occupied' => false,
            'current_pallet_id' => null,
        ]);

        $latestByLocation = \App\Models\StockLocation::query()
            ->whereNotNull('warehouse_location')
            ->where('warehouse_location', '!=', 'Unknown')
            ->orderByDesc('stored_at')
            ->get()
            ->groupBy('warehouse_location');

        foreach ($latestByLocation as $code => $records) {
            $latest = $records->first();

            \App\Models\MasterLocation::where('code', $code)->update([
                'is_occupied' => true,
                'current_pallet_id' => $latest->pallet_id,
            ]);
        }
    });

    $this->info('Done.');
})->purpose('Sync master_locations occupancy from latest stock_locations');

Artisan::command('delivery:purge-completions', function () {
    $expiredSessions = \App\Models\DeliveryPickSession::where('completion_status', 'completed')
        ->whereNotNull('redo_until')
        ->where('redo_until', '<', now())
        ->get();

    foreach ($expiredSessions as $session) {
        $sessionId = $session->id;
        \App\Models\DeliveryPickItem::where('pick_session_id', $sessionId)->delete();
        \App\Models\DeliveryPickSession::where('id', $sessionId)->delete();
    }

    $this->info('Expired completion data purged.');
})->purpose('Purge completed delivery data after redo window');

Artisan::command('expired-box:send-test', function () {
    app(\App\Services\ExpiredBoxService::class)->sendDailySummary();
    $this->info('Expired box email summary sent (if data exists).');
})->purpose('Send expired box summary email on demand');

Artisan::command('health:concurrency', function () {
    $activeSessionDuplicates = DB::table('delivery_pick_sessions')
        ->select('delivery_order_id', DB::raw('COUNT(*) as total'))
        ->whereIn('status', ['scanning', 'blocked', 'approved'])
        ->groupBy('delivery_order_id')
        ->havingRaw('COUNT(*) > 1')
        ->get();

    $duplicatePickItems = DB::table('delivery_pick_items')
        ->select('pick_session_id', 'box_id', DB::raw('COUNT(*) as total'))
        ->groupBy('pick_session_id', 'box_id')
        ->havingRaw('COUNT(*) > 1')
        ->get();

    $duplicateWithdrawals = DB::table('stock_withdrawals')
        ->whereNotNull('pick_session_id')
        ->select('pick_session_id', 'box_id', DB::raw('COUNT(*) as total'))
        ->groupBy('pick_session_id', 'box_id')
        ->havingRaw('COUNT(*) > 1')
        ->get();

    $hasVerificationColumn = Schema::hasColumn('delivery_pick_sessions', 'verification_box_ids');
    $verificationOverflows = collect();

    if ($hasVerificationColumn) {
        $verificationOverflows = DB::table('delivery_pick_sessions as s')
            ->selectRaw('s.id as session_id')
            ->selectRaw('COALESCE(JSON_LENGTH(verification_box_ids), 0) as verified_total')
            ->selectRaw('(SELECT COUNT(*) FROM delivery_pick_items i WHERE i.pick_session_id = s.id) as item_total')
            ->whereRaw('COALESCE(JSON_LENGTH(verification_box_ids), 0) > (SELECT COUNT(*) FROM delivery_pick_items i WHERE i.pick_session_id = s.id)')
            ->get();
    }

    $this->line('=== Concurrency Health Check ===');
    $this->line('Active session duplicates: ' . $activeSessionDuplicates->count());
    $this->line('Duplicate pick items: ' . $duplicatePickItems->count());
    $this->line('Duplicate withdrawals: ' . $duplicateWithdrawals->count());
    $this->line('Verification overflows: ' . $verificationOverflows->count());
    if (!$hasVerificationColumn) {
        $this->warn('Verification overflow check skipped: column verification_box_ids not found.');
    }

    if ($activeSessionDuplicates->isNotEmpty()) {
        $this->newLine();
        $this->warn('Active session duplicates detail:');
        $this->table(['delivery_order_id', 'total'], $activeSessionDuplicates->map(fn ($row) => [(int) $row->delivery_order_id, (int) $row->total])->all());
    }

    if ($duplicatePickItems->isNotEmpty()) {
        $this->newLine();
        $this->warn('Duplicate pick items detail:');
        $this->table(['pick_session_id', 'box_id', 'total'], $duplicatePickItems->map(fn ($row) => [(int) $row->pick_session_id, (int) $row->box_id, (int) $row->total])->all());
    }

    if ($duplicateWithdrawals->isNotEmpty()) {
        $this->newLine();
        $this->warn('Duplicate withdrawals detail:');
        $this->table(['pick_session_id', 'box_id', 'total'], $duplicateWithdrawals->map(fn ($row) => [(int) $row->pick_session_id, (int) $row->box_id, (int) $row->total])->all());
    }

    if ($verificationOverflows->isNotEmpty()) {
        $this->newLine();
        $this->warn('Verification overflow detail:');
        $this->table(['session_id', 'verified_total', 'item_total'], $verificationOverflows->map(fn ($row) => [(int) $row->session_id, (int) $row->verified_total, (int) $row->item_total])->all());
    }

    $hasIssue = $activeSessionDuplicates->isNotEmpty()
        || $duplicatePickItems->isNotEmpty()
        || $duplicateWithdrawals->isNotEmpty()
        || $verificationOverflows->isNotEmpty();

    if ($hasIssue) {
        $this->error('FAILED: concurrency anomalies detected.');
        return 1;
    }

    $this->info('OK: no concurrency anomalies detected.');
    return 0;
})->purpose('Run delivery concurrency health checks (session/item/withdrawal/verification consistency)');

Schedule::call(function () {
    app(\App\Services\ExpiredBoxService::class)->sendDailySummary();
})->dailyAt('07:00');
