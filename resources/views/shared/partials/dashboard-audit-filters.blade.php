<form id="audit-filter-form" method="GET" action="{{ url()->current() }}" class="dashboard-filters dashboard-audit-filters">
    <fieldset aria-describedby="dashboard-audit-help">
        <legend>Saring aktivitas</legend>
        <div class="dashboard-filters-grid">
            <div>
                <label for="dashboard-audit-type" class="form-label">Jenis aktivitas</label>
                <select id="dashboard-audit-type" name="audit_type" class="form-select">
                    <option value="">Semua jenis</option>
                    @foreach($auditTypes as $type)
                        <option value="{{ $type }}" {{ request('audit_type') === $type ? 'selected' : '' }}>{{ $typeLabels[$type] ?? $type }}</option>
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
            <p class="dashboard-filters-help" id="dashboard-audit-help">Petugas adalah operator atau admin yang melakukan aksi. Pilih filter, lalu tekan Terapkan.</p>
            <div class="dashboard-filters-actions">
                <button class="btn btn-primary" type="submit">Terapkan</button>
                <a href="{{ url()->current() }}?period={{ request('period', 'week') }}" class="btn btn-outline-secondary" id="audit-filter-reset">Reset</a>
            </div>
        </div>
    </fieldset>
</form>
