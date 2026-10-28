<?php

namespace App\Http\Controllers;

use App\Models\ExpiredBoxHandling;
use App\Models\ExpiredBoxReport;
use App\Models\ExpiredBoxTransaction;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Services\ExpiredBoxBulkService;
use App\Services\ExpiredBoxHandlingService;
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
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $search = '%'.(string) $request->input('search').'%';
                    $q->where('boxes.box_number', 'like', $search)->orWhere('boxes.part_number', 'like', $search)
                        ->orWhere('pallets.pallet_number', 'like', $search)->orWhere('stock_locations.warehouse_location', 'like', $search);
                });
            })
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

        $transactions = ExpiredBoxTransaction::with(['handlings' => fn ($q) => $q->select('id', 'transaction_id', 'status', 'pcs_quantity')])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = (string) $request->input('search');
                $query->where(function ($q) use ($search) {
                    if (preg_match('/^EXP-\d{8}-(\d+)$/i', $search, $matches)) {
                        $q->where('id', (int) $matches[1]);
                    }
                    $q->orWhereHas('handlings', fn ($items) => $items->where('source_box_number', 'like', '%'.$search.'%')
                        ->orWhere('replacement_box_number', 'like', '%'.$search.'%')
                        ->orWhere('part_number', 'like', '%'.$search.'%'));
                });
            })->withExists(['handlings as is_open' => fn ($q) => $q->whereNotIn('status', ['completed', 'rejected'])])
            ->orderByDesc('is_open')->latest('id')->paginate(15, ['*'], 'transaction_page')->withQueryString();

        return view('warehouse.expired-box.index', compact('warningBoxes', 'expiredBoxes', 'handledHistory', 'transactions'));
    }

    public function handle(Request $request, int $boxId, ExpiredBoxService $service): RedirectResponse
    {
        try {
            $handling = app(ExpiredBoxHandlingService::class)->start($boxId, $request->user()->id);
        } catch (\RuntimeException $e) {
            return redirect()->route('expired-box.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('expired-box.transactions.show', $handling->transaction_id)
            ->with('success', 'Penyerahan ke QA tercatat. Box belum tersedia untuk delivery.');
    }

    public function show(ExpiredBoxHandling $handling)
    {
        $handling->loadMissing('transaction');
        $users = \App\Models\User::whereIn('id', collect(['sent_qa_by', 'qa_decided_by', 'sent_packing_by', 'received_by'])
            ->map(fn ($field) => $handling->$field)->filter())->pluck('name', 'id');
        $originHandling = ExpiredBoxHandling::where('replacement_box_id', $handling->source_box_id)->first();
        $nextHandling = $handling->replacement_box_id
            ? ExpiredBoxHandling::where('source_box_id', $handling->replacement_box_id)->first() : null;

        return view('warehouse.expired-box.show', compact('handling', 'users', 'originHandling', 'nextHandling'));
    }

    public function qa(Request $request, ExpiredBoxHandling $handling, ExpiredBoxHandlingService $service)
    {
        if ($redirect = $this->groupRedirect($handling)) {
            return $redirect;
        }
        $data = $request->validate([
            'decision' => ['required', 'in:passed,rejected'],
            'notes' => ['nullable', 'string', 'max:2000', 'required_if:decision,rejected'],
            'confirmed' => ['accepted'],
        ]);

        return $this->perform($handling, fn () => $service->decideQa($handling->id, $data['decision'], $data['notes'] ?? null, $request->user()->id));
    }

    public function packing(Request $request, ExpiredBoxHandling $handling, ExpiredBoxHandlingService $service)
    {
        if ($redirect = $this->groupRedirect($handling)) {
            return $redirect;
        }
        $request->validate(['confirmed' => ['accepted']]);

        return $this->perform($handling, fn () => $service->sendToPacking($handling->id, $request->user()->id));
    }

    public function receive(Request $request, ExpiredBoxHandling $handling, ExpiredBoxHandlingService $service)
    {
        if ($redirect = $this->groupRedirect($handling)) {
            return $redirect;
        }
        $data = $request->validate([
            'box_number' => ['required', 'string', 'regex:/^\d{8}$/'],
            'part_number' => ['required', 'string', 'max:255'],
            'pcs_quantity' => ['required', 'integer', 'min:1'],
            'label_issued_at' => ['required', 'date', 'before_or_equal:now'],
            'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            'location_id' => ['nullable', 'required_without:pallet_id', 'integer', 'exists:master_locations,id,scope,domestic'],
            'confirmed' => ['accepted'],
        ]);

        return $this->perform($handling, fn () => $service->receive($handling->id, $data, $request->user()->id));
    }

    public function print(ExpiredBoxHandling $handling)
    {
        if ($handling->transaction->handlings()->count() > 1) {
            return redirect()->route('expired-box.transactions.print', $handling->transaction_id);
        }
        abort_unless(in_array($handling->status, ['awaiting_packing', 'at_packing', 'completed']), 409, 'Pengantar hanya tersedia setelah lolos QA.');

        return view('warehouse.expired-box.print', compact('handling'));
    }

    public function trace(ExpiredBoxHandling $handling)
    {
        return response()->json($handling->toArray(), 200, [
            'Content-Disposition' => 'attachment; filename="'.$handling->reference.'-riwayat.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function bulkReview(Request $request, ExpiredBoxService $service)
    {
        $data = $request->validate([
            'action' => ['required', 'in:start,qa,packing,receive,print,result'],
            'ids' => ['required', 'array', 'min:1', 'max:'.ExpiredBoxBulkService::LIMIT],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $action = $data['action'];
        if ($action === 'start') {
            $service->syncStatuses();
            $records = $service->getExpirableBoxesQuery()->whereIn('boxes.id', $data['ids'])
                ->where('boxes.expired_status', 'expired')->orderBy('boxes.id')->get();
        } else {
            $query = ExpiredBoxHandling::whereIn('id', $data['ids'])->orderBy('id');
            $states = match ($action) {
                'qa' => ['at_qa'], 'packing' => ['awaiting_packing'], 'receive' => ['at_packing'],
                'print' => ['awaiting_packing', 'at_packing', 'completed'], default => null,
            };
            if ($states) {
                $query->whereIn('status', $states);
            }
            $records = $query->get();
        }
        if ($records->count() !== count($data['ids'])) {
            return redirect()->route('expired-box.index')->with('error', 'Pilihan memuat box yang tidak ditemukan atau statusnya tidak sesuai tindakan. Pilih ulang box pada tahap yang sama.');
        }
        if ($action === 'print' || $action === 'result') {
            if ($records->pluck('transaction_id')->unique()->count() !== 1) {
                return redirect()->route('expired-box.index')->with('error', 'Buka satu transaksi untuk melanjutkan proses.');
            }

            return redirect()->route($action === 'print' ? 'expired-box.transactions.print' : 'expired-box.transactions.show', $records->first()->transaction_id);
        }
        if ($action !== 'start') {
            if ($records->pluck('transaction_id')->unique()->count() !== 1) {
                return redirect()->route('expired-box.index')->with('error', 'Buka satu transaksi untuk melanjutkan proses.');
            }

            return redirect()->route('expired-box.transactions.show', $records->first()->transaction_id);
        }
        $availableLocations = MasterLocation::available()->orderBy('code')->get(['id', 'code']);
        $pallets = Pallet::with('stockLocation')->whereHas('stockLocation')->orderBy('pallet_number')->get();

        return view('warehouse.expired-box.bulk-review', compact('action', 'records', 'availableLocations', 'pallets'));
    }

    public function bulkStore(Request $request, ExpiredBoxBulkService $service): RedirectResponse
    {
        try {
            $ids = $service->execute($request->all(), $request->user()->id);
        } catch (\Illuminate\Database\QueryException $e) {
            if (! in_array((string) $e->getCode(), ['23000', '23505', '40001'])) {
                throw $e;
            }

            return back()->withInput()->with('error', 'ID atau lokasi sedang digunakan transaksi lain. Seluruh pilihan belum disimpan; periksa dan coba kembali.');
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('expired-box.transactions.show', ExpiredBoxHandling::findOrFail($ids[0])->transaction_id)
            ->with('success', count($ids).' box tersimpan dalam satu transaksi.');
    }

    private function groupRedirect(ExpiredBoxHandling $handling): ?RedirectResponse
    {
        if ($handling->transaction->handlings()->count() > 1) {
            return redirect()->route('expired-box.transactions.show', $handling->transaction_id)
                ->with('error', 'Box ini bagian dari satu transaksi. Lanjutkan seluruh box melalui halaman transaksi.');
        }

        return null;
    }

    private function perform(ExpiredBoxHandling $handling, callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (\Illuminate\Database\QueryException $e) {
            if (! in_array((string) $e->getCode(), ['23000', '23505', '40001'])) {
                throw $e;
            }

            return back()->withInput()->with('error', 'Data sedang berubah atau ID/lokasi sudah digunakan. Muat ulang dan periksa kembali.');
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('expired-box.show', $handling)->with('success', 'Penanganan berhasil diperbarui.');
    }
}
