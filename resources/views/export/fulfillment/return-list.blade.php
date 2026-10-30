@if($returns->isNotEmpty() || $released->isNotEmpty())
<div class="plan-table-scroll" tabindex="0" role="region" aria-label="Daftar pengembalian dan pelepasan reservasi">
    <table class="table plan-table"><thead><tr><th>Nomor box</th><th>Nomor part / PCS</th><th>Posisi tercatat</th><th>Pertama diterima</th><th>Tindakan</th></tr></thead><tbody>
    @foreach($returns->concat($released) as $f)<tr><th>{{ $f->box_number }}</th><td>{{ $f->stockBox->part_number }} · {{ $f->pcs_quantity }} PCS</td><td>{{ $f->stockBox->currentPallet?->display_name ?? 'Belum ditempatkan' }} · {{ $f->stockBox->currentPallet?->location_code ?? '-' }}</td><td>{{ $f->first_received_at->format('d/m/Y H:i') }}</td><td>{{ $returns->contains('id', $f->id) ? 'Wajib pengembalian fisik' : 'Lepas reservasi; posisi tetap' }}</td></tr>@endforeach
    </tbody></table>
</div>
@else<p class="plan-panel-body">Belum ada box yang perlu dipindahkan atau dilepas reservasinya. Revisi hanya mengurangi kebutuhan yang belum terisi.</p>@endif
