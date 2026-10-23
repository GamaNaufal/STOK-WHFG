<form method="GET" action="{{ url()->current() }}" class="dashboard-filters" id="audit-report" aria-label="Filter laporan">
    <fieldset class="dashboard-filters-period">
        <legend>Periode laporan</legend>
        <div class="dashboard-filters-grid">
            <div>
                <label for="dashboard-period" class="form-label">Periode cepat</label>
                <select id="dashboard-period" name="period" class="form-select" aria-describedby="dashboard-date-help">
                    <option value="today" {{ request('period') === 'today' ? 'selected' : '' }}>Hari Ini</option>
                    <option value="week" {{ request('period', 'week') === 'week' ? 'selected' : '' }}>Minggu Ini</option>
                    <option value="month" {{ request('period') === 'month' ? 'selected' : '' }}>Bulan Ini</option>
                    <option value="all" {{ request('period') === 'all' ? 'selected' : '' }}>All Time</option>
                </select>
            </div>
            <div>
                <label for="dashboard-start" class="form-label">Tanggal mulai</label>
                <input id="dashboard-start" type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" aria-describedby="dashboard-date-help">
            </div>
            <div>
                <label for="dashboard-end" class="form-label">Tanggal akhir</label>
                <input id="dashboard-end" type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" aria-describedby="dashboard-date-help">
            </div>
        </div>
        <p id="dashboard-date-help" class="dashboard-filters-help">Isi salah satu atau kedua tanggal untuk menggantikan periode cepat.</p>
    </fieldset>
    <div class="dashboard-filters-bottom">
        <fieldset class="dashboard-filters-options">
            <legend>Tampilan data</legend>
            <div class="dashboard-filters-grid">
                <div>
                    <label for="dashboard-group" class="form-label">Kelompokkan per</label>
                    <select id="dashboard-group" name="group_by" class="form-select">
                        <option value="day" {{ request('group_by', 'day') === 'day' ? 'selected' : '' }}>Harian</option>
                        <option value="week" {{ request('group_by') === 'week' ? 'selected' : '' }}>Mingguan</option>
                        <option value="month" {{ request('group_by') === 'month' ? 'selected' : '' }}>Bulanan</option>
                    </select>
                </div>
                <div>
                    <label for="dashboard-fulfillment" class="form-label">Fulfillment</label>
                    <select id="dashboard-fulfillment" name="fulfillment_filter" class="form-select">
                        <option value="all" {{ request('fulfillment_filter', 'all') === 'all' ? 'selected' : '' }}>Semua</option>
                        <option value="partial" {{ request('fulfillment_filter') === 'partial' ? 'selected' : '' }}>Partial saja</option>
                        <option value="full" {{ request('fulfillment_filter') === 'full' ? 'selected' : '' }}>Full saja</option>
                    </select>
                </div>
            </div>
        </fieldset>
        <div class="dashboard-filters-actions">
            <button type="submit" class="btn btn-primary">Terapkan</button>
            <a href="{{ route('dashboard', ['period' => 'week']) }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>
