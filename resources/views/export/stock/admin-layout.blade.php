@extends('shared.layouts.app')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-receiving.css') }}">
@endsection

@section('content')
<div class="delivery-plan-page receiving-page">
    <a class="plan-back" href="@yield('stock-back', route('export.stock.index'))"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke stok ekspor</a>
    @yield('stock-header')
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif
    @yield('stock-body')
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/pages/export-stock-admin.js') }}?v={{ filemtime(public_path('js/pages/export-stock-admin.js')) }}" defer></script>
@endsection
