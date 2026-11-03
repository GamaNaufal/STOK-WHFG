@extends('shared.layouts.app')
@section('title', 'Hasil Merge Pallet Ekspor')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}"><link rel="stylesheet" href="{{ asset('css/pages/export-merge.css') }}">@endsection
@section('content')
<div class="delivery-plan-page export-merge-page"><header class="mb-4"><p class="text-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Merge berhasil</p><h1>Pallet sudah digabung</h1></header>
    <div class="merge-destination"><div><small>Hasil merge</small><h2>{{ $result['pallet_number'] ? 'Pallet '.$result['pallet_number'] : 'Pallet cadangan' }} · Lokasi {{ $result['location_code'] }}</h2><span>{{ $result['box_quantity'] }} box @if($result['plan_code']) · {{ $result['plan_code'] }} · Revisi {{ $result['plan_revision'] }}@endif</span></div></div>
    <p class="mt-3">ID Box, PCS aktual dan tanggal penerimaan FIFO dipertahankan. Histori sumber dan perubahan rencana tercatat di audit trail.</p>
    @if($result['delivery_id'])<p class="alert alert-info">Tinjau ulang alokasi FIFO jika masih ada yang belum dikonfirmasi. Box yang wajib verifikasi tetap menunggu scan dan konfirmasi penempatan di antrean.</p>@endif
    @if($result['ready_cancelled'])<div class="alert alert-warning">Konfirmasikan ulang status siap pada Pemenuhan Permintaan setelah seluruh kebutuhan terpenuhi.</div>@endif
    <div class="plan-inline-actions my-4"><a class="btn btn-primary" href="{{ route('export.merge.index') }}">Kembali ke Merge Pallet</a>
    @if($result['delivery_id'])
        @if(auth()->user()->canAccess('export_pallet_plans'))<a class="btn btn-outline-primary" href="{{ route('export.pallet-plans.show',$result['delivery_id']) }}">Lihat rencana terbaru</a><a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('export.pallet-plans.print',$result['delivery_id']) }}">Cetak pallet terbaru</a>@endif
        @if(auth()->user()->canAccess('export_fulfillment'))<a class="btn btn-outline-primary" href="{{ route('export.fulfillment.show',$result['delivery_id']) }}">Pemenuhan Permintaan</a>@endif
    @endif
    @if(auth()->user()->canAccess('export_stock_input'))<a class="btn btn-outline-secondary" href="{{ route('export.stock.index') }}">Stok Expor</a>@endif</div>
    <p class="plan-help">Ini adalah catatan hasil merge pada saat konfirmasi. Gunakan halaman stok/rencana untuk posisi terkini dan panduan dengan revisi terbaru.</p>
</div>
@endsection
