@extends('shared.layouts.app')

@section('title', 'Persetujuan Box Not Full')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/not-full.css') }}">
@endsection

@section('content')
<div class="not-full-page">
@include('shared.not-full-feedback')
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
                <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel data, geser untuk melihat kolom lainnya">
                    <table class="table table-hover mb-0 not-full-pending-table">
                        <caption class="visually-hidden">Rincian request Box Not Full</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="text-nowrap">ID Box</th>
                                <th scope="col" class="text-nowrap">No Part</th>
                                <th scope="col" class="text-nowrap text-center">PCS</th>
                                <th scope="col" class="text-nowrap text-center">Fixed</th>
                                <th scope="col" class="text-nowrap">Delivery Order</th>
                                <th scope="col" class="text-nowrap">Tipe</th>
                                <th scope="col" class="text-nowrap">Target</th>
                                <th scope="col" class="text-nowrap">Alasan</th>
                                <th scope="col" class="text-nowrap">Requester</th>
                                <th scope="col" class="text-nowrap text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($requests as $req)
                                <tr>
                                    <td data-label="ID Box">
                                        <span class="not-full-identity">{{ $req->box_number }}</span>
                                    </td>
                                    <td data-label="No Part">
                                        <span class="not-full-part">{{ $req->part_number }}</span>
                                    </td>
                                    <td data-label="PCS" class="text-center">
                                        <strong>{{ number_format($req->pcs_quantity) }}</strong>
                                        <small class="text-muted d-block">PCS</small>
                                    </td>
                                    <td data-label="Kapasitas" class="text-center">
                                        <strong>{{ number_format($req->fixed_qty) }}</strong>
                                    </td>
                                    <td data-label="Delivery">
                                        <div class="d-flex flex-column">
                                            <span class="badge bg-info mb-1">#{{ $req->delivery_order_id }}</span>
                                            @if($req->deliveryOrder)
                                                <small class="text-muted">{{ $req->deliveryOrder->customer_name }}</small>
                                                <small class="text-muted">{{ $req->deliveryOrder->delivery_date->format('d M Y') }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td data-label="Tipe">
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
                                    <td data-label="Tujuan">
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
                                    <td data-label="Alasan">
                                        <small class="text-muted">{{ $req->reason }}</small>
                                    </td>
                                    <td data-label="Pemohon">
                                        <strong class="text-dark">{{ $req->requester?->name ?? '-' }}</strong>
                                    </td>
                                    <td data-label="Keputusan">
                                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                                            <form method="POST" action="{{ route('box-not-full.approve', $req->id) }}" class="d-inline js-approve-form" data-box="{{ $req->box_number }}" data-part="{{ $req->part_number }}" data-quantity="{{ $req->pcs_quantity }}" data-delivery="{{ $req->delivery_order_id }}" data-type="{{ $req->request_type }}">
                                                @csrf
                                                <x-button 
                                                    type="submit" 
                                                    variant="success"
                                                    size="sm"
                                                    title="Setujui permintaan ini"
                                                >
                                                    <i class="bi bi-check-circle"></i>
                                                    <span>Setujui</span>
                                                </x-button>
                                            </form>
                                            <form method="POST" action="{{ route('box-not-full.reject', $req->id) }}" class="d-inline js-reject-form" data-box="{{ $req->box_number }}" data-part="{{ $req->part_number }}" data-quantity="{{ $req->pcs_quantity }}" data-delivery="{{ $req->delivery_order_id }}">
                                                @csrf
                                                <x-button 
                                                    type="submit" 
                                                    variant="danger"
                                                    size="sm"
                                                    title="Tolak permintaan ini"
                                                >
                                                    <i class="bi bi-x-circle"></i>
                                                    <span>Tolak</span>
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
                <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel data, geser untuk melihat kolom lainnya">
                    <table class="table table-hover mb-0">
                        <caption class="visually-hidden">Riwayat keputusan Box Not Full</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="text-nowrap">Tanggal</th>
                                <th scope="col" class="text-nowrap">ID Box</th>
                                <th scope="col" class="text-nowrap">No Part</th>
                                <th scope="col" class="text-nowrap text-center">PCS</th>
                                <th scope="col" class="text-nowrap">Delivery Order</th>
                                <th scope="col" class="text-nowrap">Status</th>
                                <th scope="col" class="text-nowrap">Requester</th>
                                <th scope="col" class="text-nowrap">Approver</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($historyRequests as $req)
                                <tr>
                                    <td>
                                        <small class="text-muted">{{ $req->updated_at->format('d/m/Y H:i') }}</small>
                                    </td>
                                    <td>
                                        <span class="not-full-identity">{{ $req->box_number }}</span>
                                    </td>
                                    <td>
                                        <span class="not-full-part">{{ $req->part_number }}</span>
                                    </td>
                                    <td class="text-center">
                                        <strong>{{ number_format($req->pcs_quantity) }}</strong>
                                        <small class="text-muted d-block">PCS</small>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="badge bg-info mb-1">#{{ $req->delivery_order_id }}</span>
                                            @if($req->deliveryOrder)
                                                <small class="text-muted">{{ $req->deliveryOrder->customer_name }}</small>
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
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/not-full.js') }}"></script>
@endpush
