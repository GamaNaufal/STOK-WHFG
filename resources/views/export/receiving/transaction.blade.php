@extends('shared.layouts.app')
@section('title', 'Penempatan '.$receipt['code'])
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-receiving.css') }}?v={{ filemtime(public_path('css/pages/export-receiving.css')) }}">
@endsection
@section('content')
<div class="delivery-plan-page receiving-page">
    <header class="plan-header">
        <div><span class="plan-eyebrow">{{ !empty($receipt['notice_id']) ? 'Transaksi rekomendasi stok FIFO' : 'Penerimaan Transfer Box Ekspor' }}</span><h1>Penempatan {{ $receipt['code'] }}</h1><p>{{ $receipt['received_at']->format('d/m/Y H:i') }} · {{ $receipt['operator'] }}</p>@if(!empty($receipt['delivery']))<p>{{ $receipt['delivery']->plan_code }} · {{ $receipt['delivery']->customer_name }}</p>@endif</div>
        <div class="plan-inline-actions"><a class="btn btn-outline-primary" href="{{ route('export.receiving.index') }}#receiving-pending-queue">Kembali ke antrean</a><a class="btn btn-primary" href="{{ $receipt['print_url'] }}" target="_blank" rel="noopener" data-placement-guide-link="{{ $receipt['print_url'] }}">Cetak panduan</a></div>
    </header>
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Penempatan belum disimpan.</strong><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    <div class="fulfillment-summary"><div><span>Total box</span><strong>{{ $receipt['total'] }}</strong></div><div><span>Menunggu penempatan / pemindahan</span><strong>{{ $receipt['pending'] }}</strong></div><div><span>Sudah ditempatkan</span><strong>{{ $receipt['placed'] }}</strong></div>@if($receipt['redirected'])<div><span>Di transaksi rekomendasi</span><strong>{{ $receipt['redirected'] }}</strong></div>@endif</div>
    @if($receipt['pending'] === 0 && !$receipt['redirected'])<div class="alert alert-success" role="status">Seluruh box pada transaksi ini sudah ditempatkan.</div>@elseif($receipt['redirected'])<p class="alert alert-info">{{ $receipt['redirected'] }} box diproses melalui transaksi rekomendasi FIFO tersendiri. Gunakan tautan transaksi di bawah untuk picking dan penempatannya.</p>@else<p class="plan-help mb-3">Daftar box dikelompokkan sesuai tujuan pallet dan lokasi. Konfirmasikan setelah box benar-benar ditempatkan; penempatan dapat diselesaikan bertahap per kelompok.</p>@endif
    @if($groups->contains(fn($group)=>!$group['planned']&&$group['pending']->isNotEmpty()))
        <p class="plan-help mb-3">Box cadangan dikelompokkan per nomor part di dalam transaksi ini. Pilih satu tujuan atau bagi ke beberapa tujuan; pilihan tidak menjadi default untuk penerimaan berikutnya.</p>
        <form method="POST" action="{{ $receipt['print_url'] }}" target="_blank" data-spare-print-form hidden>@csrf<input type="hidden" name="spare_placements_json" value="[]"></form>
    @endif
    <h2 class="visually-hidden">Daftar box dan tujuan penempatan</h2>
    @foreach($groups as $group)@include('export.receiving.group')@endforeach
    @php
        $spareGroups=$groups->filter(fn($group)=>!$group['planned']&&$group['actionable']->isNotEmpty()&&!$group['picking_issue']);
    @endphp
    @if($spareGroups->count()>1)
        <form method="POST" action="{{ route('export.receiving.place-bulk') }}" class="plan-panel plan-panel-body" data-placement-form data-spare-confirm-all hidden>
            @csrf
            @if($batch)<input type="hidden" name="return_batch" value="{{ $batch->id }}">@elseif($returnBox)<input type="hidden" name="return_box" value="{{ $returnBox }}">@endif
            <input type="hidden" name="placements_json" value="[]">
            <p class="plan-help">Pilihan tiap part dapat dikonfirmasi sekaligus setelah seluruh box cadangan sudah diletakkan sesuai tujuan. Box yang mendapat alokasi rencana tetap dikonfirmasi pada kelompok tujuannya.</p>
            <button type="submit" class="btn btn-primary">Konfirmasi seluruh {{ $spareGroups->sum(fn($group)=>$group['actionable']->count()) }} box cadangan sudah ditempatkan</button>
        </form>
    @endif
</div>
@endsection
@section('scripts')@vite('resources/js/export-stock-receiving.js')@endsection
