<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

Schedule::call(function () {
    app(\App\Services\ExpiredBoxService::class)->sendDailySummary();
})->dailyAt('07:00');
