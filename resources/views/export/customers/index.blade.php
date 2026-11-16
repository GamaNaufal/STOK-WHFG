@extends('shared.layouts.app')
@section('title', 'Master Customer Ekspor')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">@endsection
@section('content')
<div class="delivery-plan-page">
    <header class="plan-header"><div><span class="plan-eyebrow">Master Data Ekspor</span><h1>Master customer</h1><p>Identitas bersama untuk penerimaan box, reservasi stok dan rencana delivery Ekspor.</p></div>
        @if($ready && auth()->user()->canAccess('export_customers_manage'))<a class="btn btn-primary" href="{{ route('export.customers.create') }}">Tambah customer</a>@endif
    </header>
    @if(!$ready)<div class="alert alert-info">Master customer belum tersedia. Stok dan delivery existing tetap mengikuti aturan sebelumnya.</div>
    @else
    <section class="plan-panel"><div class="plan-panel-body"><form method="GET"><label for="customer-search" class="form-label">Cari nama, kode atau alias</label><div class="d-flex gap-2"><input id="customer-search" name="q" class="form-control" value="{{ $q }}" maxlength="100"><button class="btn btn-outline-primary">Cari</button></div></form></div>
        <div class="plan-table-scroll"><table class="table plan-table"><thead><tr><th>Kode</th><th>Nama customer</th><th>Alias Excel</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @forelse($customers as $customer)<tr><td>{{ $customer->code }}</td><th scope="row">{{ $customer->name }}</th><td>{{ $customer->aliases->where('kind', 'alias')->pluck('label')->join(', ') ?: 'Belum ada alias tambahan' }}</td><td>{{ $customer->is_active ? 'Aktif' : 'Nonaktif, histori dipertahankan' }}</td><td>@if(auth()->user()->canAccess('export_customers_manage'))<a class="btn btn-sm btn-outline-primary" href="{{ route('export.customers.edit', $customer) }}">Ubah</a>@endif</td></tr>
        @empty<tr><td colspan="5" class="plan-empty">Belum ada customer yang cocok. Tambahkan master sebelum memetakan stok.</td></tr>@endforelse
        </tbody></table></div><div class="plan-panel-body">{{ $customers->links('pagination::bootstrap-5') }}<p class="plan-help">Nama dan alias dicocokkan secara pasti. Nama yang mirip perlu diperiksa, tidak membuat master baru otomatis.</p></div>
    </section>
    @endif
</div>
@endsection
