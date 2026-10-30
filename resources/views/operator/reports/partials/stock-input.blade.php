<header class="stock-input-report-header">
    <div class="stock-input-report-intro">
        <span class="stock-input-report-eyebrow">Laporan stok</span>
        <h1>Laporan Input Stok</h1>
        <p>Telusuri catatan stok masuk berdasarkan tanggal, nomor part, dan lokasi gudang.</p>
    </div>
    <div class="stock-input-report-export">
        <span>Ekspor mengikuti filter yang sedang diterapkan.</span>
        <a data-report-export href="{{ route('reports.stock-input.export', array_filter($filters)) }}" class="btn btn-outline-primary">
            <i class="bi bi-download" aria-hidden="true"></i> Export Excel
        </a>
    </div>
</header>
@include('operator.reports.scope-nav', ['kind'=>'receipts'])

<section class="stock-input-report-overview mb-4" aria-labelledby="stock-input-overview-title">
    <div class="stock-input-report-overview-heading">
        <div>
            <span class="stock-input-report-eyebrow">Ringkasan</span>
            <h2 id="stock-input-overview-title">Seluruh histori input</h2>
        </div>
        <p>Angka ringkasan tidak berubah saat filter diterapkan.</p>
    </div>
    <div class="stock-input-report-metrics">
        <dl class="stock-input-report-metric stock-input-report-metric-primary">
            <dt><i class="bi bi-stack" aria-hidden="true"></i> Total PCS Masuk</dt>
            <dd><span>{{ number_format($totalItems) }}</span><small>PCS</small></dd>
        </dl>
        <dl class="stock-input-report-metric">
            <dt><i class="bi bi-journal-check" aria-hidden="true"></i> Total Pencatatan Input</dt>
            <dd><span>{{ number_format($totalRecords) }}</span><small>pencatatan</small></dd>
        </dl>
        <dl class="stock-input-report-metric">
            <dt><i class="bi bi-box" aria-hidden="true"></i> Total Box</dt>
            <dd><span>{{ number_format((int) $totalBoxes) }}</span><small>box</small></dd>
        </dl>
    </div>
</section>

<!-- Filter Form -->
@php
    $activeFilterCount = collect($filters)
        ->filter(fn ($value) => is_scalar($value) && filled($value))
        ->count();
@endphp
<section class="card stock-input-report-filters mb-4" aria-labelledby="stock-input-filter-title">
    <header class="stock-input-report-filter-heading">
        <div>
            <h2 id="stock-input-filter-title">Filter catatan</h2>
            <p>Filter membatasi tabel dan file Excel; angka ringkasan di atas mencakup seluruh histori.</p>
        </div>
        <span>{{ $activeFilterCount }} filter aktif</span>
    </header>
    <div class="card-body">
        <form data-report-filter-form method="GET" action="{{ route('reports.stock-input') }}" class="stock-input-report-filter-grid">
            <div class="stock-input-report-field">
                <label for="stock-input-report-start" class="form-label">Dari tanggal</label>
                <input id="stock-input-report-start" type="date" class="form-control" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
            </div>
            <div class="stock-input-report-field">
                <label for="stock-input-report-end" class="form-label">Sampai tanggal</label>
                <input id="stock-input-report-end" type="date" class="form-control" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
            </div>
            <div class="stock-input-report-field">
                <label for="stock-input-report-part" class="form-label">Nomor part</label>
                <input id="stock-input-report-part" type="text" class="form-control" name="part_number" placeholder="Cari nomor part" value="{{ $filters['part_number'] ?? '' }}">
            </div>
            <div class="stock-input-report-field">
                <label for="stock-input-report-location" class="form-label">Lokasi gudang</label>
                <select id="stock-input-report-location" class="form-select" name="warehouse_location">
                    <option value="">Semua lokasi</option>
                    @foreach($locations as $location)
                        <option value="{{ $location }}" {{ $filters['warehouse_location'] === $location ? 'selected' : '' }}>
                            {{ $location }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="stock-input-report-filter-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> Terapkan</button>
                <a data-report-reset href="{{ route('reports.stock-input') }}" class="btn btn-outline-secondary">Atur ulang</a>
            </div>
        </form>
    </div>
</section>

<section class="stock-input-report-results" aria-labelledby="stock-input-results-title">
    <header class="stock-input-report-results-heading">
        <div>
            <h2 id="stock-input-results-title">Hasil input stok</h2>
            <p>Menampilkan {{ $stockInputs->firstItem() ?? 0 }}–{{ $stockInputs->lastItem() ?? 0 }} dari {{ number_format($stockInputs->total()) }} pencatatan sesuai filter.</p>
        </div>
        <span id="stock-input-table-hint"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> Geser tabel untuk melihat semua kolom</span>
    </header>
    @if($stockInputs->count() > 0)
        <div class="table-responsive stock-input-report-table-scroll" tabindex="0" role="region" aria-label="Tabel hasil input stok" aria-describedby="stock-input-table-hint">
            <table class="table table-hover stock-input-report-table mb-0">
                <caption class="visually-hidden">Riwayat input stok sesuai filter, termasuk ID dan status audit box.</caption>
                <thead>
                    <tr>
                        <th scope="col" class="stock-input-report-entry-cell">Catatan input</th>
                        <th scope="col">Pallet</th>
                        <th scope="col">Part Number</th>
                        <th scope="col">ID Box</th>
                        <th scope="col" class="stock-input-report-number">Box</th>
                        <th scope="col" class="stock-input-report-number">PCS</th>
                        <th scope="col">Lokasi</th>
                        <th scope="col">Operator</th>
                    </tr>
                </thead>
                <tbody>
                    @php $counter = $stockInputs->firstItem(); @endphp
                    @foreach($stockInputs as $input)
                        <tr>
                            <th scope="row" class="stock-input-report-entry-cell">
                                <span class="stock-input-report-entry-number">#{{ $counter }}</span>
                                <span class="stock-input-report-entry-date">{{ $input->stored_at->format('d/m/Y') }}</span>
                                <span class="stock-input-report-entry-time">{{ $input->stored_at->format('H:i') }}</span>
                            </th>
                            <td class="stock-input-report-pallet">{{ $input->pallet?->pallet_number ?? '-' }}</td>
                            <td class="stock-input-report-part-cell">
                                @php
                                    $partSummaries = collect($input->part_summaries ?? []);
                                    $partText = $partSummaries->isNotEmpty()
                                        ? $partSummaries->map(function ($summary) {
                                            return ($summary['part_number'] ?? '-') . ' (' . ((int) ($summary['box_quantity'] ?? 0)) . ' box)';
                                        })->implode(', ')
                                        : '-';
                                @endphp
                                <span class="stock-input-report-part-label">{{ $partText }}</span>
                            </td>
                            <td class="stock-input-report-box-cell">
                                @php $boxAuditItems = collect($input->box_audit_items ?? []); @endphp
                                @if($boxAuditItems->isNotEmpty())
                                    <div class="stock-input-report-box-list">
                                        @foreach($boxAuditItems as $boxAudit)
                                            @php
                                                $status = $boxAudit['status'] ?? 'active';
                                                $isDeletedNotShipped = $status === 'deleted_not_shipped';
                                                $isDeletedShipped = $status === 'deleted_shipped';
                                            @endphp
                                            <span class="stock-input-report-box-tag {{ $isDeletedNotShipped ? 'is-deleted-unshipped' : ($isDeletedShipped ? 'is-deleted-shipped' : '') }}">{{ $boxAudit['label'] ?? '-' }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="stock-input-report-muted">–</span>
                                @endif
                            </td>
                            <td class="stock-input-report-number">{{ (int) $input->box_quantity }}</td>
                            <td class="stock-input-report-number stock-input-report-pcs">{{ $input->pcs_quantity }} <span>PCS</span></td>
                            <td class="stock-input-report-secondary">{{ $input->warehouse_location }}</td>
                            <td class="stock-input-report-secondary">{{ $input->user->name }}</td>
                        </tr>
                        @php $counter++; @endphp
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="stock-input-report-pagination">
            {{ $stockInputs->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
    @else
        <div class="stock-input-report-empty">
            <i class="bi bi-inbox" aria-hidden="true"></i>
            <h3>Tidak ada data input stok</h3>
            <p>{{ $activeFilterCount > 0 ? 'Tidak ada pencatatan yang sesuai dengan filter saat ini.' : 'Belum ada pencatatan input stok.' }}</p>
            @if($activeFilterCount > 0)
                <a data-report-reset href="{{ route('reports.stock-input') }}" class="btn btn-outline-secondary">Atur ulang filter</a>
            @endif
        </div>
    @endif
</section>
