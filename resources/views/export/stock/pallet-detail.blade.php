@extends('shared.layouts.app')
@section('title', 'Isi Pallet Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-receiving.css') }}">
@endsection
@section('content')
<div class="delivery-plan-page receiving-page">
    <header class="plan-header"><div><h1>{{ $physicalPallet?->display_name ?? 'Belum ditempatkan' }}</h1><p>{{ $physicalPallet?->location_code }}</p></div><a class="btn btn-outline-primary" href="{{ route('export.stock.index', array_merge($filters, ['mode' => 'pallet'])) }}">Kembali ke stok</a></header>
    <section class="plan-panel">@include('export.stock.pallet-boxes')</section>
</div>
@include('export.stock.box-modal')
@endsection
@section('scripts')@vite('resources/js/export-stock-receiving.js')@endsection
