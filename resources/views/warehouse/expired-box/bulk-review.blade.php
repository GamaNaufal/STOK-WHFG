@extends('shared.layouts.app')
@php
    $titles = ['start' => 'Buat transaksi penanganan', 'qa' => 'Catat hasil QA beberapa box', 'packing' => 'Serahkan beberapa box ke packing', 'receive' => 'Terima beberapa box dari packing'];
@endphp
@section('title', $titles[$action])
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/expired-box.css') }}">
@endsection
@section('content')
<div class="expired-page">
    <header class="expired-page-header"><a href="{{ route('expired-box.index') }}">← Kembali ke daftar</a><h1 class="mt-3">{{ $titles[$action] }}</h1><p>{{ $records->count() }} box dipilih. Box ini akan tetap berada dalam satu transaksi sampai proses selesai.</p></header>
    <div class="alert alert-info">Seluruh pilihan disimpan bersama. Jika satu box gagal validasi, tidak ada tindakan pada pilihan ini yang disimpan.</div>
    @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Perbaiki isian berikut; seluruh pilihan belum disimpan.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @include('warehouse.expired-box.transaction-form')

</div>
@endsection
@section('scripts')
<script src="{{ asset('js/expired-box-bulk.js') }}" defer></script>
@endsection
