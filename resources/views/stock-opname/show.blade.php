@extends('shared.layouts.app')
@section('title', $opname->code.' · Stock Opname '.$scopeLabel)
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/stock-opname.css') }}?v={{ filemtime(public_path('css/pages/stock-opname.css')) }}">
@endsection
@section('content')
<div class="delivery-plan-page opname-page" data-opname-page data-active="{{ $opname->status === 'active' ? 'true' : 'false' }}" data-detail-url="{{ route($base.'.show', $opname) }}">
    <a class="plan-back" href="{{ route($base.'.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> History opname {{ $scopeLabel }}</a>
    <header class="plan-header">
        <div><span class="plan-eyebrow">Stock opname {{ $scopeLabel }}</span><h1>{{ $opname->code }}</h1><p>{{ $opname->started_at->format('d/m/Y H:i') }} · {{ $opname->starter->name }}</p></div>
        <div class="plan-inline-actions">
            <span class="opname-scope">{{ $opname->status === 'active' ? 'Sesi aktif' : 'Selesai' }} · {{ $scopeLabel }}</span>
            @if($opname->status === 'completed')
                <a class="btn btn-outline-primary" href="{{ route($base.'.export', $opname) }}"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Export Excel</a>
            @endif
        </div>
    </header>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if($opname->status === 'completed')
        <p class="alert alert-success">Hasil disimpan {{ $opname->finished_at->format('d/m/Y H:i') }} oleh {{ $opname->finisher->name }}. History memakai kondisi stok saat sesi diselesaikan.</p>
    @endif
    <div class="opname-summary" aria-label="Ringkasan stock opname">
        @foreach(['baseline'=>'Stok awal', 'found'=>'Box sesuai', 'missing'=>'Belum terscan', 'ng'=>'Scan NG', 'arrivals'=>'Masuk selama sesi', 'departed'=>'Keluar / ID diganti'] as $key => $label)
            <div><span>{{ $label }}</span><strong data-opname-count="{{ $key }}">{{ number_format($summary[$key], 0, ',', '.') }}</strong></div>
        @endforeach
    </div>
    <div class="{{ $opname->status === 'active' && $canManage ? 'opname-workspace' : '' }}">
        @if($opname->status === 'active' && $canManage)
            <section class="plan-panel opname-scan-panel">
                <div class="plan-panel-heading"><h2>Scan ID Box · {{ $scopeLabel }}</h2></div>
                <div class="plan-panel-body">
                    <form method="POST" action="{{ route($base.'.scan', $opname) }}" data-opname-scan data-scan-feedback>
                        @csrf
                        <input type="hidden" name="request_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <label class="form-label" for="opname-box">ID Box (8 angka)</label>
                        <div class="opname-scan-field"><input id="opname-box" class="form-control" name="box_number" inputmode="numeric" maxlength="256" autocomplete="off" autofocus required aria-describedby="opname-scan-hint"><button type="submit" class="btn btn-primary"><i class="bi bi-upc-scan" aria-hidden="true"></i> Scan</button></div>
                        <p id="opname-scan-hint" class="plan-help">Tekan Enter atau gunakan scanner. Scan NG tetap dicatat dan Anda dapat melanjutkan box berikutnya.</p>
                    </form>
                    <div class="opname-feedback" data-opname-feedback role="status" aria-live="polite">
                        <strong data-opname-outcome>Siap scan</strong><span data-opname-message>Hasil setiap scan akan tersimpan pada sesi ini.</span>
                        <small data-opname-snapshot></small>
                    </div>
                    <p class="opname-queue-status" data-opname-queue role="status">Semua scan sudah tersimpan.</p>
                    <button type="button" class="btn btn-outline-primary" data-opname-retry hidden>Coba kirim lagi</button>
                    <form method="POST" action="{{ route($base.'.refresh', $opname) }}" class="mt-3" data-opname-action>
                        @csrf<button type="submit" class="btn btn-outline-primary w-100">Perbarui perbandingan stok</button>
                    </form>
                    <p class="plan-help mt-2">Stok masuk, keluar, dan perubahan lokasi dibandingkan dengan stok awal saat perbandingan diperbarui dan saat sesi selesai.</p>
                    <details class="opname-finish">
                        <summary>Selesaikan pemeriksaan</summary>
                        <form method="POST" action="{{ route($base.'.finish', $opname) }}" data-opname-action>
                            @csrf
                            <label class="opname-confirm"><input type="checkbox" name="confirmed" value="1" required><span>Semua operator sudah selesai memindai. Simpan hasil akhir, termasuk box yang belum terscan dan scan NG.</span></label>
                            <button type="submit" class="btn btn-primary w-100">Selesaikan sesi &amp; simpan hasil</button>
                        </form>
                    </details>
                </div>
            </section>
        @endif
        <section class="plan-panel">
            <div class="opname-results" data-opname-results>@include('stock-opname.results')</div>
        </section>
    </div>
    <p class="plan-help">Jumlah box dihitung unik. Duplikat dicatat terpisah. Box yang sudah keluar atau berganti ID selama sesi ditampilkan pada perubahan, terpisah dari box belum terscan. Pemeriksaan ID Box mencocokkan keberadaan box; part, PCS, dan lokasi pada rincian mengikuti catatan sistem.</p>
</div>
@endsection
@section('scripts')
<script src="{{ asset('js/scan-feedback.js') }}?v={{ filemtime(public_path('js/scan-feedback.js')) }}" data-ok="{{ asset('audio/OK.mp3') }}" data-ng="{{ asset('audio/NG.mp3') }}"></script>
@vite('resources/js/stock-opname.js')
@endsection
