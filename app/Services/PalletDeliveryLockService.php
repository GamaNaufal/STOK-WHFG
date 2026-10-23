<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PalletDeliveryLockService
{
    public const BLOCKING_SESSION_STATUSES = ['scanning', 'blocked', 'approved'];

    /**
     * Pending pick items are locked during mutations so picking cannot start
     * between eligibility validation and the pallet update.
     */
    public function blockingBoxForPallets(array $palletIds, bool $lockRows = false): ?object
    {
        $palletIds = collect($palletIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if ($palletIds === []) {
            return null;
        }

        $statuses = $lockRows
            ? ['pending', ...self::BLOCKING_SESSION_STATUSES]
            : self::BLOCKING_SESSION_STATUSES;

        $query = DB::table('pallet_boxes')
            ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('delivery_pick_items', 'delivery_pick_items.box_id', '=', 'pallet_boxes.box_id')
            ->join('delivery_pick_sessions', 'delivery_pick_sessions.id', '=', 'delivery_pick_items.pick_session_id')
            ->whereIn('pallet_boxes.pallet_id', $palletIds)
            ->whereIn('delivery_pick_sessions.status', $statuses)
            ->orderBy('delivery_pick_sessions.id')
            ->orderBy('delivery_pick_items.id');

        if ($lockRows) {
            $query->lockForUpdate();
        }

        return $query->get([
            'delivery_pick_items.box_id',
            'boxes.box_number',
            'delivery_pick_sessions.status',
        ])->first(fn ($row) => in_array($row->status, self::BLOCKING_SESSION_STATUSES, true));
    }
}
