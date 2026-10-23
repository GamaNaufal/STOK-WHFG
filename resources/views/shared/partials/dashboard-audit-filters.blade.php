@php
    $auditTypeLabels = [
        'stock_input' => 'Input Stok', 'stock_withdrawal' => 'Pengambilan Stok',
        'delivery_pickup' => 'Delivery Pickup', 'delivery_redo' => 'Delivery Redo',
        'delivery_assignment' => 'Delivery Assignment', 'pallet_merged' => 'Pallet Merge',
        'box_pallet_moved' => 'Box Dipindahkan', 'other' => 'Lainnya',
    ];
    $activeAuditType = is_scalar(request('audit_type')) ? (string) request('audit_type') : null;
    $activeAuditAction = is_scalar(request('audit_action')) ? (string) request('audit_action') : null;
    $activeAuditUser = is_scalar(request('audit_user')) ? (string) request('audit_user') : null;
    $activeAuditCount = collect([$activeAuditType, $activeAuditAction, $activeAuditUser])->filter(fn ($value) => filled($value))->count();
    $activeAuditUserName = $activeAuditUser ? ($auditUsers->firstWhere('id', $activeAuditUser)?->name ?? 'User #'.$activeAuditUser) : null;
@endphp
<form id="audit-filter-form" method="GET" action="{{ url()->current() }}" class="dashboard-filters dashboard-audit-filters">
    @foreach(['period', 'start_date', 'end_date', 'group_by', 'fulfillment_filter'] as $contextKey)
        @if(is_scalar(request($contextKey)) && request()->filled($contextKey))
            <input type="hidden" name="{{ $contextKey }}" value="{{ request($contextKey) }}">
        @endif
    @endforeach
    <fieldset aria-describedby="dashboard-audit-help">
        <legend>Filter aktivitas</legend>
        <div class="dashboard-audit-intro">
            <p>Pilih jenis, aksi, atau petugas untuk menelusuri riwayat pada periode laporan ini.</p>
            <span class="dashboard-audit-count">{{ $activeAuditCount }} filter aktif</span>
        </div>
        <div class="dashboard-filters-grid">
            <div>
                <label for="dashboard-audit-type" class="form-label">Jenis aktivitas</label>
                <select id="dashboard-audit-type" name="audit_type" class="form-select">
                    <option value="">Semua jenis</option>
                    @foreach($auditTypes as $type)
                        <option value="{{ $type }}" {{ request('audit_type') === $type ? 'selected' : '' }}>{{ $auditTypeLabels[$type] ?? $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="dashboard-audit-action" class="form-label">Aksi</label>
                <select id="dashboard-audit-action" name="audit_action" class="form-select">
                    <option value="">Semua aksi</option>
                    @foreach($auditActions as $action)
                        <option value="{{ $action }}" {{ request('audit_action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="dashboard-audit-user" class="form-label">Petugas</label>
                <select id="dashboard-audit-user" name="audit_user" class="form-select">
                    <option value="">Semua petugas</option>
                    @foreach($auditUsers as $user)
                        <option value="{{ $user->id }}" {{ request('audit_user') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="dashboard-audit-bottom">
            <div class="dashboard-audit-guidance">
                <p class="dashboard-filters-help" id="dashboard-audit-help">Petugas adalah operator atau admin yang melakukan aksi. Tekan Terapkan untuk memperbarui hasil.</p>
                <div class="dashboard-audit-applied" aria-label="Filter audit yang sedang diterapkan">
                    <span class="dashboard-audit-applied-label">Sedang diterapkan</span>
                    @if($activeAuditCount)
                        @if(filled($activeAuditType))<span class="dashboard-audit-chip">Jenis: {{ $auditTypeLabels[$activeAuditType] ?? $activeAuditType }}</span>@endif
                        @if(filled($activeAuditAction))<span class="dashboard-audit-chip">Aksi: {{ $activeAuditAction }}</span>@endif
                        @if(filled($activeAuditUser))<span class="dashboard-audit-chip">Petugas: {{ $activeAuditUserName }}</span>@endif
                    @else
                        <span class="dashboard-audit-all">Semua aktivitas</span>
                    @endif
                </div>
            </div>
            <div class="dashboard-filters-actions">
                <button class="btn btn-primary" type="submit">Terapkan</button>
                <a href="{{ url()->current() }}?{{ http_build_query(request()->except('audit_type', 'audit_action', 'audit_user', 'page') ?: ['period' => 'week']) }}" class="btn btn-outline-secondary" id="audit-filter-reset">Reset</a>
            </div>
        </div>
    </fieldset>
</form>
