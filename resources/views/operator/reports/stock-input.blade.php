@extends('shared.layouts.app')

@section('title', 'Laporan Input Stok - Warehouse FG Yamato')

@section('styles')@include('operator.reports.ajax-styles')@endsection

@section('content')
@include('operator.reports.ajax-feedback')
<div data-report-browser>
@include('operator.reports.partials.stock-input')
</div>
@endsection
@section('scripts')
<script type="module" src="{{ asset('js/report-browser.js') }}?v={{ filemtime(public_path('js/report-browser.js')) }}"></script>
@endsection
