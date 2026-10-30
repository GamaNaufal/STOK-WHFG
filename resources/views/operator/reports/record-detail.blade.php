@extends('shared.layouts.app')
@section('title','Rincian Laporan')
@section('styles')@include('operator.reports.ajax-styles')@endsection
@section('content')
@include('operator.reports.ajax-feedback')
<div data-report-browser>
@include('operator.reports.partials.record-detail')
</div>
@endsection
@section('scripts')
<script type="module" src="{{ asset('js/report-browser.js') }}?v={{ filemtime(public_path('js/report-browser.js')) }}"></script>
@endsection
