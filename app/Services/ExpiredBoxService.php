<?php

namespace App\Services;

use App\Mail\ExpiredBoxSummaryMail;
use App\Models\Box;
use App\Models\ExpiredBoxReport;
use App\Models\MasterLocation;
use App\Models\PalletItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class ExpiredBoxService
{
    public function __construct(private readonly PalletDeliveryLockService $palletDeliveryLockService) {}

    private function canUseExpiredReports(): bool
    {
        return Schema::hasTable('expired_box_reports');
    }

    public function syncStatuses(): void
    {
        $this->getExpirableBoxesQuery()
            ->where(function ($q) {
                $q->whereNull('boxes.expired_status')->orWhere('boxes.expired_status', '!=', 'handled');
            })
            ->orderBy('boxes.id')->chunkById(500, function ($rows): void {
                foreach ($rows as $candidate) {
                    if (! $candidate->stored_at) {
                        continue;
                    }
                    $candidateUntil = $candidate->reactivated_until ? Carbon::parse($candidate->reactivated_until) : null;
                    $candidateAge = Carbon::parse($candidate->stored_at)->diffInMonths(now());
                    if ($candidate->expired_status === $this->statusFor($candidateUntil, $candidateAge)) {
                        continue;
                    }

                    DB::transaction(function () use ($candidate): void {
                        $box = Box::whereKey($candidate->id)->lockForUpdate()->first();
                        if (! $box || $box->is_withdrawn || $box->expired_status === 'handled') {
                            return;
                        }
                        $row = $this->getExpirableBoxesQuery()->where('boxes.id', $box->id)->first();
                        if (! $row || ! $row->stored_at) {
                            return;
                        }

                        $ageMonths = Carbon::parse($row->stored_at)->diffInMonths(now());
                        $nextStatus = $this->statusFor($box->reactivated_until, $ageMonths);
                        // Capture the old storage location before inactive stock vacates it.
                        if ($nextStatus === 'expired') {
                            $this->ensureReportExists($row, (int) $ageMonths, $nextStatus);
                        }
                        if ($box->expired_status !== $nextStatus) {
                            $box->expired_status = $nextStatus;
                            $box->save();
                        }
                        if ($nextStatus === 'expired') {
                            $this->syncLinkedPalletsAfterBoxDeactivation($box);
                        }
                    });
                }
            }, 'boxes.id', 'id');
    }

    private function statusFor(?Carbon $reactivatedUntil, float $ageMonths): string
    {
        if ($reactivatedUntil) {
            return $reactivatedUntil->gt(now()) ? 'active' : 'expired';
        }

        return $ageMonths >= 12 ? 'expired' : ($ageMonths >= 9 ? 'warning' : 'active');
    }

    public function handleBox(int $boxId, int $userId, ?int $locationId = null): void
    {
        app(ExpiredBoxHandlingService::class)->start($boxId, $userId);
    }

    public function syncLinkedPalletsAfterBoxDeactivation(Box $box): void
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

            if (! $item && $activeForPart->isNotEmpty()) {
                $item = new PalletItem(['pallet_id' => $linkedPallet->id, 'part_number' => $box->part_number]);
            }
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

            if (! $linkedPallet->activeBoxes()->exists() && $linkedPallet->stockLocation) {
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
            ->leftJoinSub($canonicalPalletSub, 'canonical_pallet', function ($join) {
                $join->on('canonical_pallet.box_id', '=', 'boxes.id');
            })
            ->leftJoin('pallet_boxes', 'pallet_boxes.id', '=', 'canonical_pallet.pivot_id')
            ->leftJoin('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->leftJoin('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->whereNull('boxes.deleted_at')
            ->where('boxes.is_withdrawn', false)
            ->select(
                'boxes.id',
                'boxes.box_number',
                'boxes.part_number',
                'boxes.pcs_quantity',
                'boxes.expired_status',
                'boxes.reactivated_until',
                'stock_locations.master_location_id',
                DB::raw('COALESCE(boxes.created_at, stock_in.stored_at) as stored_at'),
                'pallets.id as pallet_id',
                'pallets.pallet_number',
                'stock_locations.warehouse_location'
            );
    }

    private function ensureReportExists(object $row, int $ageMonths, string $status): void
    {
        if (! $this->canUseExpiredReports()) {
            return;
        }

        ExpiredBoxReport::firstOrCreate(
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
