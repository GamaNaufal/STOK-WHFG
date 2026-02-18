@extends('shared.layouts.app')

@section('title', 'Input Stok - Warehouse FG Yamato')

@section('content')
<div class="container-fluid">
    @include('operator.stock-input.partials.header')

    <div class="row g-4 mt-0">
        <!-- Main Content -->
        <div class="col-lg-8">
            <div class="card shadow" style="border: none; border-radius: 12px; overflow: hidden;">
                <div class="card-body" style="padding: 30px;">
                    @include('operator.stock-input.partials.scanner')
                    @include('operator.stock-input.partials.pallet')
                    @include('operator.stock-input.partials.location')
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/operator-stock-input.css') }}">
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    window.stockInputConfig = {
        csrfToken: '{{ csrf_token() }}',
        scanBarcodeUrl: '{{ route("stock-input.scan-barcode") }}',
        scanPartUrl: '{{ route("stock-input.scan-part") }}',
        getPalletDataUrl: '{{ route("stock-input.get-pallet-data") }}',
        clearSessionUrl: '{{ route("stock-input.clear-session") }}',
        storeUrl: '{{ route("stock-input.store") }}',
        indexUrl: '{{ route("stock-input.index") }}',
        locationSearchUrl: '/api/locations/search'
    };
</script>
<script src="{{ asset('js/pages/operator-stock-input.js') }}"></script>
@endsection