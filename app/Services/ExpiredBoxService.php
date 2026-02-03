<?php

namespace App\Services;

use App\Models\Box;
use App\Models\ExpiredBoxReport;
use App\Models\User;
use App\Mail\ExpiredBoxSummaryMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ExpiredBoxService
{
    public function syncStatuses(): void
    {
        $boxes = $this->getExpirableBoxesQuery()
            ->whereNotIn('boxes.expired_status', ['handled'])
            ->get();

        foreach ($boxes as $row) {
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
                Box::where('id', $row->id)->update(['expired_status' => $nextStatus]);
            }

            if ($nextStatus === 'expired') {
                $this->ensureReportExists($row, $ageMonths, 'expired');
            }
        }
    }

    public function handleBox(int $boxId, int $userId): void
    {
        $row = $this->getExpirableBoxesQuery()
            ->where('boxes.id', $boxId)
            ->first();

        if (!$row) {
            return;
        }

        $storedAt = $row->stored_at ? Carbon::parse($row->stored_at) : null;
        $ageMonths = $storedAt ? $storedAt->diffInMonths(now()) : 0;

        Box::where('id', $boxId)->update([
            'expired_status' => 'handled',
            'handled_at' => now(),
            'handled_by' => $userId,
        ]);

        ExpiredBoxReport::create([
            'box_id' => $row->id,
            'box_number' => $row->box_number,
            'part_number' => $row->part_number,
            'pallet_id' => $row->pallet_id,
            'pallet_number' => $row->pallet_number,
            'warehouse_location' => $row->warehouse_location,
            'stored_at' => $row->stored_at,
            'age_months' => $ageMonths,
            'status' => 'handled',
            'handled_by' => $userId,
            'handled_at' => now(),
        ]);
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
        $storedAtSub = DB::table('pallet_boxes as pb')
            ->join('stock_inputs as si', 'si.pallet_id', '=', 'pb.pallet_id')
            ->select('pb.box_id', DB::raw('MIN(si.stored_at) as stored_at'))
            ->groupBy('pb.box_id');

        return DB::table('boxes')
            ->joinSub($storedAtSub, 'stock_in', function ($join) {
                $join->on('stock_in.box_id', '=', 'boxes.id');
            })
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->leftJoin('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->whereNull('boxes.deleted_at')
            ->where('boxes.is_withdrawn', false)
            ->select(
                'boxes.id',
                'boxes.box_number',
                'boxes.part_number',
                'boxes.expired_status',
                'stock_in.stored_at',
                'pallets.id as pallet_id',
                'pallets.pallet_number',
                'stock_locations.warehouse_location'
            );
    }

    private function ensureReportExists(object $row, int $ageMonths, string $status): void
    {
        $exists = ExpiredBoxReport::where('box_id', $row->id)
            ->where('status', $status)
            ->exists();

        if ($exists) {
            return;
        }

        ExpiredBoxReport::create([
            'box_id' => $row->id,
            'box_number' => $row->box_number,
            'part_number' => $row->part_number,
            'pallet_id' => $row->pallet_id,
            'pallet_number' => $row->pallet_number,
            'warehouse_location' => $row->warehouse_location,
            'stored_at' => $row->stored_at,
            'age_months' => $ageMonths,
            'status' => $status,
            'handled_by' => null,
            'handled_at' => null,
        ]);
    }
}
