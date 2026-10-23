<div class="table-responsive" tabindex="0" role="region" aria-label="{{ $tableLabel }}">
    <table class="table expired-page-table mb-0">
        <thead><tr><th scope="col" class="expired-page-first-cell">ID box</th><th scope="col">Part / PCS</th><th scope="col">Umur saat ini</th><th scope="col">Tanggal masuk asli</th><th scope="col">Pallet / lokasi</th></tr></thead>
        <tbody>
        @forelse($inventoryBoxes as $box)
            <tr>
                <td class="expired-page-first-cell"><strong>{{ $box->box_number }}</strong></td>
                <td>{{ $box->part_number }}<small class="d-block text-muted">{{ number_format($box->pcs_quantity) }} PCS</small></td>
                <td>@include('warehouse.expired-box.box-age')</td>
                <td>{{ $box->stored_at ? \Illuminate\Support\Carbon::parse($box->stored_at)->format('d M Y H:i') : '—' }}</td>
                <td>{{ $box->pallet_number ?? 'Tanpa pallet aktif' }}<small class="d-block text-muted">{{ $box->warehouse_location ?? 'Lokasi tidak aktif' }}</small></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center py-4">{{ $emptyMessage }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<p class="expired-page-scroll-hint d-md-none">Geser tabel ke samping untuk melihat umur, tanggal masuk, dan lokasi.</p>
