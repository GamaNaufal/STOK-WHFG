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
                        <th>Waktu</th>
                        <th>Status</th>
                        <th>Notes Admin</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="issue-table-body">
                    @forelse($issues as $issue)
                        <tr>
                            <td>#{{ $issue->session->delivery_order_id }}</td>
                            <td>{{ $issue->scanned_code }}</td>
                            <td>{{ $issue->created_at->format('d M Y H:i') }}</td>
                            <td>-</td>
                            <td>
                                <input type="text" name="notes" class="form-control form-control-sm" form="approve-issue-{{ $issue->id }}" required maxlength="500" placeholder="Catatan approval">
                            </td>
                            <td class="text-end">
                                <form id="approve-issue-{{ $issue->id }}" method="POST" action="{{ route('delivery.pick.issue.approve', $issue->id) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success">
                                        <i class="bi bi-check2"></i> Approve
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">Tidak ada issue.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">History</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Scanned</th>
                        <th>Waktu</th>
                        <th>Status</th>
                        <th>Notes Admin</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($historyIssues as $issue)
                        <tr>
                            <td>#{{ $issue->session->delivery_order_id }}</td>
                            <td>{{ $issue->scanned_code }}</td>
                            <td>{{ optional($issue->resolved_at)->format('d M Y H:i') ?? '-' }}</td>
                            <td>{{ strtoupper($issue->status) }}</td>
                            <td>{{ $issue->notes ?? '-' }}</td>
                            <td class="text-end">-</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">Belum ada history.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
