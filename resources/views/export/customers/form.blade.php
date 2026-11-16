@extends('shared.layouts.app')
@section('title', $customer ? 'Ubah Customer Ekspor' : 'Tambah Customer Ekspor')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">@endsection
@section('content')
<div class="delivery-plan-page">
    <header class="plan-header"><div><a class="plan-back" href="{{ route('export.customers.index') }}">Kembali ke master customer</a><span class="plan-eyebrow">Master Data Ekspor</span><h1>{{ $customer ? 'Ubah customer' : 'Tambah customer' }}</h1><p>Nama lama pada histori delivery tetap dipertahankan.</p></div></header>
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $customer ? route('export.customers.update', $customer) : route('export.customers.store') }}" class="plan-panel">@csrf @if($customer)@method('PUT')<input type="hidden" name="revision" value="{{ $customer->revision }}">@endif
        <div class="plan-panel-body"><div class="row g-3"><div class="col-md-4"><label for="customer-code" class="form-label">Kode customer</label><input id="customer-code" name="code" class="form-control" maxlength="100" value="{{ old('code', $customer?->code) }}" required></div><div class="col-md-8"><label for="customer-name" class="form-label">Nama resmi</label><input id="customer-name" name="name" class="form-control" maxlength="255" value="{{ old('name', $customer?->name) }}" required></div>
        <div class="col-12"><label for="customer-aliases" class="form-label">Alias pada dokumen Excel</label><textarea id="customer-aliases" name="aliases" class="form-control" rows="4" maxlength="20000" aria-describedby="customer-alias-help">{{ old('aliases', $customer?->aliases?->where('kind', 'alias')->pluck('label')->join("\n")) }}</textarea><p class="plan-help" id="customer-alias-help">Satu alias per baris. Kode, nama dan alias harus unik antarcustomer. Perbedaan kapitalisasi dan spasi tidak membuat identitas baru.</p></div></div>
        <input type="hidden" name="is_active" value="0"><div class="form-check my-3"><input id="customer-active" name="is_active" type="checkbox" class="form-check-input" value="1" @checked(old('is_active', $customer?->is_active ?? true))><label for="customer-active" class="form-check-label">Aktif untuk pemetaan dan rencana baru</label></div><p class="plan-help">Nonaktif tidak membatalkan komitmen delivery atau melepaskan reservasi yang sudah ada.</p><button type="submit" class="btn btn-primary">Simpan customer</button></div>
    </form>
</div>
@endsection
