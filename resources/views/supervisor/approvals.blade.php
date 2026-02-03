@extends('shared.layouts.app')

@section('title', 'Persetujuan Box Not Full')

@push('styles')
<style>
    @media (max-width: 768px) {
        .table-responsive {
            font-size: 0.875rem;
        }
        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
    }
    
    .bg-outline-primary {
        background-color: transparent !important;
    }
    
    .bg-outline-success {
        background-color: transparent !important;
    }
</style>
@endpush

@section('content')
<!-- Page Header Component -->
<x-page-header 
    title="Persetujuan Box Not Full" 
    icon="bi-clipboard-check"
    subtitle="Daftar permintaan box not full yang menunggu persetujuan Anda"
/>

<!-- Pending Requests Section -->
<div class="row mb-4">
    <div class="col-12">
        <x-card title="Permintaan Menunggu Persetujuan" icon="bi-hourglass-split">
            @if($requests->isEmpty())
                <x-empty-state 
                    icon="bi-inbox"
                    title="Tidak Ada Permintaan Pending"
                    message="Semua permintaan box not full telah diproses"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">ID Box</th>
                                <th class="text-nowrap">No Part</th>
                                <th class="text-nowrap text-center">PCS</th>
                                <th class="text-nowrap text-center">Fixed</th>
                                <th class="text-nowrap">Delivery Order</th>
                                <th class="text-nowrap">Tipe</th>
                                <th class="text-nowrap">Target</th>
                                <th class="text-nowrap">Alasan</th>
                                <th class="text-nowrap">Requester</th>
                                <th class="text-nowrap text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($requests as $req)
                                <tr>
                                    <td>
                                        <span class="badge bg-primary">{{ $req->box_number }}</span>
                                    </td>
                                    <td>
                                        <code class="text-primary bg-light px-2 py-1 rounded">{{ $req->part_number }}</code>
                                    </td>
                                    <td class="text-center">
                                        <strong>{{ number_format($req->pcs_quantity) }}</strong>
                                        <small class="text-muted d-block">PCS</small>
                                    </td>
                                    <td class="text-center">
                                        <strong>{{ number_format($req->fixed_qty) }}</strong>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="badge bg-info mb-1">#{{ $req->delivery_order_id }}</span>
                                            @if($req->deliveryOrder)
                                                <small class="text-muted">{{ Str::limit($req->deliveryOrder->customer_name, 20) }}</small>
                                                <small class="text-muted">{{ $req->deliveryOrder->delivery_date->format('d M Y') }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if($req->request_type === 'additional')
                                            <span class="badge bg-info">
                                                <i class="bi bi-plus-circle"></i> Tambahan
                                            </span>
                                        @else
                                            <span class="badge bg-purple text-white" style="background-color: #9b59b6;">
                                                <i class="bi bi-puzzle"></i> Pelengkap
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($req->targetPallet)
                                            <span class="badge bg-outline-primary border border-primary text-primary">
                                                <i class="bi bi-box"></i> {{ $req->targetPallet->pallet_number }}
                                            </span>
                                        @elseif($req->targetLocation)
                                            <span class="badge bg-outline-success border border-success text-success">
                                                <i class="bi bi-geo-alt"></i> {{ $req->targetLocation->code }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ Str::limit($req->reason, 30) }}</small>
                                    </td>
                                    <td>
                                        <strong class="text-dark">{{ $req->requester?->name ?? '-' }}</strong>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                                            <form method="POST" action="{{ route('box-not-full.approve', $req->id) }}" class="d-inline">
                                                @csrf
                                                <x-button 
                                                    type="submit" 
                                                    variant="success"
                                                    size="sm"
                                                    title="Setujui permintaan ini"
                                                    onclick="return confirm('Apakah Anda yakin ingin menyetujui permintaan ini?')"
                                                >
                                                    <i class="bi bi-check-circle"></i>
                                                    <span class="d-none d-lg-inline">Approve</span>
                                                </x-button>
                                            </form>
                                            <form method="POST" action="{{ route('box-not-full.reject', $req->id) }}" class="d-inline">
                                                @csrf
                                                <x-button 
                                                    type="submit" 
                                                    variant="danger"
                                                    size="sm"
                                                    title="Tolak permintaan ini"
                                                    onclick="return confirm('Apakah Anda yakin ingin menolak permintaan ini?')"
                                                >
                                                    <i class="bi bi-x-circle"></i>
                                                    <span class="d-none d-lg-inline">Reject</span>
                                                </x-button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
</div>

<!-- History Section -->
<div class="row">
    <div class="col-12">
        <x-card title="Riwayat Persetujuan" icon="bi-clock-history">
            @if($historyRequests->isEmpty())
                <x-empty-state 
                    icon="bi-calendar-x"
                    title="Belum Ada Riwayat Persetujuan"
                    message="Riwayat persetujuan akan muncul di sini setelah ada permintaan yang diproses"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">Tanggal</th>
                                <th class="text-nowrap">ID Box</th>
                                <th class="text-nowrap">No Part</th>
                                <th class="text-nowrap text-center">PCS</th>
                                <th class="text-nowrap">Delivery Order</th>
                                <th class="text-nowrap">Status</th>
                                <th class="text-nowrap">Requester</th>
                                <th class="text-nowrap">Approver</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($historyRequests as $req)
                                <tr>
                                    <td>
                                        <small class="text-muted">{{ $req->updated_at->format('d/m/Y H:i') }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ $req->box_number }}</span>
                                    </td>
                                    <td>
                                        <code class="text-primary bg-light px-2 py-1 rounded">{{ $req->part_number }}</code>
                                    </td>
                                    <td class="text-center">
                                        <strong>{{ number_format($req->pcs_quantity) }}</strong>
                                        <small class="text-muted d-block">PCS</small>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="badge bg-info mb-1">#{{ $req->delivery_order_id }}</span>
                                            @if($req->deliveryOrder)
                                                <small class="text-muted">{{ Str::limit($req->deliveryOrder->customer_name, 20) }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if($req->status === 'approved')
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Disetujui
                                            </span>
                                        @else
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-circle"></i> Ditolak
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong class="text-dark">{{ $req->requester?->name ?? '-' }}</strong>
                                    </td>
                                    <td>
                                        <strong class="text-dark">{{ $req->approver?->name ?? '-' }}</strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
</div>
@endsection
