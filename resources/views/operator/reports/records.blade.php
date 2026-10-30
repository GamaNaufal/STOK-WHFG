@extends('shared.layouts.app')
@section('title','Laporan Operasional')
@section('styles')@include('operator.reports.ajax-styles')@endsection
@section('content')
@include('operator.reports.ajax-feedback')
<div data-report-browser>
@include('operator.reports.partials.records')
</div>
@endsection
@section('scripts')
<script type="module" src="{{ asset('js/report-browser.js') }}?v={{ filemtime(public_path('js/report-browser.js')) }}"></script>
@endsection
