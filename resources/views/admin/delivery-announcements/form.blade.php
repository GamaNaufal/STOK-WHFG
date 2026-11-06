@extends('admin.delivery-announcements.layout')
@section('title', $announcement ? 'Edit Pengumuman Delivery' : 'Buat Pengumuman Delivery')
@section('announcement-content')
<a class="plan-back" href="{{ $announcement ? route('delivery-announcements.show', $announcement) : route('delivery-announcements.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ $announcement ? 'Kembali ke detail' : 'Daftar pengumuman' }}</a>
<header class="plan-header">
    <div><span class="plan-eyebrow">Lokal dan Ekspor</span><h1>{{ $announcement ? 'Edit pengumuman' : 'Buat pengumuman' }}</h1><p>Instruksi akan muncul di keterangan delivery yang sesuai.</p></div>
</header>
@if($errors->any())
    <div class="alert alert-danger" role="alert"><strong>Periksa isian berikut.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<form method="POST" class="plan-panel announcement-form" action="{{ $announcement ? route('delivery-announcements.update', $announcement) : route('delivery-announcements.store') }}">
    @csrf
    @if($announcement) @method('PATCH') @endif
    <div class="plan-panel-body">
        @if($announcement)
            <div class="announcement-match mb-4">
                <strong>{{ $announcement->customer_name }}</strong><span>Part {{ $announcement->part_number }} · Kuota per {{ $announcement->unit }}</span>
                <p class="plan-help mb-0">Customer, nomor part, dan jenis kuota ditetapkan saat pengumuman diterbitkan.</p>
            </div>
        @else
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="announcement-customer" class="form-label">Nama customer</label>
                    <input id="announcement-customer" class="form-control" name="customer_name" value="{{ old('customer_name') }}" list="announcement-customers" placeholder="Contoh EURONAK" maxlength="255" required aria-describedby="announcement-customer-help">
                    <datalist id="announcement-customers">@foreach($customers as $customer)<option value="{{ $customer }}"></option>@endforeach</datalist>
                    <p class="plan-help mb-0" id="announcement-customer-help">Gunakan nama yang tercantum di delivery. Huruf besar dan kecil dianggap sama.</p>
                </div>
                <div class="col-md-6">
                    <label for="announcement-part" class="form-label">Nomor part</label>
                    <input id="announcement-part" class="form-control" name="part_number" value="{{ old('part_number') }}" list="announcement-parts" placeholder="Cari nomor part" maxlength="100" required>
                    <datalist id="announcement-parts">@foreach($parts as $part)<option value="{{ $part }}"></option>@endforeach</datalist>
                </div>
            </div>
        @endif
        <div class="mb-4">
            <label class="form-label" for="announcement-message">Instruksi untuk operator</label>
            <textarea class="form-control" rows="4" name="message" id="announcement-message" maxlength="2000" placeholder="Contoh Tempelkan stiker hijau pada sisi depan setiap box." required>{{ old('message', $announcement?->message) }}</textarea>
        </div>
        @unless($announcement)
            <fieldset class="mb-4">
                <legend class="form-label">Kuota dihitung berdasarkan</legend>
                <div class="announcement-options">
                    @foreach(['box' => ['Jumlah box', 'Instruksi berlaku untuk ID Box yang terpilih. Kuota dipakai saat box dikirim.'], 'delivery' => ['Jumlah delivery', 'Instruksi berlaku untuk semua box part ini. Kuota dipakai saat delivery aktif.']] as $type => [$title, $help])
                        <label class="announcement-option">
                            <input class="form-check-input" type="radio" name="quota_type" value="{{ $type }}" @checked(old('quota_type', 'box') === $type) required>
                            <span><strong>{{ $title }}</strong><small>{{ $help }}</small></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endunless
        <div class="announcement-quota mb-4">
            <label class="form-label" for="announcement-quota">{{ $announcement ? 'Batas jumlah '.$announcement->unit : 'Jumlah kuota' }}</label>
            <input class="form-control" type="number" name="quota" id="announcement-quota" min="1" max="1000000" step="1" value="{{ old('quota', $announcement?->quota) }}" required aria-describedby="announcement-quota-help">
            <p class="plan-help mb-0" id="announcement-quota-help">Jatah kembali jika delivery atau alokasi box dibatalkan.</p>
        </div>
        <button type="submit" class="btn btn-primary">{{ $announcement ? 'Simpan perubahan' : 'Terbitkan pengumuman' }}</button>
    </div>
</form>
@endsection
