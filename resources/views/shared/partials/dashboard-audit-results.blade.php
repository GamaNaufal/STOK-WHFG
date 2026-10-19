@php
    $auditTypeLabels = [
        'stock_input' => 'Input Stok', 'stock_withdrawal' => 'Pengambilan Stok',
        'delivery_pickup' => 'Delivery Pickup', 'delivery_redo' => 'Delivery Redo',
        'delivery_assignment' => 'Delivery Assignment', 'pallet_merged' => 'Pallet Merge',
        'box_pallet_moved' => 'Box Dipindahkan', 'other' => 'Lainnya',
    ];
@endphp
<section class="dashboard-audit-summary" aria-labelledby="dashboard-audit-summary-title">
    <h3 id="dashboard-audit-summary-title">Ringkasan aktivitas</h3>
    <p>Jumlah aktivitas per jenis untuk seluruh hasil sesuai filter, bukan hanya halaman ini.</p>
    @if($auditSummary->isNotEmpty())
        <dl class="dashboard-audit-counts">
            @foreach($auditSummary as $row)
                <div><dt>{{ $auditTypeLabels[$row->type] ?? $row->type }}</dt><dd>{{ number_format($row->total) }}</dd></div>
            @endforeach
        </dl>
    @else
        <p>Belum ada aktivitas yang sesuai filter.</p>
    @endif
</section>
<section class="dashboard-stock dashboard-audit-log" aria-labelledby="dashboard-audit-log-title">
    <header class="dashboard-stock-header">
        <div>
            <h2 id="dashboard-audit-log-title">Riwayat aktivitas</h2>
            <p>Aktivitas terbaru ditampilkan lebih dahulu. Petugas adalah operator atau admin yang melakukan aksi.</p>
        </div>
        <span class="dashboard-stock-count">{{ number_format($auditLogs->count()) }} baris di halaman ini</span>
    </header>
    @if($auditLogs->isEmpty())
        <div class="dashboard-stock-empty"><strong>Tidak ada aktivitas pada halaman ini.</strong><p>Periksa filter di atas atau gunakan navigasi halaman bila tersedia.</p></div>
    @else
        <p class="dashboard-stock-hint" id="dashboard-audit-log-help">Geser tabel untuk melihat semua kolom. Jenis aktivitas tetap terlihat; deskripsi ditampilkan lengkap.</p>
        <div class="dashboard-stock-scroll" tabindex="0" role="region" aria-labelledby="dashboard-audit-log-title" aria-describedby="dashboard-audit-log-help">
            <table class="table dashboard-stock-table dashboard-stock-table--audit mb-0">
                <caption class="visually-hidden">Riwayat audit sesuai filter, diurutkan dari aktivitas terbaru</caption>
                <colgroup><col style="width:16%"><col style="width:10%"><col style="width:15%"><col style="width:30%"><col style="width:14%"><col style="width:15%"></colgroup>
                <thead><tr><th scope="col">Jenis aktivitas</th><th scope="col">Aksi</th><th scope="col">Model</th><th scope="col">Deskripsi</th><th scope="col">Petugas</th><th scope="col">Tanggal</th></tr></thead>
                <tbody>
                    @foreach($auditLogs as $log)
                        <tr>
                            <th scope="row">{{ $auditTypeLabels[$log->type] ?? $log->type }}</th>
                            <td>{{ $log->action }}</td>
                            <td>{{ $log->model }}</td>
                            <td class="dashboard-audit-description">{{ $log->description }}</td>
                            <td>{{ $log->user?->name ?? 'User #' . $log->user_id }}</td>
                            <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@if(method_exists($auditLogs, 'links'))
    <div class="dashboard-audit-pagination">
        <p>Menampilkan {{ number_format($auditLogs->firstItem() ?? 0) }}–{{ number_format($auditLogs->lastItem() ?? 0) }} dari {{ number_format($auditLogs->total()) }} data</p>
        {{ $auditLogs->onEachSide(1)->fragment('audit-report')->links('pagination::bootstrap-5') }}
    </div>
@endif
