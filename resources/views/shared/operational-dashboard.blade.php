@extends('shared.layouts.app')
@section('title', 'Dashboard Analitik - Warehouse FG')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/operational-dashboard.css') }}?v={{ filemtime(public_path('css/operational-dashboard.css')) }}">
@endsection
@section('content')
<div class="od-request-feedback" data-report-feedback role="status" aria-live="polite"><span data-report-message>Filter dashboard langsung diterapkan.</span><button type="button" class="btn btn-sm btn-outline-primary" data-report-retry hidden>Coba lagi</button></div>
<div data-report-browser>@include('shared.partials.operational-dashboard-content')</div>
@endsection
@section('scripts')
<script type="module" src="{{ asset('js/report-browser.js') }}?v={{ filemtime(public_path('js/report-browser.js')) }}"></script>
<script type="module" src="{{ asset('js/pages/operational-analytics.js') }}?v={{ filemtime(public_path('js/pages/operational-analytics.js')) }}"></script>
@endsection
