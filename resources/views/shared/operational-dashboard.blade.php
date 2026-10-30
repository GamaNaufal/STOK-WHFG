@extends('shared.layouts.app')
@section('title', 'Dashboard Operasional - Warehouse FG')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/operational-dashboard.css') }}?v={{ filemtime(public_path('css/operational-dashboard.css')) }}">
@endsection
@section('content')
@include('operator.reports.ajax-feedback')
<div data-report-browser>@include('shared.partials.operational-dashboard-content')</div>
@endsection
@section('scripts')<script type="module" src="{{ asset('js/report-browser.js') }}?v={{ filemtime(public_path('js/report-browser.js')) }}"></script>@endsection
