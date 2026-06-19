<?php

namespace App\Http\Controllers;

use App\Exports\StockViewByBoxIdExport;
use App\Exports\StockViewByPalletExport;
use App\Exports\StockViewByPartExport;
use App\Models\AuditLog;
use App\Models\Box;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Services\AuditService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class StockViewController extends Controller
{
    private function resolveDirectBoxTarget(string $search, $items): ?array
    {
        $needle = mb_strtolower(trim($search));
        if ($needle === '') {
            return null;
        }

        $matches = $items
            ->filter(function ($item) use ($needle) {
                $boxId = isset($item['box_id']) ? (string) $item['box_id'] : '';
                $boxNumber = isset($item['box_number']) ? (string) $item['box_number'] : '';
                if ($boxId === '' && $boxNumber === '') {
                    return false;
                }

                return mb_strtolower($boxId) === $needle
                    || mb_strtolower($boxNumber) === $needle;
            })
            ->values();

        if ($matches->count() !== 1) {
            return null;
        }

        $target = $matches->first();
        if (empty($target['box_id']) || empty($target['pallet_id'])) {
            return null;
        }

        return [
            'box_id' => (int) $target['box_id'],
            'box_number' => (string) ($target['box_number'] ?? ''),
            'pallet_id' => (int) $target['pallet_id'],
            'pallet_number' => (string) ($target['pallet_number'] ?? ''),
        ];
    }

    private function matchesGlobalSearch(?string $search, ?string $location, ?string $partNumber, ?string $palletNumber, ?string $boxNumber = null, $boxId = null): bool
    {
        if (!filled($search)) {
            return true;
        }

        $needle = mb_strtolower(trim((string) $search));
        if ($needle === '') {
            return true;
        }

        $candidates = [
            (string) ($location ?? ''),
            (string) ($partNumber ?? ''),
            (string) ($palletNumber ?? ''),
            (string) ($boxNumber ?? ''),
            (string) ($boxId ?? ''),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && mb_stripos($candidate, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function canDeleteStock(?object $user): bool
    {
        return $user && in_array($user->role, ['admin_warehouse', 'supervisi', 'admin'], true);
    }

    private function isBoxLockedByActivePicking(int $boxId): bool
    {
        return DB::table('delivery_pick_items')
            ->join('delivery_pick_sessions', 'delivery_pick_sessions.id', '=', 'delivery_pick_items.pick_session_id')
            ->where('delivery_pick_items.box_id', $boxId)
            ->whereIn('delivery_pick_sessions.status', ['pending', 'scanning', 'blocked', 'approved'])
            ->exists();
    }

    private function syncStockInputHeadersForBox(int $boxId): void
    {
        $stockInputIds = DB::table('stock_input_boxes')
            ->where('box_id', $boxId)
            ->pluck('stock_input_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        foreach ($stockInputIds as $stockInputId) {
            $mappedBoxes = DB::table('stock_input_boxes as sib')
                ->join('boxes', 'boxes.id', '=', 'sib.box_id')
                ->where('sib.stock_input_id', $stockInputId)
                ->select('boxes.part_number', 'boxes.pcs_quantity', 'boxes.created_at')
                ->get();

            if ($mappedBoxes->isEmpty()) {
                continue;
            }

            DB::table('stock_inputs')
                ->where('id', $stockInputId)
                ->update([
                    'pcs_quantity' => (int) $mappedBoxes->sum('pcs_quantity'),
                    'box_quantity' => $mappedBoxes->count(),
                    'part_numbers' => json_encode(
                        $mappedBoxes->pluck('part_number')->filter()->unique()->values()->all()
                    ),
                    'stored_at' => $mappedBoxes->min('created_at'),
                    'updated_at' => now(),
                ]);
        }
    }

    private function getDefaultSortMode(string $viewMode): string
    {
        return match ($viewMode) {
            'box_id' => 'box_id_asc',
            'pallet' => 'pallet_asc',
            'not_full' => 'created_oldest',
            default => 'part_asc',
        };
    }

    private function getSortOptionsByViewMode(string $viewMode): array
    {
        return match ($viewMode) {
            'box_id' => [
                'box_id_asc' => 'ID Box A-Z',
                'box_id_desc' => 'ID Box Z-A',
                'box_number_asc' => 'No Box A-Z',
                'box_number_desc' => 'No Box Z-A',
                'part_asc' => 'Part A-Z',
                'part_desc' => 'Part Z-A',
                'pallet_asc' => 'Pallet A-Z',
                'pallet_desc' => 'Pallet Z-A',
                'pcs_asc' => 'PCS Kecil-Besar',
                'pcs_desc' => 'PCS Besar-Kecil',
                'location_asc' => 'Lokasi A-Z',
                'location_desc' => 'Lokasi Z-A',
                'created_oldest' => 'Paling lama ditambahkan',
                'created_newest' => 'Paling baru ditambahkan',
                'updated_oldest' => 'Paling lama di-update',
                'updated_newest' => 'Terakhir di-update',
            ],
            'pallet' => [
                'pallet_asc' => 'Pallet A-Z',
                'pallet_desc' => 'Pallet Z-A',
                'location_asc' => 'Lokasi A-Z',
                'location_desc' => 'Lokasi Z-A',
                'total_box_asc' => 'Total Box Kecil-Besar',
                'total_box_desc' => 'Total Box Besar-Kecil',
                'total_pcs_asc' => 'Total PCS Kecil-Besar',
                'total_pcs_desc' => 'Total PCS Besar-Kecil',
                'created_oldest' => 'Paling lama ditambahkan',
                'created_newest' => 'Paling baru ditambahkan',
                'updated_oldest' => 'Paling lama di-update',
                'updated_newest' => 'Terakhir di-update',
            ],
            'not_full' => [
                'box_number_asc' => 'No Box A-Z',
                'box_number_desc' => 'No Box Z-A',
                'part_asc' => 'Part A-Z',
                'part_desc' => 'Part Z-A',
                'pallet_asc' => 'Pallet A-Z',
                'pallet_desc' => 'Pallet Z-A',
                'pcs_asc' => 'PCS Kecil-Besar',
                'pcs_desc' => 'PCS Besar-Kecil',
                'location_asc' => 'Lokasi A-Z',
                'location_desc' => 'Lokasi Z-A',
                'created_oldest' => 'Paling lama ditambahkan',
                'created_newest' => 'Paling baru ditambahkan',
                'updated_oldest' => 'Paling lama di-update',
                'updated_newest' => 'Terakhir di-update',
            ],
            default => [
                'part_asc' => 'Part A-Z',
                'part_desc' => 'Part Z-A',
                'total_box_asc' => 'Total Box Kecil-Besar',
                'total_box_desc' => 'Total Box Besar-Kecil',
                'total_pcs_asc' => 'Total PCS Kecil-Besar',
                'total_pcs_desc' => 'Total PCS Besar-Kecil',
                'created_oldest' => 'Paling lama ditambahkan',
                'created_newest' => 'Paling baru ditambahkan',
                'updated_oldest' => 'Paling lama di-update',
                'updated_newest' => 'Terakhir di-update',
            ],
        };
    }

    private function normalizeSortMode(string $viewMode, ?string $sortMode): string
    {
        $sortOptions = $this->getSortOptionsByViewMode($viewMode);
        $defaultSortMode = $this->getDefaultSortMode($viewMode);

        if (!$sortMode || !array_key_exists($sortMode, $sortOptions)) {
            return $defaultSortMode;
        }

        return $sortMode;
    }

    private function toTimestamp($value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $timestamp = strtotime($value);
            return $timestamp !== false ? $timestamp : null;
        }

        return null;
    }

    private function compareNullableString($left, $right, string $direction = 'asc'): int
    {
        $leftValue = mb_strtolower(trim((string) ($left ?? '')));
        $rightValue = mb_strtolower(trim((string) ($right ?? '')));

        if ($leftValue === '' && $rightValue === '') {
            return 0;
        }

        if ($leftValue === '') {
            return 1;
        }

        if ($rightValue === '') {
            return -1;
        }

        $comparison = strcmp($leftValue, $rightValue);

        return $direction === 'desc' ? -$comparison : $comparison;
    }

    private function compareNullableNumber($left, $right, string $direction = 'asc'): int
    {
        $leftIsMissing = !is_numeric($left);
        $rightIsMissing = !is_numeric($right);

        if ($leftIsMissing && $rightIsMissing) {
            return 0;
        }

        if ($leftIsMissing) {
            return 1;
        }

        if ($rightIsMissing) {
            return -1;
        }

        $comparison = ((float) $left) <=> ((float) $right);

        return $direction === 'desc' ? -$comparison : $comparison;
    }

    private function compareNullableTimestamp($left, $right, string $direction = 'asc'): int
    {
        $leftTimestamp = $this->toTimestamp($left);
        $rightTimestamp = $this->toTimestamp($right);

        if ($leftTimestamp === null && $rightTimestamp === null) {
            return 0;
        }

        if ($leftTimestamp === null) {
            return 1;
        }

        if ($rightTimestamp === null) {
            return -1;
        }

        $comparison = $leftTimestamp <=> $rightTimestamp;

        return $direction === 'desc' ? -$comparison : $comparison;
    }

    private function buildPartGroups($items)
    {
        return collect($items)
            ->groupBy('part_number')
            ->map(function ($itemGroup) {
                $sortedByCreated = $itemGroup->sortBy(fn ($item) => $this->toTimestamp($item['created_at'] ?? null));
                $sortedByUpdated = $itemGroup->sortByDesc(fn ($item) => $this->toTimestamp($item['updated_at'] ?? null));

                $totalPcs = $itemGroup->sum('pcs_quantity');
                $totalBox = $itemGroup->sum('box_quantity');

                return [
                    'part_number' => $itemGroup->first()['part_number'],
                    'total_box' => (int) $totalBox,
                    'total_pcs' => $totalPcs,
                    'items' => $itemGroup->sortBy('created_at'),
                    'sort_created_at' => $sortedByCreated->first()['created_at'] ?? null,
                    'sort_updated_at' => $sortedByUpdated->first()['updated_at'] ?? ($sortedByUpdated->first()['created_at'] ?? null),
                ];
            });
    }

    private function buildPalletGroups($items, array $mergedPalletIds = [])
    {
        return collect($items)
            ->groupBy('pallet_id')
            ->map(function ($itemGroup) use ($mergedPalletIds) {
                $firstItem = $itemGroup->first();
                $sortedByCreated = $itemGroup->sortBy(fn ($item) => $this->toTimestamp($item['created_at'] ?? null));
                $sortedByUpdated = $itemGroup->sortByDesc(fn ($item) => $this->toTimestamp($item['updated_at'] ?? null));
                $totalPcs = $itemGroup->sum('pcs_quantity');
                $totalBox = $itemGroup->sum('box_quantity');
                $isMerged = in_array($firstItem['pallet_id'], $mergedPalletIds, true);

                return [
                    'pallet_id' => $firstItem['pallet_id'],
                    'pallet_number' => $firstItem['pallet_number'],
                    'location' => $firstItem['location'] ?? 'Unknown',
                    'total_box' => (int) $totalBox,
                    'total_pcs' => $totalPcs,
                    'items' => $itemGroup,
                    'is_merged' => $isMerged,
                    'sort_created_at' => $sortedByCreated->first()['created_at'] ?? null,
                    'sort_updated_at' => $sortedByUpdated->first()['updated_at'] ?? ($sortedByUpdated->first()['created_at'] ?? null),
                ];
            });
    }

    private function buildNotFullRows($items)
    {
        return collect($items)
            ->map(function ($item) {
                return [
                    'box_id' => $item['box_id'] ?? null,
                    'pallet_id' => $item['pallet_id'],
                    'box_number' => $item['box_number'] ?? '-',
                    'part_number' => $item['part_number'],
                    'pcs_quantity' => $item['pcs_quantity'],
                    'location' => $item['location'] ?? 'Unknown',
                    'pallet_number' => $item['pallet_number'],
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at'] ?? $item['created_at'],
                    'reason' => $item['not_full_reason'] ?? null,
                ];
            })->values();
    }

    private function sortItemsByBoxId($items, string $sortMode = 'box_id_asc')
    {
        return $this->sortRowsForView('box_id', $items, $sortMode);
    }

    private function sortRowsForView(string $viewMode, $rows, string $sortMode)
    {
        $sortedRows = collect($rows)->sort(function ($left, $right) use ($viewMode, $sortMode) {
            return match ($viewMode) {
                'part' => match ($sortMode) {
                    'part_desc' => $this->compareNullableString($right['part_number'] ?? null, $left['part_number'] ?? null),
                    'total_box_asc' => $this->compareNullableNumber($left['total_box'] ?? null, $right['total_box'] ?? null),
                    'total_box_desc' => $this->compareNullableNumber($right['total_box'] ?? null, $left['total_box'] ?? null),
                    'total_pcs_asc' => $this->compareNullableNumber($left['total_pcs'] ?? null, $right['total_pcs'] ?? null),
                    'total_pcs_desc' => $this->compareNullableNumber($right['total_pcs'] ?? null, $left['total_pcs'] ?? null),
                    'created_oldest' => $this->compareNullableTimestamp($left['sort_created_at'] ?? null, $right['sort_created_at'] ?? null),
                    'created_newest' => $this->compareNullableTimestamp($right['sort_created_at'] ?? null, $left['sort_created_at'] ?? null),
                    'updated_oldest' => $this->compareNullableTimestamp($left['sort_updated_at'] ?? null, $right['sort_updated_at'] ?? null),
                    'updated_newest' => $this->compareNullableTimestamp($right['sort_updated_at'] ?? null, $left['sort_updated_at'] ?? null),
                    default => $this->compareNullableString($left['part_number'] ?? null, $right['part_number'] ?? null),
                },
                'box_id' => match ($sortMode) {
                    'box_id_asc' => $this->compareNullableNumber($left['box_id'] ?? null, $right['box_id'] ?? null),
                    'box_id_desc' => $this->compareNullableNumber($right['box_id'] ?? null, $left['box_id'] ?? null),
                    'box_number_asc' => $this->compareNullableString($left['box_number'] ?? null, $right['box_number'] ?? null),
                    'box_number_desc' => $this->compareNullableString($right['box_number'] ?? null, $left['box_number'] ?? null),
                    'part_asc' => $this->compareNullableString($left['part_number'] ?? null, $right['part_number'] ?? null),
                    'part_desc' => $this->compareNullableString($right['part_number'] ?? null, $left['part_number'] ?? null),
                    'pallet_asc' => $this->compareNullableString($left['pallet_number'] ?? null, $right['pallet_number'] ?? null),
                    'pallet_desc' => $this->compareNullableString($right['pallet_number'] ?? null, $left['pallet_number'] ?? null),
                    'pcs_asc' => $this->compareNullableNumber($left['pcs_quantity'] ?? null, $right['pcs_quantity'] ?? null),
                    'pcs_desc' => $this->compareNullableNumber($right['pcs_quantity'] ?? null, $left['pcs_quantity'] ?? null),
                    'location_asc' => $this->compareNullableString($left['location'] ?? null, $right['location'] ?? null),
                    'location_desc' => $this->compareNullableString($right['location'] ?? null, $left['location'] ?? null),
                    'created_oldest' => $this->compareNullableTimestamp($left['created_at'] ?? null, $right['created_at'] ?? null),
                    'created_newest' => $this->compareNullableTimestamp($right['created_at'] ?? null, $left['created_at'] ?? null),
                    'updated_oldest' => $this->compareNullableTimestamp($left['updated_at'] ?? null, $right['updated_at'] ?? null),
                    'updated_newest' => $this->compareNullableTimestamp($right['updated_at'] ?? null, $left['updated_at'] ?? null),
                    default => $this->compareNullableNumber($left['box_id'] ?? null, $right['box_id'] ?? null),
                },
                'pallet' => match ($sortMode) {
                    'pallet_desc' => $this->compareNullableString($right['pallet_number'] ?? null, $left['pallet_number'] ?? null),
                    'location_asc' => $this->compareNullableString($left['location'] ?? null, $right['location'] ?? null),
                    'location_desc' => $this->compareNullableString($right['location'] ?? null, $left['location'] ?? null),
                    'total_box_asc' => $this->compareNullableNumber($left['total_box'] ?? null, $right['total_box'] ?? null),
                    'total_box_desc' => $this->compareNullableNumber($right['total_box'] ?? null, $left['total_box'] ?? null),
                    'total_pcs_asc' => $this->compareNullableNumber($left['total_pcs'] ?? null, $right['total_pcs'] ?? null),
                    'total_pcs_desc' => $this->compareNullableNumber($right['total_pcs'] ?? null, $left['total_pcs'] ?? null),
                    'created_oldest' => $this->compareNullableTimestamp($left['sort_created_at'] ?? null, $right['sort_created_at'] ?? null),
                    'created_newest' => $this->compareNullableTimestamp($right['sort_created_at'] ?? null, $left['sort_created_at'] ?? null),
                    'updated_oldest' => $this->compareNullableTimestamp($left['sort_updated_at'] ?? null, $right['sort_updated_at'] ?? null),
                    'updated_newest' => $this->compareNullableTimestamp($right['sort_updated_at'] ?? null, $left['sort_updated_at'] ?? null),
                    default => $this->compareNullableString($left['pallet_number'] ?? null, $right['pallet_number'] ?? null),
                },
                'not_full' => match ($sortMode) {
                    'box_number_asc' => $this->compareNullableString($left['box_number'] ?? null, $right['box_number'] ?? null),
                    'box_number_desc' => $this->compareNullableString($right['box_number'] ?? null, $left['box_number'] ?? null),
                    'part_asc' => $this->compareNullableString($left['part_number'] ?? null, $right['part_number'] ?? null),
                    'part_desc' => $this->compareNullableString($right['part_number'] ?? null, $left['part_number'] ?? null),
                    'pallet_asc' => $this->compareNullableString($left['pallet_number'] ?? null, $right['pallet_number'] ?? null),
                    'pallet_desc' => $this->compareNullableString($right['pallet_number'] ?? null, $left['pallet_number'] ?? null),
                    'pcs_asc' => $this->compareNullableNumber($left['pcs_quantity'] ?? null, $right['pcs_quantity'] ?? null),
                    'pcs_desc' => $this->compareNullableNumber($right['pcs_quantity'] ?? null, $left['pcs_quantity'] ?? null),
                    'location_asc' => $this->compareNullableString($left['location'] ?? null, $right['location'] ?? null),
                    'location_desc' => $this->compareNullableString($right['location'] ?? null, $left['location'] ?? null),
                    'created_oldest' => $this->compareNullableTimestamp($left['created_at'] ?? null, $right['created_at'] ?? null),
                    'created_newest' => $this->compareNullableTimestamp($right['created_at'] ?? null, $left['created_at'] ?? null),
                    'updated_oldest' => $this->compareNullableTimestamp($left['updated_at'] ?? null, $right['updated_at'] ?? null),
                    'updated_newest' => $this->compareNullableTimestamp($right['updated_at'] ?? null, $left['updated_at'] ?? null),
                    default => $this->compareNullableString($left['box_number'] ?? null, $right['box_number'] ?? null),
                },
                default => 0,
            };
        })->values();

        return $sortedRows;
    }

    private function getCanonicalPalletByActiveBoxId(): array
    {
        return DB::table('pallet_boxes as pb')
            ->join('pallets as p', 'p.id', '=', 'pb.pallet_id')
            ->leftJoin('stock_locations as sl', 'sl.pallet_id', '=', 'p.id')
            ->join('boxes as b', 'b.id', '=', 'pb.box_id')
            ->whereNull('b.deleted_at')
            ->where('b.is_withdrawn', false)
            ->where(function ($q) {
                $q->whereNull('b.expired_status')
                    ->orWhereNotIn('b.expired_status', ['handled', 'expired']);
            })
            ->groupBy('pb.box_id')
            ->select(
                'pb.box_id',
                DB::raw('COALESCE(MIN(CASE WHEN sl.warehouse_location IS NOT NULL AND sl.warehouse_location != "Unknown" THEN pb.pallet_id END), MIN(pb.pallet_id)) as canonical_pallet_id')
            )
            ->pluck('canonical_pallet_id', 'pb.box_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function buildStockItems(?string $search = null)
    {
        $canonicalPalletByBoxId = $this->getCanonicalPalletByActiveBoxId();

        $palletQuery = Pallet::query()
            ->select(['id', 'pallet_number'])
            ->with([
                'stockLocation:id,pallet_id,warehouse_location',
                'items:id,pallet_id,part_number,box_quantity,pcs_quantity,created_at,updated_at',
                'boxes:id,box_number,part_number,pcs_quantity,is_not_full,not_full_reason,is_withdrawn,expired_status,created_at,updated_at',
            ])
            ->where(function ($q) {
                $q->whereHas('stockLocation', function ($q2) {
                    $q2->where('warehouse_location', '!=', 'Unknown');
                })->orWhereHas('boxes', function ($q2) {
                    $q2->whereNull('boxes.deleted_at')
                        ->where('boxes.is_withdrawn', false)
                        ->where(function ($q3) {
                            $q3->whereNull('boxes.expired_status')
                                ->orWhereNotIn('boxes.expired_status', ['handled', 'expired']);
                        });
                });
            });

        $items = [];

        $palletQuery->chunkById(200, function ($pallets) use (&$items, $search, $canonicalPalletByBoxId) {
            foreach ($pallets as $pallet) {
                $location = $pallet->stockLocation->warehouse_location ?? 'Unknown';
                $hasAnyBoxHistory = $pallet->boxes->isNotEmpty();

                // Prefer active boxes as source of truth
                $activeBoxes = $pallet->boxes
                    ->where('is_withdrawn', false)
                    ->reject(fn ($box) => in_array($box->expired_status, ['handled', 'expired'], true))
                    ->filter(function ($box) use ($canonicalPalletByBoxId, $pallet) {
                        $boxId = (int) $box->id;
                        $canonicalPalletId = (int) ($canonicalPalletByBoxId[$boxId] ?? $pallet->id);
                        return $canonicalPalletId === (int) $pallet->id;
                    });

                if ($activeBoxes->isNotEmpty()) {
                    if ($search) {
                        $activeBoxes = $activeBoxes->filter(function ($box) use ($search, $pallet, $location) {
                            return $this->matchesGlobalSearch(
                                $search,
                                $location,
                                $box->part_number,
                                $pallet->pallet_number,
                                $box->box_number,
                                $box->id
                            );
                        });
                    }

                    foreach ($activeBoxes as $box) {
                        $items[] = [
                            'box_id' => $box->id,
                            'pallet_id' => $pallet->id,
                            'pallet_number' => $pallet->pallet_number,
                            'location' => $location,
                            'part_number' => $box->part_number,
                            'box_number' => $box->box_number,
                            'box_quantity' => 1,
                            'pcs_quantity' => (int) $box->pcs_quantity,
                            'created_at' => $box->created_at,
                            'updated_at' => $box->updated_at,
                            'is_not_full' => (bool) $box->is_not_full,
                            'not_full_reason' => $box->not_full_reason,
                        ];
                    }
                } elseif (!$hasAnyBoxHistory) {
                    // Fallback only for legacy pallets that truly have no box history.
                    $legacyItems = $pallet->items->filter(function ($item) {
                        return $item->pcs_quantity > 0 || $item->box_quantity > 0;
                    });

                    if ($search) {
                        $legacyItems = $legacyItems->filter(function ($item) use ($search, $pallet, $location) {
                            return $this->matchesGlobalSearch(
                                $search,
                                $location,
                                $item->part_number,
                                $pallet->pallet_number
                            );
                        });
                    }

                    foreach ($legacyItems as $item) {
                        $items[] = [
                            'box_id' => null,
                            'pallet_id' => $pallet->id,
                            'pallet_number' => $pallet->pallet_number,
                            'location' => $location,
                            'part_number' => $item->part_number,
                            'box_number' => null,
                            'box_quantity' => (int) $item->box_quantity,
                            'pcs_quantity' => (int) $item->pcs_quantity,
                            'created_at' => $item->created_at,
                            'updated_at' => $item->updated_at,
                            'is_not_full' => false,
                            'not_full_reason' => null,
                        ];
                    }
                }
            }
        });

        return collect($items)->sortBy('created_at');
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $viewMode = $request->input('view_mode', 'part'); // Default view by part
        $sortMode = $this->normalizeSortMode($viewMode, $request->input('sort'));
        $sortOptions = $this->getSortOptionsByViewMode($viewMode);

        $items = $this->buildStockItems($search);
        $directBoxTarget = filled($search) ? $this->resolveDirectBoxTarget((string) $search, $items) : null;
        if ($viewMode === 'not_full') {
            $items = $items->filter(fn ($item) => !empty($item['is_not_full']))->values();
        }
        // Calculate total pallets from the filtered items
        $totalPallets = $items->pluck('pallet_id')->unique()->count();

        // Data for "By Part" view
        $groupedByPart = collect();
        if ($viewMode === 'part') {
            $groupedByPart = $this->sortRowsForView('part', $this->buildPartGroups($items), $sortMode);
        }

        $groupedByBoxId = collect();
        if ($viewMode === 'box_id') {
            $groupedByBoxId = $this->sortItemsByBoxId($items, $sortMode);
        }

        // Data for "By Pallet" view
        $groupedByPallet = collect();
        if ($viewMode === 'pallet') {
            $palletIds = $items->pluck('pallet_id')->unique()->values();
            $mergedPalletIds = AuditLog::where('model', 'Pallet')
                ->where('type', 'pallet_merged')
                ->whereIn('model_id', $palletIds)
                ->pluck('model_id')
                ->unique()
                ->toArray();

            $groupedByPallet = $this->sortRowsForView('pallet', $this->buildPalletGroups($items, $mergedPalletIds), $sortMode);
        }

        $notFullBoxes = collect();
        if ($viewMode === 'not_full') {
            $notFullBoxes = $this->sortRowsForView('not_full', $this->buildNotFullRows($items), $sortMode);
        }
        
        // Calculate totals for summary cards regardless of view mode
        // We use the raw items collection to calculate these compatible with both views
        $summaryTotalBox = $items->sum('box_quantity');
        $summaryTotalPcs = $items->sum('pcs_quantity');
        $summaryTotalParts = $items->pluck('part_number')->unique()->count();
        $allParts = $items->pluck('part_number')->filter()->unique()->values();
        $allPallets = $items->pluck('pallet_number')->filter()->unique()->values();
        $allBoxNumbers = $items->pluck('box_number')->filter()->unique()->values();
        $allLocations = $items->pluck('location')->filter()->unique()->values();
        $allSearchTerms = $allParts
            ->concat($allPallets)
            ->concat($allBoxNumbers)
            ->concat($allLocations)
            ->unique()
            ->sort(function ($left, $right) {
                return strcasecmp((string) $left, (string) $right);
            })
            ->values();
        $masterPartNumbers = PartSetting::query()
            ->orderBy('part_number')
            ->pluck('part_number')
            ->values();

        return view('shared.stock-view.index', compact(
            'groupedByPart', 
            'groupedByBoxId',
            'groupedByPallet',
            'notFullBoxes',
            'search', 
            'viewMode', 
            'totalPallets',
            'summaryTotalBox',
            'summaryTotalPcs',
            'summaryTotalParts',
            'allParts',
            'allPallets',
            'allBoxNumbers',
            'allLocations',
            'allSearchTerms',
            'directBoxTarget',
            'masterPartNumbers'
            , 'sortMode',
            'sortOptions'
        ));
    }

    // API: Get stock grouped by part number
    public function apiGetStockByPart()
    {
        $items = $this->buildStockItems();

        $groupedByPart = $items->groupBy('part_number')->map(function ($itemGroup) {
            $totalPcs = $itemGroup->sum('pcs_quantity');
            $totalBox = $itemGroup->sum('box_quantity');

            return [
                'part_number' => $itemGroup->first()['part_number'],
                'total_box' => (int) $totalBox,
                'total_pcs' => $totalPcs,
            ];
        })->sortBy(function ($row) {
            return mb_strtolower((string) ($row['part_number'] ?? ''));
        }, SORT_STRING)->values();

        return response()->json($groupedByPart);
    }

    // API: Get detailed information for a specific part number
    public function apiGetPartDetail($partNumber)
    {
        $items = $this->buildStockItems()
            ->filter(function ($item) use ($partNumber) {
                return $item['part_number'] === $partNumber;
            })
            ->values();

        if ($items->isEmpty()) {
            return response()->json(['error' => 'Part not found'], 404);
        }

        $totalPcs = $items->sum('pcs_quantity');
        $totalBox = $items->sum('box_quantity');

        $palletDetails = $items->map(function ($item) {
            return [
                'box_id' => $item['box_id'] ?? null,
                'pallet_id' => $item['pallet_id'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'box_number' => $item['box_number'] ?? null,
                'pallet_number' => $item['pallet_number'],
                'box_quantity' => $item['box_quantity'],
                'pcs_quantity' => $item['pcs_quantity'],
                'location' => $item['location'] ?? 'Unknown',
                'created_at' => $item['created_at']->format('d M Y H:i'),
                'stored_at_raw' => optional($item['created_at'])->format('Y-m-d H:i:s'),
                'is_not_full' => (bool) ($item['is_not_full'] ?? false),
                'not_full_reason' => $item['not_full_reason'] ?? null,
            ];
        });


        return response()->json([
            'part_number' => $partNumber,
            'total_box' => (int)$totalBox,
            'total_pcs' => $totalPcs,
            'pallet_count' => $items->pluck('pallet_id')->unique()->count(),
            'pallets' => $palletDetails,
        ]);
    }

    // API: Get detailed information for a specific pallet
    public function apiGetPalletDetail($palletId)
    {
        $pallet = Pallet::with(['items', 'boxes', 'stockLocation'])->find($palletId);

        if (!$pallet) {
            return response()->json(['error' => 'Pallet not found'], 404);
        }

        // Priority 1: Use Boxes (Source of Truth for FIFO created_at)
        if ($pallet->boxes->count() > 0) {
            $boxIds = $pallet->boxes
                ->where('is_withdrawn', false)
                ->reject(fn ($box) => in_array($box->expired_status, ['handled', 'expired'], true))
                ->pluck('id');
            $originLogs = AuditLog::where('type', 'box_pallet_moved')
                ->where('model', 'Box')
                ->whereIn('model_id', $boxIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('model_id')
                ->map(function ($logs) {
                    return $logs->first();
                });

            $items = $pallet->boxes
                ->where('is_withdrawn', false)
                ->reject(fn ($box) => in_array($box->expired_status, ['handled', 'expired'], true))
                ->map(function ($box) use ($originLogs) {
                $log = $originLogs->get($box->id);
                $origin = $log?->getOldValuesArray()['from_pallet'] ?? null;

                return [
                    'box_id' => $box->id,
                    'part_number' => $box->part_number,
                    'box_number' => $box->box_number,
                    'box_quantity' => 1,
                    'pcs_quantity' => (int)$box->pcs_quantity,
                    'created_at' => $box->created_at->format('d M Y H:i'),
                    'stored_at_raw' => $box->created_at->format('Y-m-d H:i:s'),
                    'origin_pallet' => $origin,
                    'is_not_full' => (bool) $box->is_not_full,
                    'not_full_reason' => $box->not_full_reason,
                ];
            });
        } else {
            // Priority 2: Fallback using PalletItem (Legacy / No Box Data)
            $items = $pallet->items->where(function ($q) {
                 return $q->pcs_quantity > 0 || $q->box_quantity > 0;
            })->map(function ($item) {
                return [
                    'box_id' => null,
                    'part_number' => $item->part_number,
                    'box_number' => '-',
                    'box_quantity' => (int)$item->box_quantity,
                    'pcs_quantity' => (int)$item->pcs_quantity,
                    'created_at' => $item->created_at->format('d M Y H:i'),
                    'stored_at_raw' => $item->created_at->format('Y-m-d H:i:s'),
                    'origin_pallet' => null,
                    'is_not_full' => false,
                    'not_full_reason' => null,
                ];
            });
        }

        return response()->json([
            'pallet_number' => $pallet->pallet_number,
            'location' => $pallet->stockLocation->warehouse_location ?? 'Unknown',
            'items' => $items->values() 
        ]);
    }

    public function updateBox(Request $request, $boxId)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['admin_warehouse', 'admin'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'part_number' => [
                'required',
                'string',
                'max:100',
                function ($attribute, $value, $fail) {
                    if (!$this->findExactPartSetting($value)) {
                        $fail('No Part tidak ditemukan di Master Part.');
                    }
                },
            ],
            'pcs_quantity' => 'required|integer|min:1',
            'stored_at' => 'required|date',
            'reason' => 'required|string|min:3|max:500',
        ]);

        $newPartNumber = trim((string) $validated['part_number']);
        $newPcsQuantity = (int) $validated['pcs_quantity'];
        $newStoredAt = Carbon::parse($validated['stored_at']);

        $box = Box::with('pallets')->findOrFail($boxId);

        if ($box->is_withdrawn || in_array($box->expired_status, ['handled', 'expired'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Box tidak bisa diedit karena statusnya tidak aktif.'
            ], 422);
        }

        $oldPartNumber = (string) $box->part_number;
        $oldPcsQuantity = (int) $box->pcs_quantity;
        $isSamePart = $oldPartNumber === $newPartNumber;
        $isSamePcs = $oldPcsQuantity === $newPcsQuantity;
        $isSameStoredAtMinute = optional($box->created_at)->format('Y-m-d H:i') === $newStoredAt->format('Y-m-d H:i');

        if ($isSamePart && $isSamePcs && $isSameStoredAtMinute) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada perubahan data box. Aksi tidak diproses.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $box = Box::whereKey($boxId)->lockForUpdate()->firstOrFail();

            if ($box->is_withdrawn || in_array($box->expired_status, ['handled', 'expired'], true)) {
                throw new \RuntimeException('Box tidak bisa diedit karena statusnya tidak aktif.');
            }

            if ($this->isBoxLockedByActivePicking((int) $box->id)) {
                throw new \RuntimeException('Box sedang digunakan dalam sesi picking dan tidak dapat diedit.');
            }

            $pallets = $box->pallets()->lockForUpdate()->get();

            foreach ($pallets as $pallet) {
                $oldItem = PalletItem::where('pallet_id', $pallet->id)
                    ->where('part_number', $oldPartNumber)
                    ->lockForUpdate()
                    ->first();

                if ($oldPartNumber === $newPartNumber) {
                    if ($oldItem) {
                        $oldItem->pcs_quantity = max(0, (int) $oldItem->pcs_quantity - $oldPcsQuantity + $newPcsQuantity);
                        $oldItem->save();
                    }
                } else {
                    if ($oldItem) {
                        $oldItem->box_quantity = max(0, (int) $oldItem->box_quantity - 1);
                        $oldItem->pcs_quantity = max(0, (int) $oldItem->pcs_quantity - $oldPcsQuantity);
                        $oldItem->save();
                    }

                    $newItem = PalletItem::firstOrCreate(
                        [
                            'pallet_id' => $pallet->id,
                            'part_number' => $newPartNumber,
                        ],
                        [
                            'box_quantity' => 0,
                            'pcs_quantity' => 0,
                        ]
                    );

                    $newItem = PalletItem::whereKey($newItem->id)->lockForUpdate()->firstOrFail();

                    $newItem->box_quantity = (int) $newItem->box_quantity + 1;
                    $newItem->pcs_quantity = (int) $newItem->pcs_quantity + $newPcsQuantity;
                    $newItem->save();
                }
            }

            $oldValues = [
                'part_number' => $box->part_number,
                'pcs_quantity' => (int) $box->pcs_quantity,
                'stored_at' => optional($box->created_at)->format('Y-m-d H:i:s'),
            ];

            $box->part_number = $newPartNumber;
            $box->pcs_quantity = $newPcsQuantity;
            $box->qr_code = $box->box_number . '|' . $newPartNumber . '|' . $newPcsQuantity;
            $box->created_at = $newStoredAt;
            $box->save();

            $this->syncStockInputHeadersForBox((int) $box->id);

            $newValues = [
                'part_number' => $box->part_number,
                'pcs_quantity' => (int) $box->pcs_quantity,
                'stored_at' => optional($box->created_at)->format('Y-m-d H:i:s'),
                'reason' => $validated['reason'],
            ];

            AuditService::log(
                'other',
                'box_updated_by_admin_warehouse',
                'Box',
                $box->id,
                $oldValues,
                $newValues,
                $validated['reason']
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui box: ' . $e->getMessage(),
            ], $e instanceof \RuntimeException ? 422 : 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail box berhasil diperbarui.',
            'box' => [
                'id' => $box->id,
                'box_number' => $box->box_number,
                'part_number' => $box->part_number,
                'pcs_quantity' => (int) $box->pcs_quantity,
                'stored_at' => optional($box->created_at)->format('d M Y H:i'),
            ],
        ]);
    }

    public function boxHistory($boxId)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['warehouse_operator', 'ppc', 'admin_warehouse', 'supervisi', 'admin'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $box = Box::findOrFail($boxId);

        $logs = AuditLog::query()
            ->with('user')
            ->where('type', 'other')
            ->where('model', 'Box')
            ->where('action', 'box_updated_by_admin_warehouse')
            ->where('model_id', $box->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'old_values' => $log->old_values ?? [],
                'new_values' => $log->new_values ?? [],
                'user_name' => $log->user?->name ?? 'System',
                'created_at' => optional($log->created_at)->format('d M Y H:i:s'),
            ])
            ->values();

        return response()->json([
            'box_id' => $box->id,
            'box_number' => $box->box_number,
            'history' => $logs,
        ]);
    }

    public function deleteBox(Request $request, $boxId)
    {
        $user = Auth::user();
        if (!$this->canDeleteStock($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();

        try {
            $box = Box::whereKey($boxId)->lockForUpdate()->firstOrFail();
            if ($this->isBoxLockedByActivePicking((int) $box->id)) {
                throw new \RuntimeException('Box sedang digunakan dalam sesi picking dan tidak dapat dihapus.');
            }

            $pallets = $box->pallets()->lockForUpdate()->get();
            $affectedPalletIds = $pallets->pluck('id')->map(fn ($id) => (int) $id)->values();

            $oldValues = [
                'box_id' => $box->id,
                'box_number' => $box->box_number,
                'part_number' => $box->part_number,
                'pcs_quantity' => (int) $box->pcs_quantity,
                'stored_at' => optional($box->created_at)->format('Y-m-d H:i:s'),
                'pallets' => $pallets->map(fn ($p) => [
                    'id' => $p->id,
                    'pallet_number' => $p->pallet_number,
                ])->values()->all(),
            ];

            foreach ($pallets as $pallet) {
                $item = PalletItem::where('pallet_id', $pallet->id)
                    ->where('part_number', $box->part_number)
                    ->lockForUpdate()
                    ->first();

                if ($item) {
                    $item->box_quantity = max(0, (int) $item->box_quantity - 1);
                    $item->pcs_quantity = max(0, (int) $item->pcs_quantity - (int) $box->pcs_quantity);

                    if ((int) $item->box_quantity === 0 && (int) $item->pcs_quantity === 0) {
                        $item->delete();
                    } else {
                        $item->save();
                    }
                }
            }

            // Keep historical stock input mappings intact by using soft delete.
            $box->delete();

            $autoDeletedPallets = collect();

            foreach ($affectedPalletIds as $palletId) {
                $pallet = Pallet::whereKey($palletId)->lockForUpdate()->first();
                if (!$pallet) {
                    continue;
                }

                $remainingActiveBoxes = (int) DB::table('pallet_boxes')
                    ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
                    ->where('pallet_boxes.pallet_id', $palletId)
                    ->whereNull('boxes.deleted_at')
                    ->where('boxes.is_withdrawn', false)
                    ->where(function ($q) { $q->whereNull('boxes.expired_status')->orWhereNotIn('boxes.expired_status', ['handled', 'expired']); })
                    ->lockForUpdate()
                    ->count();

                if ($remainingActiveBoxes === 0) {
                    $palletNumber = (string) $pallet->pallet_number;

                    \App\Models\MasterLocation::where('current_pallet_id', $palletId)
                        ->update([
                            'is_occupied' => false,
                            'current_pallet_id' => null,
                            'updated_at' => now(),
                        ]);

                    $pallet->stockLocation()->delete();
                    $pallet->delete();
                    $autoDeletedPallets->push([
                        'id' => $palletId,
                        'pallet_number' => $palletNumber,
                    ]);

                    \App\Services\AuditService::log(
                        'other',
                        'pallet_deleted_by_stock_view',
                        'Pallet',
                        $palletId,
                        [
                            'pallet_id' => $palletId,
                            'pallet_number' => $palletNumber,
                            'reason' => 'auto_deleted_after_last_box_removed',
                        ],
                        [
                            'deleted' => true,
                            'deleted_by_role' => $user->role,
                            'trigger' => 'delete_box',
                        ],
                        sprintf('Pallet %s terhapus otomatis karena box terakhir dihapus', $palletNumber)
                    );
                    continue;
                }

                // Removed wipe-and-rebuild logic to preserve legacy stock.
                // PalletItem updates are handled incrementally above.
            }

            AuditService::log(
                'other',
                'box_deleted_by_stock_view',
                'Box',
                (int) $boxId,
                $oldValues,
                [
                    'deleted' => true,
                    'deleted_by_role' => $user->role,
                    'auto_deleted_pallets' => $autoDeletedPallets->all(),
                ],
                sprintf('Box %s dihapus dari stok aktif (soft delete) melalui stock view', $oldValues['box_number'] ?? (string) $boxId)
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus box: ' . $e->getMessage(),
            ], $e instanceof \RuntimeException ? 422 : 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Box berhasil dihapus dari stok aktif dan stok telah diperbarui.',
        ]);
    }

    public function deletePallet(Request $request, $palletId)
    {
        $user = Auth::user();
        if (!$this->canDeleteStock($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();

        try {
            $pallet = Pallet::with(['stockLocation', 'boxes', 'items'])->whereKey($palletId)->lockForUpdate()->firstOrFail();
            $attachedBoxes = $pallet->boxes;

            $lockedBox = $attachedBoxes->first(
                fn ($box) => $this->isBoxLockedByActivePicking((int) $box->id)
            );
            if ($lockedBox) {
                throw new \RuntimeException(
                    "Box {$lockedBox->box_number} sedang digunakan dalam sesi picking. Pallet tidak dapat dihapus."
                );
            }

            $oldValues = [
                'pallet_id' => $pallet->id,
                'pallet_number' => $pallet->pallet_number,
                'location' => $pallet->stockLocation->warehouse_location ?? 'Unknown',
                'items' => $attachedBoxes->map(fn ($box) => [
                    'box_id' => $box->id,
                    'box_number' => $box->box_number,
                    'part_number' => $box->part_number,
                    'pcs_quantity' => (int) $box->pcs_quantity,
                ])->values()->all(),
            ];

            $softDeletedBoxCount = 0;
            $detachedSharedBoxCount = 0;

            foreach ($attachedBoxes as $box) {
                $box = Box::whereKey($box->id)->lockForUpdate()->first();
                if (!$box) {
                    continue;
                }

                $linkedPalletCount = (int) DB::table('pallet_boxes')
                    ->where('box_id', $box->id)
                    ->lockForUpdate()
                    ->count();

                if ($linkedPalletCount <= 1) {
                    $box->delete();
                    $softDeletedBoxCount++;
                } else {
                    DB::table('pallet_boxes')
                        ->where('pallet_id', $pallet->id)
                        ->where('box_id', $box->id)
                        ->delete();
                    $detachedSharedBoxCount++;
                }
            }

            MasterLocation::where('current_pallet_id', $pallet->id)
                ->update([
                    'is_occupied' => false,
                    'current_pallet_id' => null,
                    'updated_at' => now(),
                ]);

            if ($pallet->stockLocation) {
                $pallet->stockLocation->delete();
            }

            $pallet->delete();

            AuditService::log(
                'other',
                'pallet_deleted_by_stock_view',
                'Pallet',
                (int) $palletId,
                $oldValues,
                [
                    'deleted' => true,
                    'soft_deleted_box_count' => $softDeletedBoxCount,
                    'hard_deleted_box_count' => 0,
                    'detached_shared_box_count' => $detachedSharedBoxCount,
                    'deleted_by_role' => $user->role,
                ],
                sprintf('Pallet %s dihapus dari stok aktif melalui stock view', $oldValues['pallet_number'] ?? (string) $palletId)
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pallet: ' . $e->getMessage(),
            ], $e instanceof \RuntimeException ? 422 : 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pallet berhasil dihapus dari stok aktif.',
        ]);
    }

    /**
     * Export stock view by part to Excel
     */
    public function exportByPart(Request $request)
    {
        $search = $request->query('search');
        $stocks = $this->buildStockItems($search);
        $sortMode = $this->normalizeSortMode('part', $request->query('sort'));

        return Excel::download(
            new StockViewByPartExport($this->buildPartGroups($stocks), $sortMode),
            'stock_by_part_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Export stock view by box id to Excel
     */
    public function exportByBoxId(Request $request)
    {
        $search = $request->query('search');
        $sortMode = $this->normalizeSortMode('box_id', $request->query('sort'));
        $stocks = $this->buildStockItems($search);

        return Excel::download(
            new StockViewByBoxIdExport($stocks, $sortMode),
            'stock_by_box_id_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Export stock view by pallet to Excel
     */
    public function exportByPallet(Request $request)
    {
        $search = $request->query('search');
        $stocks = $this->buildStockItems($search);
        $sortMode = $this->normalizeSortMode('pallet', $request->query('sort'));
        
        $mergedPalletIds = AuditLog::where('model', 'Pallet')
            ->where('type', 'pallet_merged')
            ->whereIn('model_id', $stocks->pluck('pallet_id')->unique()->values())
            ->pluck('model_id')
            ->unique()
            ->toArray();

        return Excel::download(
            new StockViewByPalletExport($this->buildPalletGroups($stocks, $mergedPalletIds), $sortMode),
            'stock_by_pallet_' . now()->format('Ymd_His') . '.xlsx'
        );
    }
}
