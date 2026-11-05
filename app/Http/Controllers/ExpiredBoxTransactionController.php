<?php

namespace App\Http\Controllers;

use App\Models\ExpiredBoxTransaction;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Services\ExpiredBoxBulkService;
use App\Services\ExpiredBoxPackingReceiptService;
use App\Services\ExpiredBoxService;
use Illuminate\Http\Request;

class ExpiredBoxTransactionController extends Controller
{
    public function create(ExpiredBoxService $service)
    {
        $service->syncStatuses();
        $expiredBoxes = $service->getExpirableBoxesQuery()->where('boxes.expired_status', 'expired')
            ->addSelect(['boxes.is_not_full', 'boxes.assigned_delivery_order_id as delivery_id'])
            ->orderBy('boxes.id')->get();

        return view('warehouse.expired-box.create', compact('expiredBoxes'));
    }

    public function show(ExpiredBoxTransaction $transaction)
    {
        $transaction->load(['handlings', 'receipts.receiver', 'receipts.handlings']);
        $action = $transaction->next_action;
        $records = $transaction->handlings->where('status', $transaction->stage)->values();
        $availableLocations = $action === 'receive' ? MasterLocation::available()->orderBy('code')->get(['id', 'code']) : collect();
        $pallets = $action === 'receive' ? Pallet::with('stockLocation')->whereHas('stockLocation')->orderBy('pallet_number')->get() : collect();

        return view('warehouse.expired-box.transaction', compact('transaction', 'action', 'records', 'availableLocations', 'pallets'));
    }

    public function scan(Request $request, ExpiredBoxTransaction $transaction, ExpiredBoxPackingReceiptService $service)
    {
        $data = $request->validate([
            'box_number' => ['required', 'string', 'regex:/^[0-9]{8}$/D'],
            'group_key' => ['required', 'string', 'size:64'],
        ]);
        $pending = $transaction->handlings()->where('status', 'at_packing')->get();
        abort_unless($transaction->stage === 'at_packing', 409, 'Transaksi ini tidak sedang menunggu penerimaan packing. Muat ulang transaksi.');
        abort_unless($pending->contains('group_key', $data['group_key']), 409, 'Kelompok ini sudah diterima. Muat ulang transaksi.');
        $service->assertNewNumber($data['box_number']);

        return response()->json(['outcome' => 'ok', 'box_number' => $data['box_number'], 'message' => 'ID Box siap diterima. Stok bertambah setelah Simpan penerimaan.']);
    }

    public function update(Request $request, ExpiredBoxTransaction $transaction, ExpiredBoxBulkService $service)
    {
        $request->validate(['action' => ['required', 'in:qa,packing,receive']]);
        try {
            // Route identity is authoritative; membership is rechecked under lock by the service.
            $service->execute(array_merge($request->all(), ['transaction_id' => $transaction->id]), $request->user()->id);
        } catch (\Illuminate\Database\QueryException $e) {
            if (! in_array((string) $e->getCode(), ['23000', '23505', '40001'])) {
                throw $e;
            }

            return back()->withInput()->with('error', 'ID atau lokasi sedang digunakan. Transaksi belum disimpan; periksa dan coba kembali.');
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('expired-box.transactions.show', $transaction)->with('success', $request->input('action') === 'receive'
            ? 'Penerimaan tersimpan. Box aktif satu bulan sejak diterima; sisa box mengikuti status QA dan packing.'
            : 'Transaksi berhasil diperbarui.');
    }

    public function print(ExpiredBoxTransaction $transaction)
    {
        $transaction->load('handlings');
        abort_if($transaction->stage === 'at_qa', 409, 'Selesaikan hasil QA seluruh transaksi sebelum mencetak pengantar packing.');
        $records = $transaction->handlings->whereIn('status', ['awaiting_packing', 'at_packing', 'completed'])->values();
        abort_if($records->isEmpty(), 409, 'Tidak ada box lolos QA untuk diserahkan ke packing.');

        return view('warehouse.expired-box.bulk-print', compact('transaction', 'records'));
    }
}
