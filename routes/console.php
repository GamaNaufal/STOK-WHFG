<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
    $expired = \App\Models\DeliveryCompletion::where('status', 'completed')
        ->where('redo_until', '<', now())
        ->get();

    foreach ($expired as $completion) {
        $sessionId = $completion->pick_session_id;
        \App\Models\DeliveryPickItem::where('pick_session_id', $sessionId)->delete();
        \App\Models\DeliveryScanIssue::where('pick_session_id', $sessionId)->delete();
        \App\Models\DeliveryPickSession::where('id', $sessionId)->delete();
        $completion->delete();
    }

    $this->info('Expired completion data purged.');
})->purpose('Purge completed delivery data after redo window');
