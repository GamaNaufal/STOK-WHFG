@extends('shared.layouts.app')

@section('title', 'Delivery Lock Management')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1">
                    <i class="bi bi-shield-lock"></i> Delivery Lock Management
                </h1>
                <p class="text-muted mb-0">Kelola sesi delivery yang masih mengunci proses scanning.</p>
            </div>
            <a href="{{ route('delivery.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Kembali ke Delivery
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-lock"></i> Active Locks</span>
            <span class="badge bg-secondary">{{ $activeLocks->count() }}</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th>Order</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Pending Issue</th>
                            <th>Aktivitas Terakhir</th>
                            <th style="min-width: 300px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($activeLocks as $lock)
                        <tr>
                            <td>
                                <span class="fw-bold">#{{ $lock->id }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold">#{{ $lock->delivery_order_id }}</div>
                                <small class="text-muted">
                                    {{ optional($lock->order)->customer_name ?? '-' }}
                                    @if(optional($lock->order)->delivery_date)
                                        | {{ \Carbon\Carbon::parse($lock->order->delivery_date)->format('d M Y') }}
                                    @endif
                                </small>
                            </td>
                            <td>{{ optional($lock->creator)->name ?? '-' }}</td>
                            <td>
                                @if($lock->status === 'blocked')
                                    <span class="badge bg-danger">BLOCKED</span>
                                @else
                                    <span class="badge bg-warning text-dark">SCANNING</span>
                                @endif
                            </td>
                            <td>{{ (int) $lock->items_count }}</td>
                            <td>
                                @if((int) $lock->pending_issue_count > 0)
                                    <span class="badge bg-danger">{{ (int) $lock->pending_issue_count }}</span>
                                @else
                                    <span class="badge bg-success">0</span>
                                @endif
                            </td>
                            <td>
                                <small>{{ optional($lock->updated_at)->format('d M Y H:i') ?? '-' }}</small>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('delivery.pick.lock.terminate', $lock->id) }}" class="js-terminate-lock-form d-flex gap-2">
                                    @csrf
                                    <input type="text"
                                           name="reason"
                                           class="form-control form-control-sm"
                                           maxlength="500"
                                           required
                                           placeholder="Alasan terminate sesi ini">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-x-octagon"></i> Terminate
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bi bi-check-circle fs-2 text-success d-block mb-2"></i>
                                <div class="text-muted">Tidak ada lock aktif saat ini.</div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.js-terminate-lock-form').forEach((form) => {
        form.addEventListener('submit', function (event) {
            const reasonInput = form.querySelector('input[name="reason"]');
            const reason = String(reasonInput?.value || '').trim();

            if (!reason) {
                event.preventDefault();
                reasonInput?.focus();
                alert('Alasan terminate wajib diisi.');
                return;
            }

            const confirmed = confirm('Terminate sesi ini dan lepas lock sekarang?');
            if (!confirmed) {
                event.preventDefault();
            }
        });
    });
</script>
@endsection
