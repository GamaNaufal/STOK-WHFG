@extends('shared.layouts.app')

@section('title', 'Scan Issues')

@section('content')
<div class="container-fluid">
    <!-- Modern Gradient Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div style="background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); 
                        color: white; 
                        padding: 40px 30px; 
                        border-radius: 12px; 
                        box-shadow: 0 8px 24px rgba(239, 68, 68, 0.15);">
                <div>
                    <h1 class="h2" style="margin: 0 0 10px 0; font-weight: 700;">
                        <i class="bi bi-exclamation-triangle-fill"></i> Scan Issues
                    </h1>
                    <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                        Permintaan approval untuk error scan delivery
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Issues Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header text-white fw-bold" style="background-color: #EF4444;">
            <i class="bi bi-bell-fill"></i> Pending Issues
            @php
                $pendingIssueCount = is_countable($issues) ? count($issues) : 0;
            @endphp
            @if($pendingIssueCount > 0)
                <span class="badge bg-white text-danger ms-2">{{ $pendingIssueCount }}</span>
            @endif
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th style="width: 10%;">Order</th>
                            <th style="width: 20%;">Scanned Code</th>
                            <th style="width: 15%;">Waktu</th>
                            <th style="width: 30%;">Notes Admin</th>
                            <th class="text-center" style="width: 15%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($issues as $issue)
                            <tr>
                                <td>
                                    <span class="badge" style="background-color: #DBEAFE; color: #1E40AF; font-size: 0.9rem;">
                                        #{{ $issue->session->delivery_order_id }}
                                    </span>
                                </td>
                                <td>
                                    <code style="background-color: #FEE2E2; color: #991B1B; padding: 4px 8px; border-radius: 4px; font-size: 0.9rem;">
                                        {{ $issue->scanned_code }}
                                    </code>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> {{ $issue->created_at->format('d M Y H:i') }}
                                    </small>
                                </td>
                                <td>
                                    <input type="text" 
                                           name="notes" 
                                           class="form-control form-control-sm" 
                                           form="approve-issue-{{ $issue->id }}" 
                                           required 
                                           maxlength="500" 
                                           placeholder="Masukkan catatan approval...">
                                </td>
                                <td class="text-center">
                                    <form id="approve-issue-{{ $issue->id }}" 
                                          method="POST" 
                                          action="{{ route('delivery.pick.issue.approve', $issue->id) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle"></i> Approve
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="bi bi-check-circle display-5 text-success d-block mb-2"></i>
                                    <p class="text-muted mb-0">Tidak ada issue pending</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- History Card -->
    <div class="card shadow-sm border-0">
        <div class="card-header text-white fw-bold" style="background-color: #6B7280;">
            <i class="bi bi-clock-history"></i> History Issues
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th style="width: 10%;">Order</th>
                            <th style="width: 20%;">Scanned Code</th>
                            <th style="width: 15%;">Waktu Resolved</th>
                            <th style="width: 10%;">Status</th>
                            <th style="width: 35%;">Notes Admin</th>
                            <th class="text-center" style="width: 10%;">-</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($historyIssues as $issue)
                            <tr>
                                <td>
                                    <span class="badge" style="background-color: #DBEAFE; color: #1E40AF; font-size: 0.9rem;">
                                        #{{ $issue->session->delivery_order_id }}
                                    </span>
                                </td>
                                <td>
                                    <code style="background-color: #F3F4F6; color: #374151; padding: 4px 8px; border-radius: 4px; font-size: 0.9rem;">
                                        {{ $issue->scanned_code }}
                                    </code>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> 
                                        {{ optional($issue->resolved_at)->format('d M Y H:i') ?? '-' }}
                                    </small>
                                </td>
                                <td>
                                    @if($issue->status === 'approved')
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle"></i> APPROVED
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">
                                            {{ strtoupper($issue->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $issue->notes ?? '-' }}</small>
                                </td>
                                <td class="text-center">
                                    <span class="text-muted">-</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-inbox display-5 text-muted d-block mb-2"></i>
                                    <p class="text-muted mb-0">Belum ada history issues</p>
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
    document.querySelectorAll('form[id^="approve-issue-"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const notesInput = document.querySelector(`[name="notes"][form="${this.id}"]`) || this.querySelector('[name="notes"]');
            if (!notesInput) {
                WarehouseAlert.error({
                    title: 'Field Notes Tidak Ditemukan',
                    message: 'Silakan refresh halaman lalu coba lagi.'
                });
                return;
            }
            const notes = notesInput.value.trim();
            
            if (!notes) {
                WarehouseAlert.error({
                    title: 'Notes Diperlukan',
                    message: 'Harap masukkan catatan approval sebelum melanjutkan.'
                });
                notesInput.focus();
                return;
            }
            
            WarehouseAlert.confirm({
                title: 'Approve Scan Issue?',
                message: 'Anda akan menyetujui issue scan ini.',
                warningItems: [
                    'Scan yang error akan <strong>diterima</strong>',
                    'Catatan akan tersimpan di history'
                ],
                infoText: `<strong>Catatan:</strong> ${notes}`,
                confirmText: 'Ya, Approve',
                confirmColor: '#10B981',
                onConfirm: () => {
                    this.submit();
                }
            });
        });
    });
</script>
@endsection
