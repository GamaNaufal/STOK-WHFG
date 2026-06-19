<?php

namespace App\Services;

use App\Models\Box;
use App\Models\ExpiredBoxReport;
use App\Models\MasterLocation;
use App\Models\PalletItem;
use App\Models\User;
use App\Mail\ExpiredBoxSummaryMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class ExpiredBoxService
{
    private function canUseExpiredReports(): bool
    {
        return Schema::hasTable('expired_box_reports');
    }

    public function syncStatuses(): void
    {
        $this->getExpirableBoxesQuery()
            ->where(function ($q) { $q->whereNull('boxes.expired_status')->orWhereNotIn('boxes.expired_status', ['handled']); })
            ->orderBy('boxes.id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    if (!$row->stored_at) {
                        continue;
                    }

                    $storedAt = Carbon::parse($row->stored_at);
                    $ageMonths = $storedAt->diffInMonths(now());

                    $nextStatus = 'active';
                    if ($ageMonths >= 12) {
                        $nextStatus = 'expired';
                    } elseif ($ageMonths >= 9) {
                        $nextStatus = 'warning';
                    }

                    if ($row->expired_status !== $nextStatus) {
                        DB::transaction(function () use ($row, $nextStatus): void {
                            $box = Box::whereKey($row->id)->lockForUpdate()->first();
                            if (!$box || $box->expired_status === 'handled') {
                                return;
                            }

                            $wasInactive = in_array((string) $box->expired_status, ['expired', 'handled'], true);
                            $box->expired_status = $nextStatus;
                            $box->save();

                            if ($nextStatus === 'expired' && !$wasInactive) {
                                $this->syncLinkedPalletsAfterBoxDeactivation($box);
                            }
                        });
                    }

                    if ($nextStatus === 'expired') {
                        $this->ensureReportExists($row, $ageMonths, 'expired');
                    }
                }
            }, 'boxes.id', 'id');
    }

    public function handleBox(int $boxId, int $userId): void
    {
        DB::transaction(function () use ($boxId, $userId) {
            $box = Box::whereKey($boxId)->lockForUpdate()->first();
            if (!$box) {
                return;
            }

            if ($box->expired_status === 'handled') {
                return;
            }

            $row = $this->getExpirableBoxesQuery()
                ->where('boxes.id', $boxId)
                ->first();

            $storedAt = $row?->stored_at ? Carbon::parse($row->stored_at) : null;
            $ageMonths = $storedAt ? $storedAt->diffInMonths(now()) : 0;

            $box->expired_status = 'handled';
            $box->handled_at = now();
            $box->handled_by = $userId;
            $box->save();

            $this->syncLinkedPalletsAfterBoxDeactivation($box);

            if ($this->canUseExpiredReports() && $row) {
                ExpiredBoxReport::updateOrCreate(
                    [
                        'box_id' => $row->id,
                        'status' => 'handled',
                    ],
                    [
                        'box_number' => $row->box_number,
                        'part_number' => $row->part_number,
                        'pallet_id' => $row->pallet_id,
                        'pallet_number' => $row->pallet_number,
                        'warehouse_location' => $row->warehouse_location,
                        'stored_at' => $row->stored_at,
                        'age_months' => $ageMonths,
                        'handled_by' => $userId,
                        'handled_at' => now(),
                    ]
                );
            }
        });
    }

    private function syncLinkedPalletsAfterBoxDeactivation(Box $box): void
    {
        $linkedPallets = $box->pallets()
            ->with('stockLocation')
            ->lockForUpdate()
            ->get();

        foreach ($linkedPallets as $linkedPallet) {
            $activeForPart = $linkedPallet->activeBoxes()
                ->where('boxes.part_number', $box->part_number)
                ->get(['boxes.id', 'boxes.pcs_quantity']);

            $item = PalletItem::where('pallet_id', $linkedPallet->id)
                ->where('part_number', $box->part_number)
                ->lockForUpdate()
                ->first();

            if ($item) {
                $item->box_quantity = $activeForPart->count();
                $item->pcs_quantity = (int) $activeForPart->sum('pcs_quantity');
                $item->save();
            }

            $masterLocation = MasterLocation::where('current_pallet_id', $linkedPallet->id)
                ->lockForUpdate()
                ->first();
            if ($masterLocation) {
                $masterLocation->autoVacateIfEmpty();
            }

            if (!$linkedPallet->activeBoxes()->exists() && $linkedPallet->stockLocation) {
                $linkedPallet->stockLocation->delete();
            }
        }
    }

    public function sendDailySummary(): void
    {
        $this->syncStatuses();

        $warning = $this->getExpirableBoxesQuery()
            ->where('boxes.expired_status', 'warning')
            ->get();

        $expired = $this->getExpirableBoxesQuery()
            ->where('boxes.expired_status', 'expired')
            ->get();

        if ($warning->isEmpty() && $expired->isEmpty()) {
            return;
        }

        $emails = User::where('role', 'supervisi')
            ->whereNotNull('email')
            ->pluck('email')
            ->unique()
            ->values()
            ->all();

        if (empty($emails)) {
            return;
        }

        Mail::to($emails)->send(new ExpiredBoxSummaryMail($warning, $expired));
    }

    public function getExpirableBoxesQuery()
    {
        $storedAtSub = DB::table('stock_input_boxes as sib')
            ->join('stock_inputs as si', 'si.id', '=', 'sib.stock_input_id')
            ->whereNull('si.deleted_at')
            ->select('sib.box_id', DB::raw('MIN(si.stored_at) as stored_at'))
            ->groupBy('sib.box_id');

        $canonicalPalletSub = DB::table('pallet_boxes as pb')
            ->join('pallets as p', 'p.id', '=', 'pb.pallet_id')
            ->whereNull('p.deleted_at')
            ->select('pb.box_id', DB::raw('MAX(pb.id) as pivot_id'))
            ->groupBy('pb.box_id');

        return DB::table('boxes')
            ->leftJoinSub($storedAtSub, 'stock_in', function ($join) {
                $join->on('stock_in.box_id', '=', 'boxes.id');
            })
            ->joinSub($canonicalPalletSub, 'canonical_pallet', function ($join) {
                $join->on('canonical_pallet.box_id', '=', 'boxes.id');
            })
            ->join('pallet_boxes', 'pallet_boxes.id', '=', 'canonical_pallet.pivot_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->leftJoin('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->whereNull('boxes.deleted_at')
            ->where('boxes.is_withdrawn', false)
            ->select(
                'boxes.id',
                'boxes.box_number',
                'boxes.part_number',
                'boxes.expired_status',
                DB::raw('COALESCE(boxes.created_at, stock_in.stored_at) as stored_at'),
                'pallets.id as pallet_id',
                'pallets.pallet_number',
                'stock_locations.warehouse_location'
            );
    }

    private function ensureReportExists(object $row, int $ageMonths, string $status): void
    {
        if (!$this->canUseExpiredReports()) {
            return;
        }

        ExpiredBoxReport::updateOrCreate(
            [
                'box_id' => $row->id,
                'status' => $status,
            ],
            [
                'box_number' => $row->box_number,
                'part_number' => $row->part_number,
                'pallet_id' => $row->pallet_id,
                'pallet_number' => $row->pallet_number,
                'warehouse_location' => $row->warehouse_location,
                'stored_at' => $row->stored_at,
                'age_months' => $ageMonths,
                'handled_by' => null,
                'handled_at' => null,
            ]
        );
    }
}
