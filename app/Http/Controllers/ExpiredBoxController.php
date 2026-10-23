<?php

namespace App\Http\Controllers;

use App\Models\ExpiredBoxReport;
use App\Models\MasterLocation;
use App\Services\ExpiredBoxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ExpiredBoxController extends Controller
{
    public function index(Request $request, ExpiredBoxService $service)
    {
        $service->syncStatuses();

        $boxesQuery = $service->getExpirableBoxesQuery()
            ->whereIn('boxes.expired_status', ['warning', 'expired'])
            ->orderBy('stored_at', 'asc')
            ->get();

        $warningBoxes = $boxesQuery->filter(fn ($row) => $row->expired_status === 'warning');
        $expiredBoxes = $boxesQuery->filter(fn ($row) => $row->expired_status === 'expired');

        $handledHistory = collect();
        if (Schema::hasTable('expired_box_reports')) {
            $handledHistory = ExpiredBoxReport::with('handler')
                ->where('status', 'handled')
                ->orderByDesc('handled_at')
                ->paginate(15);
        }

        $availableLocations = MasterLocation::available()->orderBy('code')->get(['id', 'code']);

        return view('warehouse.expired-box.index', compact('warningBoxes', 'expiredBoxes', 'handledHistory', 'availableLocations'));
    }

    public function handle(Request $request, int $boxId, ExpiredBoxService $service): RedirectResponse
    {
        $validated = $request->validate(['location_id' => ['nullable', 'integer', 'exists:master_locations,id']]);
        try {
            $service->handleBox($boxId, $request->user()->id, isset($validated['location_id']) ? (int) $validated['location_id'] : null);
        } catch (\RuntimeException $e) {
            return redirect()->route('expired-box.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('expired-box.index')
            ->with('success', 'Box berhasil diaktifkan kembali selama 1 bulan. Setelah masa aktif habis, box kembali expired dan tidak dapat masuk FIFO.');
    }
}
