@extends('shared.layouts.app')

@section('styles')
    <style>
        .approvals-page {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 2.5rem;
            padding-top: 1rem;
        }

        .page-header h1 {
            color: #1f2937;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #6b7280;
            font-size: 0.95rem;
        }

        /* Approval Card Styling */
        .approval-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: none;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .approval-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .approval-card .card-header {
            background: linear-gradient(135deg, #0C7779 0%, #005461 100%);
            color: white;
            padding: 1.25rem;
            border: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .approval-card .card-header .customer-name {
            font-weight: 600;
            font-size: 1.05rem;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .approval-card .card-header .badge {
            background: #fbbf24 !important;
            color: #92400e !important;
            padding: 0.5rem 0.875rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 1rem;
            flex-shrink: 0;
        }

        .approval-card .card-body {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .order-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e9f0;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            color: #52636e;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: #1f2937;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .notes-box {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .notes-box small {
            color: #92400e;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* Items & Stock Table */
        .items-section {
            margin-bottom: 1.5rem;
        }

        .items-section h6 {
            color: #0C7779;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .items-table {
            font-size: 0.85rem;
            border-collapse: collapse;
        }

        .items-table thead {
            background: #f0f8f7;
            border-bottom: 2px solid #b3e5db;
        }

        .items-table thead th {
            color: #0C7779;
            font-weight: 600;
            padding: 0.75rem 0.5rem;
            text-align: center;
            border: none;
        }

        .items-table tbody td {
            padding: 0.75rem 0.5rem;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }

        .items-table tbody td:first-child {
            text-align: left;
            font-weight: 600;
            color: #1f2937;
        }

        .items-table .status-ok {
            color: #059669;
            font-weight: 600;
        }

        .items-table .status-warning {
            color: #dc2626;
            font-weight: 600;
        }

        .items-table .badge-ok {
            background: #dcfce7;
            color: #166534;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .items-table .badge-warning {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: auto;
        }

        .btn-approve {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
            color: white;
        }

        .btn-correction {
            background: #fbbf24;
            color: #92400e;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            flex: 1;
        }

        .btn-correction:hover {
            background: #f59e0b;
            color: white;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            flex: 1;
        }

        .btn-reject:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
            transform: translateY(-2px);
        }

        .btn-group-horizontal {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        /* Empty State */
        .empty-state {
            background: white;
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 4rem;
            color: #10b981;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: #1f2937;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #52636e;
        }

        /* History Section */
        .history-section {
            margin-top: 3rem;
        }

        .history-header {
            color: #1f2937;
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .history-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: none;
            overflow: hidden;
        }

        .history-table {
            margin-bottom: 0;
        }

        .history-table thead {
            background: #f0f8f7;
            border-bottom: 2px solid #b3e5db;
        }

        .history-table thead th {
            color: #0C7779;
            font-weight: 600;
            padding: 1rem 0.75rem;
            border: none;
        }

        .history-table tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s ease;
        }

        .history-table tbody tr:hover {
            background: #f8fffe;
        }

        .history-table tbody td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        .empty-history {
            padding: 2rem;
            text-align: center;
            color: #52636e;
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }

        .modal-header {
            border-bottom: 1px solid #e9ecef;
            padding: 1.5rem;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1.5rem;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/pages/ppc-approval-queue.css') }}">
@endsection

@section('content')
<div class="page-header ppc-approval-page-header">
    <div>
        <span class="ppc-eyebrow">PPC / Keputusan delivery</span>
        <h1>Antrean persetujuan</h1>
        <p>Periksa kebutuhan part dan ketersediaan sesuai urutan delivery sebelum memutuskan.</p>
    </div>
    <span class="ppc-queue-count"><strong>{{ $pendingOrders->count() }}</strong> menunggu keputusan</span>
</div>

<div class="container-fluid ppc-approvals-page">
    @if($pendingOrders->isEmpty())
        <div class="empty-state ppc-queue-empty" role="status">
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <h2>Tidak ada pesanan menunggu</h2>
            <p>Pesanan baru dari Sales akan muncul di antrean ini.</p>
        </div>
    @else
        <div class="ppc-queue-intro">
            <span>Urutan kerja</span>
            <p>Tanggal delivery terdekat lebih dulu. Angka siap saat giliran memperhitungkan kebutuhan order sebelumnya.</p>
        </div>
        <div class="row g-3 ppc-queue-grid">
            @foreach($pendingOrders as $order)
            <div class="col-12 col-xl-6">
                <article class="approval-card ppc-approval-card" aria-labelledby="ppc-order-{{ $order->id }}">
                    <div class="card-header">
                        <div class="ppc-order-heading">
                            <span>Order #{{ $order->id }}</span>
                            <h2 id="ppc-order-{{ $order->id }}">{{ $order->customer_name }}</h2>
                        </div>
                        <span class="ppc-pending-badge"><i class="bi bi-clock" aria-hidden="true"></i> Menunggu PPC</span>
                    </div>
                    <div class="card-body">
                        <dl class="ppc-order-meta">
                            <div>
                                <dt>Sales</dt>
                                <dd>{{ $order->salesUser->name ?? 'Tidak diketahui' }}</dd>
                            </div>
                            <div>
                                <dt>Tanggal delivery</dt>
                                <dd><time datetime="{{ $order->delivery_date->format('Y-m-d') }}">{{ $order->delivery_date->format('d M Y') }}</time></dd>
                            </div>
                        </dl>

                        @if($order->notes)
                        <div class="ppc-order-note">
                            <strong><i class="bi bi-chat-left-text" aria-hidden="true"></i> Catatan pada order</strong>
                            <p>{{ $order->notes }}</p>
                        </div>
                        @endif

                        <section class="ppc-stock-section" aria-labelledby="ppc-stock-title-{{ $order->id }}">
                            <div class="ppc-stock-section-heading">
                                <h3 id="ppc-stock-title-{{ $order->id }}">Kebutuhan &amp; stok</h3>
                                <span>{{ $order->items->count() }} part</span>
                            </div>
                            @if($order->items->isNotEmpty())
                                <ul class="ppc-stock-list">
                                    @foreach($order->items as $item)
                                        <li class="ppc-stock-item">
                                            <div class="ppc-stock-item-heading">
                                                <strong>{{ $item->part_number }}</strong>
                                                <span class="ppc-stock-status {{ $item->stock_warning ? 'is-short' : 'is-ready' }}">
                                                    <i class="bi {{ $item->stock_warning ? 'bi-exclamation-circle' : 'bi-check-circle' }}" aria-hidden="true"></i>
                                                    {{ $item->stock_warning ? 'Belum cukup' : 'Cukup' }}
                                                </span>
                                            </div>
                                            <div class="ppc-stock-values">
                                                <div>
                                                    <span>Siap / kebutuhan</span>
                                                    <strong class="{{ $item->stock_warning ? 'is-short' : '' }}">{{ number_format($item->remaining_before ?? 0, 0, ',', '.') }} / {{ number_format($item->quantity, 0, ',', '.') }} <small>PCS</small></strong>
                                                </div>
                                                <div>
                                                    <span>Total stok</span>
                                                    <strong>{{ number_format($item->available_total ?? 0, 0, ',', '.') }} <small>PCS</small></strong>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="ppc-stock-empty">Tidak ada item pada pesanan ini.</p>
                            @endif
                        </section>

                        <div class="action-buttons ppc-queue-actions" role="group" aria-label="Keputusan untuk Order #{{ $order->id }}">
                            <span>Pilih keputusan</span>
                            <button class="btn-approve js-open-status-modal" data-order-id="{{ $order->id }}" data-status="approved" type="button">
                                <i class="bi bi-check-circle" aria-hidden="true"></i> Setujui
                            </button>
                            <div class="btn-group-horizontal">
                                <button class="btn-correction js-open-status-modal" data-order-id="{{ $order->id }}" data-status="correction" type="button">
                                    <i class="bi bi-pencil" aria-hidden="true"></i> Minta koreksi
                                </button>
                                <button class="btn-reject js-open-status-modal" data-order-id="{{ $order->id }}" data-status="rejected" type="button">
                                    <i class="bi bi-x-circle" aria-hidden="true"></i> Tolak
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
            @endforeach
        </div>
    @endif

    <section class="history-section ppc-history-section" aria-labelledby="ppc-history-title">
        <div class="ppc-history-heading">
            <div>
                <span class="ppc-eyebrow">Aktivitas PPC</span>
                <h2 id="ppc-history-title">Riwayat persetujuan</h2>
                <p>Hingga 15 order terbaru yang telah diputuskan atau diproses.</p>
            </div>
            <span class="ppc-history-count">{{ $historyOrders->count() }} ditampilkan</span>
        </div>
        <div class="history-card ppc-history-card">
            @if($historyOrders->isEmpty())
                <div class="empty-history ppc-history-empty">
                    <i class="bi bi-inbox" aria-hidden="true"></i>
                    <strong>Belum ada riwayat persetujuan</strong>
                    <span>Keputusan PPC dan pembaruan order akan muncul di sini.</span>
                </div>
            @else
                <div class="table-responsive ppc-history-scroll" tabindex="0" role="region" aria-label="Riwayat persetujuan PPC, geser untuk melihat semua kolom">
                    <table class="table history-table ppc-history-table">
                        <caption class="visually-hidden">Pembaruan terakhir, pelanggan, status, catatan, dan rincian dari hingga 15 order terbaru.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Order / pembaruan terakhir</th>
                                <th scope="col">Pelanggan / Sales</th>
                                <th scope="col">Status</th>
                                <th scope="col">Catatan &amp; rincian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($historyOrders as $order)
                                @php
                                    $historyStatus = match ($order->status) {
                                        'approved' => ['Disetujui', 'approved', 'bi-check-circle'],
                                        'rejected' => ['Ditolak', 'rejected', 'bi-x-circle'],
                                        'correction' => ['Perlu koreksi', 'correction', 'bi-pencil-square'],
                                        'completed' => ['Selesai', 'completed', 'bi-check2-all'],
                                        'processing' => ['Dalam proses', 'processing', 'bi-arrow-repeat'],
                                        default => [ucfirst(str_replace('_', ' ', $order->status)), 'neutral', 'bi-circle'],
                                    };
                                @endphp
                                <tr class="ppc-history-main-row">
                                    <th scope="row" class="ppc-history-order-cell">
                                        <strong>Order #{{ $order->id }}</strong>
                                        <time datetime="{{ $order->updated_at->toIso8601String() }}">
                                            <span>{{ $order->updated_at->format('d M Y') }}</span>
                                            <span>{{ $order->updated_at->format('H:i') }}</span>
                                        </time>
                                    </th>
                                    <td class="ppc-history-customer-cell">
                                        <strong>{{ $order->customer_name }}</strong>
                                        <span>Sales: {{ $order->salesUser->name ?? 'Tidak diketahui' }}</span>
                                    </td>
                                    <td>
                                        <span class="ppc-history-status is-{{ $historyStatus[1] }}">
                                            <i class="bi {{ $historyStatus[2] }}" aria-hidden="true"></i>{{ $historyStatus[0] }}
                                        </span>
                                    </td>
                                    <td class="ppc-history-note-cell">
                                        <p>{{ $order->notes ? Str::limit($order->notes, 88) : 'Tidak ada catatan' }}</p>
                                        <div class="ppc-history-actions">
                                            <span>{{ $order->items->count() }} item</span>
                                            <button class="btn btn-outline-secondary ppc-history-detail-toggle" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#history-detail-{{ $order->id }}"
                                                aria-controls="history-detail-{{ $order->id }}" aria-expanded="false">
                                                <i class="bi bi-list-ul" aria-hidden="true"></i>
                                                <span class="ppc-history-toggle-open">Lihat rincian</span>
                                                <span class="ppc-history-toggle-close">Tutup rincian</span>
                                                <span class="visually-hidden"> Order #{{ $order->id }}</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="ppc-history-detail-row">
                                    <td colspan="4">
                                        <div class="collapse ppc-history-detail" id="history-detail-{{ $order->id }}">
                                            <div class="ppc-history-detail-heading">
                                                <strong>Rincian Order #{{ $order->id }}</strong>
                                                <span>{{ $order->items->count() }} nomor part</span>
                                            </div>
                                            <div class="ppc-history-full-note">
                                                <strong>Catatan order</strong>
                                                <p>{{ $order->notes ?: 'Tidak ada catatan pada order ini.' }}</p>
                                            </div>
                                            <strong class="ppc-history-items-label">Item pesanan</strong>
                                            @if($order->items->isNotEmpty())
                                            <ul class="ppc-history-item-list">
                                                @foreach($order->items as $item)
                                                    <li><span>{{ $item->part_number }}</span><strong>{{ number_format($item->quantity, 0, ',', '.') }} PCS</strong></li>
                                                @endforeach
                                            </ul>
                                            @else
                                                <p class="ppc-history-no-items">Tidak ada item pada order ini.</p>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
</div>

<div class="modal fade ppc-decision-modal" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form id="statusForm" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="status" id="modalStatusInput">
            <div class="modal-header">
                <div>
                    <span class="ppc-decision-eyebrow">Keputusan PPC</span>
                    <h2 class="modal-title" id="statusModalLabel">Tinjau pesanan</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup dialog"></button>
            </div>
            <div class="modal-body">
                <div class="ppc-decision-order-context">
                    <span id="statusOrderNumber"></span>
                    <strong id="statusOrderCustomer"></strong>
                    <time id="statusOrderDate"></time>
                </div>
                <p id="statusConfirmationText" class="ppc-decision-description"></p>
                <section id="impactPanel" class="ppc-impact-panel" aria-live="polite" aria-labelledby="impactTitle" hidden>
                    <div class="ppc-impact-heading">
                        <i id="impactIcon" class="bi" aria-hidden="true"></i>
                        <div>
                            <h3 id="impactTitle"></h3>
                            <p id="impactDescription"></p>
                        </div>
                    </div>
                    <ul id="impactList" class="ppc-impact-list" hidden></ul>
                    <button type="button" id="impactRetry" class="btn btn-outline-secondary ppc-impact-retry" hidden>
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Periksa lagi
                    </button>
                </section>
                <div class="ppc-decision-note">
                    <label class="form-label" for="statusNotes" id="statusNotesLabel">Catatan PPC</label>
                    <p id="statusNoteHelp">Catatan wajib diisi sebelum keputusan dikirim.</p>
                    <textarea id="statusNotes" name="notes" class="form-control" rows="3"
                        aria-describedby="statusNoteHelp statusNoteError" required></textarea>
                    <p id="statusNoteError" class="ppc-note-error" role="alert" hidden></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary" id="statusSubmitButton">Konfirmasi</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
    <script src="{{ asset('js/pages/ppc-approval-decision.js') }}"></script>
@endsection
