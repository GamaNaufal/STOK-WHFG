@extends('shared.layouts.app')

@section('title', 'Scan Issues')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 text-gray-800"><i class="bi bi-bell"></i> Scan Issues</h1>
        <p class="text-muted">Permintaan approval untuk error scan</p>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Scanned</th>
                        <th>Reason</th>
                        <th>Waktu</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($issues as $issue)
                        <tr>
                            <td>#{{ $issue->session->delivery_order_id }}</td>
                            <td>{{ $issue->scanned_code }}</td>
                            <td>{{ $issue->reason }}</td>
                            <td>{{ $issue->created_at->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('delivery.pick.issue.approve', $issue->id) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success">
                                        <i class="bi bi-check2"></i> Approve
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">Tidak ada issue.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection