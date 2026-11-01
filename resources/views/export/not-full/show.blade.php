@extends('shared.layouts.app')
@section('title','Rincian Box Not Full Ekspor')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">@endsection
@section('content')
@php
    $canPrepare=in_array(auth()->user()->role,['admin','admin_warehouse']) && auth()->user()->canAccess('not_full_request');
    $canApprove=in_array(auth()->user()->role,['admin','supervisi']) && auth()->user()->canAccess('not_full_approval');
    $covered=$target && $target->fulfillmentBoxes()->exists();
    $p=$record->preparation;
@endphp
<div class="delivery-plan-page">
    @include('shared.not-full-feedback')
    <a class="plan-back" href="{{ route($canPrepare?'box-not-full.create':'box-not-full.approvals',['scope'=>'export']) }}">Box Not Full Ekspor</a>
    <header class="plan-header"><div><h1>{{ $record->part_number }} · Not Full {{ $record->pcs_quantity }} PCS</h1><p>{{ $record->delivery->plan_code ?: 'RD-'.$record->export_delivery_plan_id }} · {{ $record->delivery->customer_name }}</p><p><strong>{{ $state }}</strong></p></div></header>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="alert {{ $target ? 'alert-info' : 'alert-warning' }}">
        @if($target)Tujuan wajib: <strong>Pallet {{ $target->pallet->position }} · {{ $target->pallet->location_code }}</strong>. Kebutuhan 1 box {{ $record->pcs_quantity }} PCS, standar {{ $record->fixed_qty }} PCS/box.
        @else Penyiapan menunggu perencanaan pallet final dengan alokasi Not Full yang sesuai. Kebutuhan ini tetap wajib dipenuhi.
        @endif
    </div>
    @if($covered)<div class="alert alert-success">{{ $state==='Terpenuhi' ? 'Box sudah tercatat sebagai stok dan penempatannya pada pallet rencana sudah dikonfirmasi. Tidak perlu penerimaan atau scan ulang.' : 'Box yang sesuai sudah direservasi. Ikuti antrean untuk konfirmasi penempatan; tidak perlu membuat box baru.' }}</div>@endif
    @if($record->notes)<p>Catatan: {{ $record->notes }}</p>@endif
    @if($record->stockBox)<p>Box hasil approval: <strong>{{ $record->stockBox->box_number }}</strong> · {{ $record->stockBox->pcs_quantity }} PCS. Disetujui {{ $record->approver?->name }}.</p>@endif
    @if($p)
    <section class="plan-panel"><div class="plan-panel-body"><h2>Data yang disiapkan</h2><p>ID baru <strong>{{ $p['box_number'] }}</strong> · {{ $p['mode']==='split'?'Ambil dari box penuh di stok':'Datang sudah dikemas Not Full' }} · {{ $record->pcs_quantity }} PCS</p>
        @if($p['mode']==='split')<p>Box sumber <strong>{{ $p['source_box_number'] }}</strong> · lokasi {{ $p['source_location'] }}. Sisa <strong>{{ $record->fixed_qty-$record->pcs_quantity }} PCS</strong> tetap pada ID dan lokasi sumber. Tanggal FIFO kedua box mengikuti sumber.</p>@endif
        <p>Disiapkan {{ $record->preparer?->name }}. {{ $p['notes'] }}</p>
    </div></section>
    @endif
    @if($canPrepare && $target && !$covered && $record->status!=='cancelled')
    <section class="plan-panel"><div class="plan-panel-body"><h2>Siapkan data Not Full</h2>
        <p>Reservasi box penuh tetap diprioritaskan. Sumber FIFO diperiksa ulang saat approval; jika sudah terikat permintaan lain, penyiapan wajib diperbarui.</p>
        @if($source)<p>Sumber penuh tertua yang tersedia: <strong>{{ $source->box_number }}</strong> · {{ $source->pcs_quantity }} PCS · {{ $source->currentPallet->location_code }} · diterima {{ $source->first_received_at->format('d/m/Y H:i') }}.</p>
        @else<div class="alert alert-warning">Tidak ada box penuh yang bebas untuk part ini. Opsi pemecahan belum tersedia. Kebutuhan penuh yang sudah direservasi tetap dipertahankan.</div>@endif
        <form method="POST" action="{{ route('box-not-full.export.prepare',$record) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}">
            <label class="form-label" for="nf-mode">Asal box</label><select class="form-select mb-3" id="nf-mode" name="mode" required><option value="external" @selected(old('mode',$p['mode']??'external')==='external')>Datang sudah dikemas Not Full {{ $record->pcs_quantity }} PCS</option><option value="split" @disabled(!$source) @selected(old('mode',$p['mode']??'external')==='split' && $source)>Ambil dari box penuh di stok · FIFO</option></select>
            <label class="form-label" for="nf-box">ID box baru (8 angka)</label><input class="form-control mb-3" id="nf-box" name="box_number" type="text" maxlength="8" pattern="[0-9]{8}" inputmode="numeric" value="{{ old('box_number',$p['box_number']??'') }}" required autocomplete="off">
            <label class="form-label" for="nf-notes">Catatan penyiapan</label><textarea class="form-control mb-3" id="nf-notes" name="notes" maxlength="1000" minlength="3" required>{{ old('notes',$p['notes']??'') }}</textarea>
            <button class="btn btn-primary" type="submit">Simpan penyiapan untuk approval</button>
        </form>
    </div></section>
    @endif
    @if($canApprove && $record->status==='prepared' && $target && !$covered)
    <section class="plan-panel"><div class="plan-panel-body"><h2>Approval Admin Utama / Supervisi</h2><p>Persetujuan langsung mencatat stok dan konfirmasi penempatan ke pallet rencana. Box hasil tidak perlu diterima/scan ulang.</p>
        <form method="POST" action="{{ route('box-not-full.export.approve',$record) }}" class="mb-4">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="nf-confirm" name="physical_confirmed" value="1" required><label class="form-check-label" for="nf-confirm">Saya memastikan identitas, jumlah PCS, sumber (jika dipecah), dan penempatan fisik sesuai rincian pada pallet tujuan.</label></div><button class="btn btn-primary" type="submit">Setujui dan catat stok / penempatan</button></form>
        <form method="POST" action="{{ route('box-not-full.export.reject',$record) }}">@csrf<input type="hidden" name="revision" value="{{ $record->revision }}"><label class="form-label" for="nf-reject">Alasan penolakan</label><textarea class="form-control mb-3" id="nf-reject" name="reason" minlength="3" maxlength="1000" required></textarea><button class="btn btn-outline-danger" type="submit">Tolak penyiapan</button></form>
    </div></section>
    @endif
</div>
@endsection
@section('scripts')<script>document.getElementById('nf-box')?.addEventListener('input',event=>{event.target.value=event.target.value.replace(/[^0-9]/g,'').slice(0,8);});</script>@endsection
