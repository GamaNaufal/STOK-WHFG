<?php

namespace App\Http\Controllers;

use App\Models\ExpiredBoxReport;
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
            ->orderBy('stock_in.stored_at', 'asc')
            ->get();

        $warningBoxes = $boxesQuery->filter(fn ($row) => $row->expired_status === 'warning');
        $expiredBoxes = $boxesQuery->filter(fn ($row) => $row->expired_status === 'expired');

        $handledHistory = collect();
        if (Schema::hasTable('expired_box_reports')) {
            $handledHistory = ExpiredBoxReport::with('handler')
                ->where('status', 'handled')
                ->orderByDesc('handled_at')
                ->paginate(50);
        }

        return view('warehouse.expired-box.index', compact('warningBoxes', 'expiredBoxes', 'handledHistory'));
    }

    public function handle(Request $request, int $boxId, ExpiredBoxService $service): RedirectResponse
    {
        $service->handleBox($boxId, $request->user()->id);

        return redirect()->route('expired-box.index')
            ->with('success', 'Box berhasil ditandai sebagai handled.');
    }
}
