@extends('shared.layouts.app')

@section('title', 'Riwayat Pengambilan Stok - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2">
                    <i class="bi bi-clock-history"></i> Riwayat Pengambilan Stok
                </h1>
                <p class="text-muted">Daftar semua pengambilan stok dan fitur undo</p>
            </div>
            <a href="{{ route('delivery.index') }}" class="btn btn-lg" style="background-color: #0C7779; color: white; border: none;">
                <i class="bi bi-arrow-left"></i> Kembali ke Delivery
            </a>
        </div>
    </div>
</div>

<!-- History Table -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0" style="border-left: 4px solid #0C7779;">
            <div class="card-header text-white" style="background-color: #0C7779;">
                <i class="bi bi-table"></i> Daftar Pengambilan
            </div>
            <div class="card-body p-0">
                @if($withdrawals->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background-color: #f5f7fa; color: #0C7779;">
                                <tr>
                                    <th>No</th>
                                    <th>Part Number</th>
                                    <th>Box Diambil</th>
                                    <th>PCS Diambil</th>
                                    <th>Lokasi</th>
                                    <th>Operator</th>
                                    <th>Waktu Pengambilan</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($withdrawals as $key => $withdrawal)
                                    <tr>
                                        <td>{{ $withdrawals->firstItem() + $key }}</td>
                                        <td>
                                            <strong>{{ $withdrawal->part_number }}</strong>
                                        </td>
                                        <td>{{ (int) $withdrawal->box_quantity }} Box</td>
                                        <td>{{ $withdrawal->pcs_quantity }} PCS</td>
                                        <td>{{ $withdrawal->warehouse_location }}</td>
                                        <td>{{ $withdrawal->user->name }}</td>
                                        <td>
                                            <small class="text-muted">
                                                {{ $withdrawal->withdrawn_at->format('d/m/Y H:i') }}
                                            </small>
                                        </td>
                                        <td>
                                            @if($withdrawal->status === 'completed')
                                                <span class="badge" style="background-color: #249E94; color: white;">
                                                    <i class="bi bi-check-circle"></i> Selesai
                                                </span>
                                            @else
                                                <span class="badge" style="background-color: #9ca3af; color: white;">
                                                    <i class="bi bi-x-circle"></i> Dibatalkan
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($withdrawal->status === 'completed')
                                                <button type="button" class="btn btn-sm" 
                                                        onclick="undoWithdrawal({{ $withdrawal->id }})"
                                                        style="background-color: #e74c3c; color: white; border: none;">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Undo
                                                </button>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="p-3">
                        {{ $withdrawals->links() }}
                    </div>
                @else
                    <div class="p-5 text-center">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                        <h5 class="mt-3 text-muted">Belum ada riwayat pengambilan</h5>
                        <a href="{{ route('stock-withdrawal.index') }}" class="btn mt-3" style="background-color: #0C7779; color: white; border: none;">
                            <i class="bi bi-plus-lg"></i> Ambil Stok
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal for Undo -->
<div class="modal fade" id="undoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #0C7779; color: white; border: none;">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle"></i> Konfirmasi Pembatalan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin membatalkan pengambilan stok ini?</p>
                <p class="text-muted small">Jumlah stok akan dikembalikan ke inventory.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Tidak
                </button>
                <button type="button" id="confirmUndoBtn" class="btn" style="background-color: #0C7779; color: white; border: none;">
                    <i class="bi bi-check-circle"></i> Ya, Batalkan
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .loading {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #0C7779;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

@endsection

@section('scripts')
<script>
    let currentWithdrawalId = null;

    function undoWithdrawal(withdrawalId) {
        currentWithdrawalId = withdrawalId;
        const modal = new bootstrap.Modal(document.getElementById('undoModal'));
        modal.show();
    }

    document.getElementById('confirmUndoBtn').addEventListener('click', function() {
        if (!currentWithdrawalId) return;

        this.disabled = true;
        this.innerHTML = '<span class="loading"></span> Memproses...';

        fetch(`/stock-withdrawal/${currentWithdrawalId}/undo`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Content-Type': 'application/json',
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(e => {
            console.error('Error:', e);
            alert('Terjadi kesalahan');
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-check-circle"></i> Ya, Batalkan';
        });
    });
</script>
@endsection
