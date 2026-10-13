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
        $this->syncStatuses();
        DB::transaction(function () use ($boxId, $userId, $locationId) {
            $box = Box::whereKey($boxId)->lockForUpdate()->first();
            if (! $box || $box->is_withdrawn) {
                throw new \RuntimeException('Box tidak ditemukan atau sudah dikirim.');
            }
            // Idempotent repeated requests must not extend an already active period.
            if ($box->reactivated_until && $box->reactivated_until->gt(now()) && $box->expired_status === 'active') {
                return;
            }
            if ($box->expired_status !== 'expired') {
                throw new \RuntimeException('Hanya box expired yang dapat di-Handle. Box warning belum expired dan tetap dapat masuk FIFO.');
            }
            if ($box->assigned_delivery_order_id !== null) {
                throw new \RuntimeException('Box masih di-assign ke delivery. Lepaskan atau selesaikan proses delivery sebelum mengaktifkan box.');
            }
            $isPickLocked = DB::table('delivery_pick_items')
                ->join('delivery_pick_sessions', 'delivery_pick_sessions.id', '=', 'delivery_pick_items.pick_session_id')
                ->where('delivery_pick_items.box_id', $box->id)
                ->whereIn('delivery_pick_sessions.status', ['pending', 'scanning', 'blocked', 'approved'])
                ->lockForUpdate()->exists();
            if ($isPickLocked) {
                throw new \RuntimeException('Box sedang digunakan dalam sesi picking dan belum dapat diaktifkan kembali.');
            }

            $row = $this->getExpirableBoxesQuery()->where('boxes.id', $boxId)->first();
            if (! $row || ! $row->stored_at) {
                throw new \RuntimeException('Riwayat penyimpanan box tidak valid.');
            }
            $ageMonths = Carbon::parse($row->stored_at)->diffInMonths(now());
            if ($ageMonths < 12 && ! $box->reactivated_until) {
                throw new \RuntimeException('Box belum expired dan tidak dapat di-Handle.');
            }

            $pallet = \App\Models\Pallet::whereKey($row->pallet_id)->lockForUpdate()->firstOrFail();
            $stockLocation = $pallet->stockLocation()->lockForUpdate()->first();
            if ($stockLocation) {
                $currentLocationId = $stockLocation->master_location_id
                    ?: MasterLocation::where('code', $stockLocation->warehouse_location)->value('id');
                if ($locationId && (int) $locationId !== (int) $currentLocationId) {
                    throw new \RuntimeException('Pallet masih memiliki lokasi. Handle harus menggunakan lokasi pallet saat ini.');
                }
                $locationId = $currentLocationId;
            } elseif (! $locationId && $this->canUseExpiredReports()) {
                $previousCode = ExpiredBoxReport::where('box_id', $box->id)->where('pallet_id', $pallet->id)
                    ->whereNotNull('warehouse_location')->latest('id')->value('warehouse_location');
                $locationId = $previousCode ? MasterLocation::where('code', $previousCode)->value('id') : null;
            }
            if (! $locationId) {
                throw new \RuntimeException('Pilih lokasi penyimpanan kosong untuk mengaktifkan box.');
            }

            // Claim before reactivation; a location conflict rolls back the entire renewal.
            $storage = app(LocationAssignmentService::class)->assign((int) $locationId, $pallet, $stockLocation?->stored_at ?? Carbon::parse($row->stored_at));
            $box->expired_status = 'active';
            $box->handled_at = now();
            $box->handled_by = $userId;
            $box->reactivated_until = now()->addMonthNoOverflow();
            $box->save();
            $this->syncLinkedPalletsAfterBoxDeactivation($box);

            if ($this->canUseExpiredReports()) {
                ExpiredBoxReport::create([
                    'box_id' => $box->id, 'status' => 'handled',
                    'box_number' => $box->box_number, 'part_number' => $box->part_number,
                    'pallet_id' => $pallet->id, 'pallet_number' => $pallet->pallet_number,
                    'warehouse_location' => $storage->warehouse_location,
                    'stored_at' => $row->stored_at, 'age_months' => (int) $ageMonths,
                    'handled_by' => $userId, 'handled_at' => $box->handled_at,
                    'reactivated_until' => $box->reactivated_until,
                ]);
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
