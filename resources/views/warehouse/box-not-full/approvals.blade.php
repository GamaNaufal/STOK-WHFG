@extends('shared.layouts.app')

@section('title', 'Approval Box Not Full')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h4"><i class="bi bi-clipboard-check"></i> Approval Box Not Full</h1>
            <p class="text-muted">Daftar permintaan box not full yang menunggu persetujuan.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            @if($requests->isEmpty())
                <div class="alert alert-info">Tidak ada permintaan pending.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID Box</th>
                                <th>No Part</th>
                                <th>PCS</th>
                                <th>Fixed</th>
                                <th>Delivery</th>
                                <th>Tipe</th>
                                <th>Target</th>
                                <th>Alasan</th>
                                <th>Requester</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($requests as $req)
                                <tr>
                                    <td>{{ $req->box_number }}</td>
                                    <td>{{ $req->part_number }}</td>
                                    <td>{{ $req->pcs_quantity }}</td>
                                    <td>{{ $req->fixed_qty }}</td>
                                    <td>
                                        #{{ $req->delivery_order_id }}
                                        @if($req->deliveryOrder)
                                            - {{ $req->deliveryOrder->customer_name }} ({{ $req->deliveryOrder->delivery_date->format('d M Y') }})
                                        @endif
                                    </td>
                                    <td>{{ $req->request_type === 'additional' ? 'Tambahan' : 'Pelengkap' }}</td>
                                    <td>
                                        @if($req->targetPallet)
                                            Pallet: {{ $req->targetPallet->pallet_number }}
                                        @elseif($req->targetLocation)
                                            Lokasi: {{ $req->targetLocation->code }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $req->reason }}</td>
                                    <td>{{ $req->requester?->name }}</td>
                                    <td class="d-flex gap-2">
                                        <form method="POST" action="{{ route('box-not-full.approve', $req->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('box-not-full.reject', $req->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm border-0 mt-4">
        <div class="card-body">
            <h5 class="mb-3"><i class="bi bi-clock-history"></i> Riwayat Approval</h5>
            @if($historyRequests->isEmpty())
                <div class="alert alert-info">Belum ada riwayat approval.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>ID Box</th>
                                <th>No Part</th>
                                <th>PCS</th>
                                <th>Delivery</th>
                                <th>Status</th>
                                <th>Requester</th>
                                <th>Approver</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($historyRequests as $req)
                                <tr>
                                    <td>{{ $req->updated_at->format('d M Y H:i') }}</td>
                                    <td>{{ $req->box_number }}</td>
                                    <td>{{ $req->part_number }}</td>
                                    <td>{{ $req->pcs_quantity }}</td>
                                    <td>
                                        #{{ $req->delivery_order_id }}
                                        @if($req->deliveryOrder)
                                            - {{ $req->deliveryOrder->customer_name }} ({{ $req->deliveryOrder->delivery_date->format('d M Y') }})
                                        @endif
                                    </td>
                                    <td>
                                        @if($req->status === 'approved')
                                            <span class="badge bg-success">Approved</span>
                                        @else
                                            <span class="badge bg-danger">Rejected</span>
                                        @endif
                                    </td>
                                    <td>{{ $req->requester?->name ?? '-' }}</td>
                                    <td>{{ $req->approver?->name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
