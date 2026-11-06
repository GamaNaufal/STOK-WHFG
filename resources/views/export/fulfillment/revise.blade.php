@extends('shared.layouts.app')

@section('title', 'Revisi Pallet Ekspor')
@section('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
@endsection

@section('content')
<div class="delivery-plan-page fulfillment-page">
    <header class="plan-header">
        <div>
            <a class="plan-back" href="{{ route('export.fulfillment.show', $delivery) }}">← Detail delivery</a>
            <span class="plan-eyebrow">Revisi pallet ekspor</span>
            <h1>Pallet {{ $pallet->position }} · {{ $pallet->location_code }}</h1>
            <p>{{ $delivery->plan_code }} · {{ $delivery->customer_name }}</p>
        </div>
    </header>
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if($pendingFifo && !$preview)
        <div class="alert alert-warning">Ada alokasi FIFO sebelumnya yang menunggu konfirmasi. <a href="{{ route('export.pallet-plans.show', ['deliveryPlan' => $delivery, 'review_stock' => 1]) }}">Tinjau alokasi FIFO</a> sebelum menyimpan tambahan yang memakai stok.</div>
    @endif

    @unless($preview)
        <form method="POST" action="{{ route('export.fulfillment.revise', [$delivery, $pallet]) }}" class="plan-panel" id="revision-editor">
            @csrf
            <input type="hidden" name="revision" value="{{ $delivery->palletPlan->revision }}">
            <input type="hidden" name="fulfillment_revision" value="{{ $pallet->fulfillment_revision }}">
            <div class="plan-panel-heading revision-heading">
                <div>
                    <h2>Jumlah box setelah revisi</h2>
                    <p>Isi target baru per part. Perubahan ini juga memperbarui kebutuhan rencana delivery.</p>
                </div>
                <x-export-pallet-load :boxes="$items->sum('box_quantity')" aria-live="polite" />
            </div>
            <div class="plan-panel-body">
                <div class="revision-parts">
                    @foreach($items as $item)
                        <div class="revision-part">
                            <div>
                                <label for="quantity-{{ $item->id }}" class="form-label">{{ $item->part_number }}</label>
                                <span class="revision-part-meta">{{ number_format($item->pcs_per_box, 0, ',', '.') }} PCS/box · {{ $item->box_quantity }} box saat ini</span>
                                <span class="revision-part-meta">{{ $reserved->get($item->id, 0) }} direservasi · {{ $placed->get($item->id, 0) }} sudah ditempatkan</span>
                            </div>
                            <div class="revision-quantity">
                                <input id="quantity-{{ $item->id }}" aria-label="Jumlah baru untuk {{ $item->part_number }}, {{ $item->pcs_per_box }} PCS per box" class="form-control" type="number" inputmode="numeric" min="0" max="4294967295" required name="quantities[{{ $item->id }}]" value="{{ old('quantities.'.$item->id, $quantities[$item->id]) }}" data-revision-quantity>
                            </div>
                        </div>
                    @endforeach
                    <div id="revision-added-parts" class="revision-parts"></div>
                </div>
                @if($catalog->isNotEmpty())
                    <details class="revision-part-picker" id="revision-part-picker">
                        <summary>Tambah nomor part</summary>
                        <div class="mt-3">
                            <label class="form-label" for="revision-part-search">Cari part dari master</label>
                            <input id="revision-part-search" class="form-control" type="search" placeholder="Ketik nomor part" autocomplete="off" aria-controls="revision-part-results">
                            <p class="plan-help mb-2 mt-2" id="revision-part-status" role="status"></p>
                            <div class="revision-part-results" id="revision-part-results" role="group" aria-label="Hasil pencarian part"></div>
                        </div>
                    </details>
                    <script type="application/json" id="revision-part-data">@json($catalog)</script>
                    <script type="application/json" id="revision-added-data">@json(old('additions', $additions))</script>
                    <template id="revision-part-template">
                        <div class="revision-part">
                            <div>
                                <label class="form-label" data-part-label></label>
                                <span class="revision-part-meta" data-part-capacity></span>
                            </div>
                            <div class="revision-quantity">
                                <input class="form-control" type="number" inputmode="numeric" min="1" max="4294967295" required data-revision-quantity>
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-remove-part>Hapus</button>
                            </div>
                        </div>
                    </template>
                @endif
                <p class="plan-help mt-3">Target 0 menghapus baris dari pallet. Jika seluruh target 0, pallet dihapus. Permintaan dibatalkan jika pallet terakhir dihapus.</p>
                @if($pallet->ready_at)
                    <p class="alert alert-warning py-2">Status siap pallet ini akan dibuka agar isi dan lokasinya dapat dikonfirmasi ulang.</p>
                @endif
                <button class="btn btn-primary" type="submit">Tinjau revisi</button>
            </div>
        </form>
        @if($pallet->ready_at)
            <section class="plan-panel">
                <div class="plan-panel-body">
                    <h2 class="fs-5">Koreksi kesiapan atau lokasi</h2>
                    <p>Buka ulang status siap untuk mengonfirmasi lokasi lagi. Jumlah box tetap sama.</p>
                    <form method="POST" action="{{ route('export.fulfillment.reopen', [$delivery, $pallet]) }}">
                        @csrf
                        <input type="hidden" name="revision" value="{{ $delivery->palletPlan->revision }}">
                        <input type="hidden" name="fulfillment_revision" value="{{ $pallet->fulfillment_revision }}">
                        <input type="hidden" name="request_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <button class="btn btn-outline-secondary" type="submit">Buka ulang kesiapan pallet</button>
                    </form>
                </div>
            </section>
        @endif
    @else
        <section class="plan-panel">
            <div class="plan-panel-heading revision-heading">
                <div><h2>Tinjauan revisi</h2><p>Perubahan jumlah diterapkan ke pallet dan rencana delivery saat disimpan.</p></div>
                <x-export-pallet-load :boxes="$preview['total']" />
            </div>
            <div class="plan-table-scroll" tabindex="0" role="region" aria-label="Perubahan kebutuhan per part">
                <table class="table plan-table revision-table">
                    <thead><tr><th scope="col">Nomor part</th><th scope="col">Sebelum</th><th scope="col">Target baru</th><th scope="col">Dampak</th></tr></thead>
                    <tbody>
                    @foreach($preview['changes'] as $change)
                        @php($delta = $change['after'] - $change['before'])
                        <tr>
                            <th scope="row">{{ $change['part_number'] }}<span class="revision-part-meta">{{ number_format($change['pcs'], 0, ',', '.') }} PCS/box</span></th>
                            <td>{{ $change['before'] }} box</td>
                            <td><strong>{{ $change['after'] }} box</strong></td>
                            <td>
                                @if($delta > 0)
                                    <strong>{{ $change['candidates']->count() }} box FIFO tersedia</strong>
                                    @if($change['candidates']->count() < $delta)
                                        <span class="fulfillment-row-warning">{{ $delta - $change['candidates']->count() }} box tambahan menunggu stok</span>
                                    @endif
                                @else
                                    Kurangi {{ -$delta }} box
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($preview['recommended'])
                <details class="plan-panel-body revision-fifo">
                    <summary>Lihat {{ $preview['recommended'] }} rekomendasi FIFO</summary>
                    <ul class="mt-2 mb-0">
                        @foreach(collect($preview['changes'])->flatMap(fn ($row) => $row['candidates'])->take(100) as $box)
                            <li>{{ $box->box_number }} · {{ $box->part_number }} · {{ $box->currentPallet?->location_code ?? 'Belum ditempatkan' }}</li>
                        @endforeach
                    </ul>
                    @if($preview['recommended'] > 100)<p class="plan-help mb-0">100 box pertama ditampilkan. Daftar lengkap tersedia di antrean setelah disimpan.</p>@endif
                </details>
            @endif
            @if($preview['added'] > $preview['recommended'])
                <div class="plan-panel-body"><p class="alert alert-warning mb-0">{{ $preview['added'] - $preview['recommended'] }} box tambahan masih menunggu stok yang sesuai. Revisi tetap dapat disimpan.</p></div>
            @endif
            @if($preview['difference'])
                <div class="plan-panel-body">
                    <h3 class="fs-6">Pengurangan box</h3>
                    <p class="mb-0"><strong>{{ $preview['returns']->count() }} box wajib dikembalikan</strong> · {{ $preview['released']->count() }} reservasi dilepas. Box terbaru dipilih terlebih dahulu.</p>
                </div>
                @include('export.fulfillment.return-list', ['returns' => $preview['returns'], 'released' => $preview['released']])
            @endif
            <form method="POST" action="{{ route('export.fulfillment.reduce', [$delivery, $pallet]) }}" class="plan-panel-body">
                @csrf
                <input type="hidden" name="revision" value="{{ $delivery->palletPlan->revision }}">
                <input type="hidden" name="fulfillment_revision" value="{{ $pallet->fulfillment_revision }}">
                <input type="hidden" name="fingerprint" value="{{ $preview['fingerprint'] }}">
                <input type="hidden" name="request_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                @foreach($quantities as $id => $quantity)<input type="hidden" name="quantities[{{ $id }}]" value="{{ $quantity }}">@endforeach
                @foreach($additions as $id => $quantity)<input type="hidden" name="additions[{{ $id }}]" value="{{ $quantity }}">@endforeach
                @if($preview['difference'])
                    @include('export.fulfillment.return-fields', ['returns' => $preview['returns']])
                @endif
                @if($preview['total'] === 0)
                    <p class="alert alert-warning">Pallet ini akan dihapus. Jika ini pallet terakhir, permintaan dibatalkan.</p>
                @endif
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-secondary" type="submit" name="edit" value="1" formaction="{{ route('export.fulfillment.revise', [$delivery, $pallet]) }}" formnovalidate>Ubah jumlah</button>
                    <button class="btn btn-primary" type="submit">{{ $preview['recommended'] ? 'Simpan dan masukkan stok FIFO ke antrean' : 'Simpan revisi' }}</button>
                </div>
            </form>
        </section>
    @endunless
</div>
@endsection

@section('scripts')
    <script src="{{ asset('js/export-fulfillment-revision.js') }}" defer></script>
@endsection
