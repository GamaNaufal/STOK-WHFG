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

    DB  ::transaction(function () {
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

Artisan::command('stock-input:diagnose
    {stockInputId? : Stock input ID to inspect}
    {--recent=0 : Show latest rows when ID is omitted}
    {--date= : Filter by stored_at prefix (YYYY-MM-DD or YYYY-MM-DD HH:MM)}
    {--pallet= : Filter by pallet number (example: PLT-108)}
    {--operator= : Filter by operator name}
    {--location= : Filter by warehouse location}
    {--all : Include matched rows when listing without stockInputId}', function () {
    $stockInputIdArg = $this->argument('stockInputId');
    $recent = max(0, (int) $this->option('recent'));
    $dateFilter = trim((string) $this->option('date'));
    $palletFilter = trim((string) $this->option('pallet'));
    $operatorFilter = trim((string) $this->option('operator'));
    $locationFilter = trim((string) $this->option('location'));
    $includeAll = (bool) $this->option('all');

    if ($stockInputIdArg === null) {
        $limit = $recent > 0 ? $recent : 20;

        $listQuery = DB::table('stock_inputs as si')
            ->leftJoin('pallets as p', 'p.id', '=', 'si.pallet_id')
            ->leftJoin('users as u', 'u.id', '=', 'si.user_id')
            ->leftJoin('stock_input_boxes as sib', 'sib.stock_input_id', '=', 'si.id')
            ->whereNull('si.deleted_at')
            ->when($dateFilter !== '', fn ($q) => $q->where('si.stored_at', 'like', $dateFilter . '%'))
            ->when($palletFilter !== '', fn ($q) => $q->where('p.pallet_number', 'like', '%' . $palletFilter . '%'))
            ->when($operatorFilter !== '', fn ($q) => $q->where('u.name', 'like', '%' . $operatorFilter . '%'))
            ->when($locationFilter !== '', fn ($q) => $q->where('si.warehouse_location', 'like', '%' . $locationFilter . '%'))
            ->groupBy('si.id', 'si.stored_at', 'si.user_id', 'u.name', 'si.warehouse_location', 'si.box_quantity', 'p.pallet_number')
            ->selectRaw('si.id, si.stored_at, si.user_id, u.name as operator_name, p.pallet_number, si.warehouse_location, si.box_quantity as recorded_box_qty, COUNT(sib.id) as mapped_box_qty');

        if (!$includeAll) {
            $listQuery->havingRaw('si.box_quantity <> COUNT(sib.id)');
        }

        $recentRows = $listQuery
            ->orderByDesc('si.stored_at')
            ->limit($limit)
            ->get();

        if ($recentRows->isEmpty()) {
            if ($includeAll) {
                $this->info('No stock input rows found for current filters.');
            } else {
                $this->info('No stock input mismatch found for current filters.');
            }
            return 0;
        }

        if ($includeAll) {
            $this->warn('Recent stock input rows:');
        } else {
            $this->warn('Recent stock input mismatch found:');
        }
        $this->table(
            ['stock_input_id', 'stored_at', 'pallet', 'operator', 'user_id', 'location', 'recorded_box', 'mapped_box', 'gap'],
            $recentRows->map(function ($row) {
                $recorded = (int) round((float) $row->recorded_box_qty);
                $mapped = (int) $row->mapped_box_qty;

                return [
                    (int) $row->id,
                    (string) $row->stored_at,
                    (string) ($row->pallet_number ?? '-'),
                    (string) ($row->operator_name ?? '-'),
                    (int) $row->user_id,
                    (string) $row->warehouse_location,
                    $recorded,
                    $mapped,
                    $recorded - $mapped,
                ];
            })->all()
        );

        $this->line('Run detail check: php artisan stock-input:diagnose <stock_input_id>');
        $this->line('Example filter check: php artisan stock-input:diagnose --date="2026-04-20" --pallet="PLT-108" --all --recent=30');
        return 0;
    }

    $stockInputId = (int) $stockInputIdArg;
    if ($stockInputId <= 0) {
        $this->error('stockInputId must be a positive integer.');
        return 1;
    }

    $summary = DB::table('stock_inputs as si')
        ->leftJoin('stock_input_boxes as sib', 'sib.stock_input_id', '=', 'si.id')
        ->leftJoin('boxes as b', 'b.id', '=', 'sib.box_id')
        ->where('si.id', $stockInputId)
        ->groupBy('si.id', 'si.stored_at', 'si.user_id', 'si.warehouse_location', 'si.box_quantity', 'si.pcs_quantity')
        ->selectRaw('si.id, si.stored_at, si.user_id, si.warehouse_location')
        ->selectRaw('si.box_quantity as recorded_box_qty, si.pcs_quantity as recorded_pcs_qty')
        ->selectRaw('COUNT(sib.id) as mapped_box_qty')
        ->selectRaw('SUM(CASE WHEN b.deleted_at IS NULL THEN 1 ELSE 0 END) as active_box_qty')
        ->selectRaw('SUM(CASE WHEN b.deleted_at IS NOT NULL THEN 1 ELSE 0 END) as soft_deleted_box_qty')
        ->selectRaw('SUM(CASE WHEN b.deleted_at IS NULL THEN COALESCE(b.pcs_quantity, 0) ELSE 0 END) as active_pcs_qty')
        ->selectRaw('SUM(CASE WHEN b.deleted_at IS NOT NULL THEN COALESCE(b.pcs_quantity, 0) ELSE 0 END) as soft_deleted_pcs_qty')
        ->first();

    if (!$summary) {
        $this->error('stock_input_id not found: ' . $stockInputId);
        return 1;
    }

    $recordedBoxQty = (int) round((float) $summary->recorded_box_qty);
    $mappedBoxQty = (int) $summary->mapped_box_qty;
    $boxGap = $recordedBoxQty - $mappedBoxQty;

    $this->line('=== Stock Input Diagnose ===');
    $this->table(
        ['metric', 'value'],
        [
            ['stock_input_id', (int) $summary->id],
            ['stored_at', (string) $summary->stored_at],
            ['user_id', (int) $summary->user_id],
            ['warehouse_location', (string) $summary->warehouse_location],
            ['recorded_box_qty', $recordedBoxQty],
            ['mapped_box_qty (stock_input_boxes)', $mappedBoxQty],
            ['gap (recorded - mapped)', $boxGap],
            ['active_box_qty', (int) $summary->active_box_qty],
            ['soft_deleted_box_qty', (int) $summary->soft_deleted_box_qty],
            ['recorded_pcs_qty', (int) $summary->recorded_pcs_qty],
            ['active_pcs_qty', (int) $summary->active_pcs_qty],
            ['soft_deleted_pcs_qty', (int) $summary->soft_deleted_pcs_qty],
        ]
    );

    $boxRows = DB::table('stock_input_boxes as sib')
        ->leftJoin('boxes as b', 'b.id', '=', 'sib.box_id')
        ->where('sib.stock_input_id', $stockInputId)
        ->orderBy('sib.box_id')
        ->get([
            'sib.box_id',
            'b.box_number',
            'b.part_number',
            'b.pcs_quantity',
            'b.is_withdrawn',
            'b.deleted_at',
        ]);

    if ($boxRows->isNotEmpty()) {
        $this->newLine();
        $this->line('Box mapping detail (max 100 rows):');
        $this->table(
            ['box_id', 'box_number', 'part_number', 'pcs', 'status'],
            $boxRows->take(100)->map(function ($row) {
                $status = 'active';
                if ($row->deleted_at !== null) {
                    $status = 'soft-deleted';
                } elseif ((int) $row->is_withdrawn === 1) {
                    $status = 'withdrawn';
                }

                return [
                    (int) $row->box_id,
                    (string) ($row->box_number ?? '-'),
                    (string) ($row->part_number ?? '-'),
                    (int) ($row->pcs_quantity ?? 0),
                    $status,
                ];
            })->all()
        );

        if ($boxRows->count() > 100) {
            $this->warn('Only first 100 rows shown. Total mapped rows: ' . $boxRows->count());
        }
    }

    $this->newLine();
    $this->line('Interpretation:');

    $storedAt = (string) $summary->stored_at;
    $pivotFeatureDate = '2026-03-11 00:00:00';

    if ($boxGap === 0) {
        $this->info('- recorded_box_qty matches mapped_box_qty.');
    } else {
        $this->warn('- recorded_box_qty does not match mapped_box_qty.');

        if ($storedAt < $pivotFeatureDate) {
            $this->line('- Possible legacy data: stock input created before stock_input_boxes migration date.');
        }

        if ($recordedBoxQty === 1 && $mappedBoxQty === 0) {
            $this->line('- Possible Not Full approval flow: stock_inputs row exists without stock_input_boxes mapping.');
        }

        $this->line('- Possible hard-delete history: forceDelete on boxes can remove pivot rows via FK cascade.');
    }

    if ((int) $summary->soft_deleted_box_qty > 0) {
        $this->line('- Soft-deleted boxes exist in mapping. Use withTrashed in report query to include them.');
    }

    return 0;
})->purpose('Diagnose mismatch between stock_inputs.box_quantity and stock_input_boxes mappings');

Schedule::call(function () {
    app(\App\Services\ExpiredBoxService::class)->sendDailySummary();
})->dailyAt('07:00');
