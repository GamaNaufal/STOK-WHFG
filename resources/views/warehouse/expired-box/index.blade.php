@extends('shared.layouts.app')

@section('title', 'Expired Box')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/expired-box.css') }}?v={{ filemtime(public_path('css/pages/expired-box.css')) }}">
@endsection

@section('content')
@php
    $historyCount = method_exists($handledHistory, 'total') ? $handledHistory->total() : $handledHistory->count();
@endphp
<div id="expired-box-page" class="expired-page" data-csrf-token="{{ csrf_token() }}">
    <header class="expired-page-header">
        <span class="expired-page-eyebrow">Kesehatan stok</span>
        <h1>Expired Box</h1>
        <p>Pantau box yang mendekati batas umur, aktifkan kembali box expired yang layak, dan periksa riwayat Handle.</p>
    </header>

    <section class="expired-page-overview" aria-label="Ringkasan status box">
        <dl class="expired-page-metric is-expired">
            <dt><i class="bi bi-exclamation-octagon" aria-hidden="true"></i> Box expired</dt>
            <dd><span>{{ number_format($expiredBoxes->count()) }}</span><small>perlu ditinjau</small></dd>
        </dl>
        <dl class="expired-page-metric is-warning">
            <dt><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Mendekati batas</dt>
            <dd><span>{{ number_format($warningBoxes->count()) }}</span><small>box warning</small></dd>
        </dl>
        <dl class="expired-page-metric">
            <dt><i class="bi bi-clock-history" aria-hidden="true"></i> Riwayat Handle</dt>
            <dd><span>{{ number_format($historyCount) }}</span><small>kejadian</small></dd>
        </dl>
    </section>

    <div class="expired-page-guidance" aria-label="Aturan status box">
        <p><strong>Warning:</strong> Box belum expired, tidak perlu Handle. Tetap dapat masuk FIFO.</p>
        <p><strong>Expired:</strong> Box tidak masuk FIFO sampai kondisi fisik diperiksa dan Handle berhasil.</p>
        <p><strong>Setelah Handle:</strong> Aktif selama satu bulan kalender. Saat masa aktif habis, lakukan Handle ulang sebelum FIFO.</p>
    </div>

    <section class="expired-page-search" aria-labelledby="expired-search-title" data-search-ui hidden>
        <div>
            <h2 id="expired-search-title">Cari box yang dipantau</h2>
            <p>Pencarian berlaku untuk daftar expired dan warning di halaman ini; riwayat tidak terpengaruh.</p>
        </div>
        <div class="expired-page-search-controls">
            <div class="expired-page-search-field">
                <label for="expired-box-search">Box, part, pallet, atau lokasi</label>
                <div class="expired-page-search-input">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="expired-box-search" type="search" autocomplete="off" placeholder="Cari pada daftar aktif" data-box-search>
                </div>
            </div>
            <button type="button" class="btn btn-outline-secondary" data-clear-search>Atur ulang</button>
        </div>
    </section>

    <section class="expired-page-panel" aria-labelledby="expired-list-title">
        <header class="expired-page-panel-heading">
            <div>
                <span class="expired-page-section-kicker is-expired">Perlu peninjauan</span>
                <h2 id="expired-list-title">Expired / masa Handle habis</h2>
                <p>Verifikasi kondisi box, pilih lokasi kosong bila perlu, lalu Handle. Box yang masih di-assign atau terkunci delivery akan ditolak oleh sistem.</p>
            </div>
            <span class="expired-page-panel-count" data-visible-count="expired" aria-live="polite">{{ $expiredBoxes->count() }} box</span>
        </header>
        <div class="table-responsive expired-page-scroll" tabindex="0" role="region" aria-label="Daftar box expired" aria-describedby="expired-list-hint">
            <table class="table table-hover expired-page-table expired-page-table-active mb-0">
                <caption class="visually-hidden">Box expired atau masa Handle habis yang perlu ditinjau untuk aktivasi ulang.</caption>
                <thead>
                    <tr>
                        <th scope="col" class="expired-page-first-cell">Box</th>
                        <th scope="col">Part</th>
                        <th scope="col">Pallet</th>
                        <th scope="col">Lokasi</th>
                        <th scope="col">Umur dan status</th>
                        <th scope="col">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expiredBoxes as $box)
                        @php
                            $age = \App\Support\BoxAgeFormatter::format($box->stored_at);
                            $handleElapsed = $box->reactivated_until && \Illuminate\Support\Carbon::parse($box->reactivated_until)->lte(now());
                        @endphp
                        <tr data-filter-row="expired" data-search="{{ $box->box_number }} {{ $box->part_number }} {{ $box->pallet_number ?? '' }} {{ $box->warehouse_location ?? '' }}">
                            <th scope="row" class="expired-page-first-cell">{{ $box->box_number }}</th>
                            <td>{{ $box->part_number }}</td>
                            <td>{{ $box->pallet_number ?? '–' }}</td>
                            <td><span class="expired-page-location">{{ $box->warehouse_location ?? 'Belum ada lokasi' }}</span></td>
                            <td>
                                <span class="expired-page-status is-expired">{{ $handleElapsed ? 'Masa Handle habis' : 'Expired' }}</span>
                                <span class="expired-page-age">{{ $age }}</span>
                                @if($handleElapsed)
                                    <span class="expired-page-detail">Aktif sampai {{ \Illuminate\Support\Carbon::parse($box->reactivated_until)->format('d M Y H:i') }}</span>
                                @endif
                            </td>
                            <td class="expired-page-action-cell">
                                @if(!$box->warehouse_location || $box->warehouse_location === 'Unknown')
                                    <label for="handle-location-{{ $box->id }}">Lokasi tujuan</label>
                                    <select id="handle-location-{{ $box->id }}" class="form-select js-handle-location" data-box-id="{{ $box->id }}">
                                        <option value="">Pilih lokasi kosong</option>
                                        @foreach($availableLocations as $location)
                                            <option value="{{ $location->id }}">{{ $location->code }}</option>
                                        @endforeach
                                    </select>
                                    @if($availableLocations->isEmpty())
                                        <small>Belum ada lokasi kosong. Hubungi admin untuk menyiapkannya.</small>
                                    @endif
                                @endif
                                <button type="button" class="btn btn-primary js-handle-btn" data-box-id="{{ $box->id }}" data-box-number="{{ $box->box_number }}" data-age="{{ $age }}" data-handle-url="{{ route('expired-box.handle', $box->id) }}" title="Aktifkan selama 1 bulan">
                                    <i class="bi bi-check-circle" aria-hidden="true"></i> Handle 1 bulan
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="expired-page-table-empty">Tidak ada box yang sudah expired.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p id="expired-list-hint" class="expired-page-scroll-hint"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> Geser tabel untuk melihat status dan tindakan.</p>
        <p class="expired-page-filter-empty" data-filter-empty="expired" hidden>Tidak ada box expired yang cocok dengan pencarian.</p>
    </section>

    <section class="expired-page-panel" aria-labelledby="warning-list-title">
        <header class="expired-page-panel-heading">
            <div>
                <span class="expired-page-section-kicker is-warning">Tetap eligible FIFO</span>
                <h2 id="warning-list-title">Warning (9–12 bulan)</h2>
                <p>Box belum expired, tidak perlu Handle. Tetap dapat masuk FIFO sesuai tanggal penyimpanan asli.</p>
            </div>
            <span class="expired-page-panel-count" data-visible-count="warning" aria-live="polite">{{ $warningBoxes->count() }} box</span>
        </header>
        <div class="table-responsive expired-page-scroll" tabindex="0" role="region" aria-label="Daftar box warning" aria-describedby="warning-list-hint">
            <table class="table table-hover expired-page-table expired-page-table-warning mb-0">
                <caption class="visually-hidden">Box warning yang tetap dapat masuk FIFO tanpa Handle.</caption>
                <thead>
                    <tr>
                        <th scope="col" class="expired-page-first-cell">Box</th>
                        <th scope="col">Part</th>
                        <th scope="col">Pallet</th>
                        <th scope="col">Lokasi</th>
                        <th scope="col">Umur</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($warningBoxes as $box)
                        <tr data-filter-row="warning" data-search="{{ $box->box_number }} {{ $box->part_number }} {{ $box->pallet_number ?? '' }} {{ $box->warehouse_location ?? '' }}">
                            <th scope="row" class="expired-page-first-cell">{{ $box->box_number }}</th>
                            <td>{{ $box->part_number }}</td>
                            <td>{{ $box->pallet_number ?? '–' }}</td>
                            <td><span class="expired-page-location">{{ $box->warehouse_location ?? 'Belum ada lokasi' }}</span></td>
                            <td><span class="expired-page-status is-warning">{{ \App\Support\BoxAgeFormatter::format($box->stored_at) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="expired-page-table-empty">Tidak ada box dengan status warning.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p id="warning-list-hint" class="expired-page-scroll-hint"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> Geser tabel untuk melihat semua kolom.</p>
        <p class="expired-page-filter-empty" data-filter-empty="warning" hidden>Tidak ada box warning yang cocok dengan pencarian.</p>
    </section>

    <section class="expired-page-panel" aria-labelledby="history-title">
        <header class="expired-page-panel-heading">
            <div>
                <span class="expired-page-section-kicker">Jejak audit</span>
                <h2 id="history-title">Riwayat Handle / aktivasi</h2>
                <p>Riwayat mencatat tindakan yang pernah terjadi; tanggal “Aktif Sampai” bukan jaminan box masih aktif saat ini.</p>
            </div>
            <span class="expired-page-panel-count">{{ number_format($historyCount) }} kejadian</span>
        </header>
        <div class="table-responsive expired-page-scroll" tabindex="0" role="region" aria-label="Riwayat Handle box expired" aria-describedby="history-hint">
            <table class="table table-hover expired-page-table expired-page-table-history mb-0">
                <caption class="visually-hidden">Riwayat aktivasi box expired dan tanggal masa aktifnya.</caption>
                <thead>
                    <tr>
                        <th scope="col" class="expired-page-first-cell">Box</th>
                        <th scope="col">Part</th>
                        <th scope="col">Pallet</th>
                        <th scope="col">Lokasi</th>
                        <th scope="col">Stored At</th>
                        <th scope="col">Umur</th>
                        <th scope="col">Handled By</th>
                        <th scope="col">Handled At</th>
                        <th scope="col">Aktif Sampai</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($handledHistory as $row)
                        <tr>
                            <th scope="row" class="expired-page-first-cell">{{ $row->box_number }}</th>
                            <td>{{ $row->part_number }}</td>
                            <td>{{ $row->pallet_number ?? '–' }}</td>
                            <td><span class="expired-page-location">{{ $row->warehouse_location ?? '–' }}</span></td>
                            <td>{{ optional($row->stored_at)->format('d M Y') ?? '–' }}</td>
                            <td>{{ \App\Support\BoxAgeFormatter::format($row->stored_at, $row->handled_at ?? $row->created_at) }}</td>
                            <td>{{ $row->handler?->name ?? '–' }}</td>
                            <td>{{ optional($row->handled_at)->format('d M Y H:i') ?? '–' }}</td>
                            <td class="expired-page-history-until">{{ $row->reactivated_until?->format('d M Y H:i') ?? 'Penanganan lama' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="expired-page-table-empty">Belum ada riwayat penanganan box expired.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p id="history-hint" class="expired-page-scroll-hint"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> Geser tabel untuk melihat tanggal dan pelaksana Handle.</p>
        @if(method_exists($handledHistory, 'links') && $handledHistory->hasPages())
            <div class="expired-page-pagination">{{ $handledHistory->links('pagination::bootstrap-5') }}</div>
        @endif
    </section>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/pages/expired-box.js') }}?v={{ filemtime(public_path('js/pages/expired-box.js')) }}"></script>
@endsection
