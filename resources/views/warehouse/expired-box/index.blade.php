@extends('shared.layouts.app')
@section('title', 'Expired Box dan Handle')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/expired-box.css') }}?v={{ filemtime(public_path('css/pages/expired-box.css')) }}">
<link rel="stylesheet" href="{{ asset('css/pages/warehouse-scope-switch.css') }}">
@endsection
@section('content')
<div class="expired-page" id="expired-workspace">@include('warehouse.expired-box.workspace')</div>
@endsection
@section('scripts')
<script src="{{ asset('js/expired-box-workspace.js') }}?v={{ filemtime(public_path('js/expired-box-workspace.js')) }}" defer></script>
@endsection
