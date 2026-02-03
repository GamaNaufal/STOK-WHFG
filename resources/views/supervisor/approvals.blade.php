@extends('shared.layouts.app')

@section('title', 'Approval Box Not Full')

@section('content')
<div class="container-fluid">
    <!-- Modern Header Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                        color: white; 
                        padding: 40px 30px; 
                        border-radius: 12px; 
                        box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);">
                <h1 class="h2" style="margin: 0 0 10px 0; font-weight: 700;">
                    <i class="bi bi-clipboard-check"></i> Approval Box Not Full
                </h1>
                <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                    Daftar permintaan box not full yang menunggu persetujuan Anda
                </p>
            </div>
        </div>
    </div>

    <!-- Pending Requests Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0" style="border-left: 4px solid #0C7779;">
                <div class="card-header text-white" style="background-color: #0C7779;">
                    <i class="bi bi-hourglass-split"></i> Permintaan Menunggu Persetujuan
                </div>
                <div class="card-body p-0">
                    @if($requests->isEmpty())
                        <div class="p-5 text-center">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <h5 class="mt-3 text-muted">Tidak ada permintaan pending</h5>
                            <p class="text-muted small">Semua permintaan box not full telah diproses</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead style="background-color: #f5f7fa; color: #0C7779;">
                                    <tr>
                                        <th style="width: 12%;">ID Box</th>
                                        <th style="width: 12%;">No Part</th>
                                        <th style="width: 8%;">PCS</th>
                                        <th style="width: 8%;">Fixed</th>
                                        <th style="width: 15%;">Delivery Order</th>
                                        <th style="width: 10%;">Tipe</th>
                                        <th style="width: 12%;">Target</th>
                                        <th style="width: 15%;">Alasan</th>
                                        <th style="width: 12%;">Requester</th>
                                        <th style="width: 18%; text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requests as $req)
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td>
                                                <strong style="color: #0C7779;">{{ $req->box_number }}</strong>
                                            </td>
                                            <td>
                                                <code style="background-color: #f0f4f7; padding: 4px 8px; border-radius: 4px;">{{ $req->part_number }}</code>
                                            </td>
                                            <td>{{ $req->pcs_quantity }} PCS</td>
                                            <td>{{ $req->fixed_qty }}</td>
                                            <td>
                                                <small>
                                                    <strong>#{{ $req->delivery_order_id }}</strong>
                                                    @if($req->deliveryOrder)
                                                        <br>{{ $req->deliveryOrder->customer_name }}
                                                        <br><span class="text-muted">{{ $req->deliveryOrder->delivery_date->format('d M Y') }}</span>
                                                    @endif
                                                </small>
                                            </td>
                                            <td>
                                                @if($req->request_type === 'additional')
                                                    <span class="badge" style="background-color: #3498db; color: white;">
                                                        <i class="bi bi-plus-circle"></i> Tambahan
                                                    </span>
                                                @else
                                                    <span class="badge" style="background-color: #9b59b6; color: white;">
                                                        <i class="bi bi-plus-lg"></i> Pelengkap
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($req->targetPallet)
                                                    <span class="badge bg-light" style="color: #0C7779; border: 1px solid #0C7779;">
                                                        <i class="bi bi-box"></i> {{ $req->targetPallet->pallet_number }}
                                                    </span>
                                                @elseif($req->targetLocation)
                                                    <span class="badge bg-light" style="color: #249E94; border: 1px solid #249E94;">
                                                        <i class="bi bi-geo-alt"></i> {{ $req->targetLocation->code }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small>{{ $req->reason }}</small>
                                            </td>
                                            <td>
                                                <small><strong>{{ $req->requester?->name ?? '-' }}</strong></small>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2 justify-content-center">
                                                    <form method="POST" action="{{ route('box-not-full.approve', $req->id) }}" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm" style="background-color: #249E94; color: white; border: none; padding: 6px 12px;" title="Setujui permintaan ini">
                                                            <i class="bi bi-check-circle"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('box-not-full.reject', $req->id) }}" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm" style="background-color: #e74c3c; color: white; border: none; padding: 6px 12px;" title="Tolak permintaan ini">
                                                            <i class="bi bi-x-circle"></i> Reject
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- History Section -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0" style="border-left: 4px solid #0C7779;">
                <div class="card-header text-white" style="background-color: #0C7779;">
                    <i class="bi bi-clock-history"></i> Riwayat Approval
                </div>
                <div class="card-body p-0">
                    @if($historyRequests->isEmpty())
                        <div class="p-5 text-center">
                            <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
                            <h5 class="mt-3 text-muted">Belum ada riwayat approval</h5>
                            <p class="text-muted small">Riwayat approval akan muncul di sini setelah ada permintaan yang diproses</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead style="background-color: #f5f7fa; color: #0C7779;">
                                    <tr>
                                        <th style="width: 14%;">Tanggal</th>
                                        <th style="width: 12%;">ID Box</th>
                                        <th style="width: 12%;">No Part</th>
                                        <th style="width: 8%;">PCS</th>
                                        <th style="width: 18%;">Delivery Order</th>
                                        <th style="width: 10%;">Status</th>
                                        <th style="width: 13%;">Requester</th>
                                        <th style="width: 13%;">Approver</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($historyRequests as $req)
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td>
                                                <small class="text-muted">{{ $req->updated_at->format('d/m/Y H:i') }}</small>
                                            </td>
                                            <td>
                                                <strong style="color: #0C7779;">{{ $req->box_number }}</strong>
                                            </td>
                                            <td>
                                                <code style="background-color: #f0f4f7; padding: 4px 8px; border-radius: 4px;">{{ $req->part_number }}</code>
                                            </td>
                                            <td>{{ $req->pcs_quantity }} PCS</td>
                                            <td>
                                                <small>
                                                    <strong>#{{ $req->delivery_order_id }}</strong>
                                                    @if($req->deliveryOrder)
                                                        <br>{{ $req->deliveryOrder->customer_name }}
                                                    @endif
                                                </small>
                                            </td>
                                            <td>
                                                @if($req->status === 'approved')
                                                    <span class="badge" style="background-color: #249E94; color: white;">
                                                        <i class="bi bi-check-circle"></i> Approved
                                                    </span>
                                                @else
                                                    <span class="badge" style="background-color: #e74c3c; color: white;">
                                                        <i class="bi bi-x-circle"></i> Rejected
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <small><strong>{{ $req->requester?->name ?? '-' }}</strong></small>
                                            </td>
                                            <td>
                                                <small><strong>{{ $req->approver?->name ?? '-' }}</strong></small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
