@extends('shared.layouts.app')
@section('title', 'Delivery Schedule Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-schedule.css') }}?v={{ filemtime(public_path('css/pages/export-delivery-schedule.css')) }}">
@endsection

@section('content')
<div class="delivery-plan-page export-schedule">
    <header class="plan-header">
        <div>
            <span class="plan-eyebrow">Pengiriman ekspor</span>
            <h1>Delivery Schedule Ekspor</h1>
            <p>Pilih nama customer pada tanggal batas pengiriman untuk melihat rincian delivery.</p>
        </div>
    </header>

    <form method="GET" action="{{ route('export.fulfillment.index') }}" id="schedule-filters" class="export-schedule-filters" aria-label="Filter jadwal ekspor">
        <div>
            <label class="form-label" for="schedule-month">Bulan</label>
            <input class="form-control" type="month" id="schedule-month" name="month" min="1000-01" max="9999-12" value="{{ $month->format('Y-m') }}">
        </div>
        <div class="export-schedule-search">
            <label class="form-label" for="schedule-search">Customer atau kode rencana</label>
            <input class="form-control" type="search" id="schedule-search" name="q" autocomplete="off" maxlength="100" value="{{ $search }}" placeholder="Cari customer atau kode">
            <ul class="export-schedule-suggestions" id="schedule-suggestions" role="listbox" aria-label="Saran delivery ekspor" hidden></ul>
        </div>
        <div>
            <label class="form-label" for="schedule-status">Status pemenuhan</label>
            <select class="form-select" id="schedule-status" name="status">
                <option value="">Semua status</option>
                @foreach($states as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="plan-inline-actions">
            <button class="btn btn-primary" type="submit">Tampilkan</button>
            <a class="btn btn-outline-secondary" id="schedule-reset" href="{{ route('export.fulfillment.index', ['month' => $month->format('Y-m')]) }}" @if($search === '' && $status === '') hidden @endif>Reset filter</a>
        </div>
    </form>
    <div class="export-schedule-feedback">
        <p id="schedule-feedback" class="visually-hidden" role="status"></p>
        <button type="button" id="schedule-retry" class="btn btn-sm btn-outline-secondary" hidden>Coba lagi</button>
    </div>

    <div id="schedule-results" tabindex="-1">
        @include('export.fulfillment.calendar')
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/pages/export-delivery-schedule.js') }}?v={{ filemtime(public_path('js/pages/export-delivery-schedule.js')) }}"></script>
@endsection
