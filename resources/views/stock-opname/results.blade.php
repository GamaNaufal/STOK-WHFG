<div class="plan-panel-heading"><h2>{{ $opname->status === 'completed' ? 'Hasil pemeriksaan' : 'Catatan pemeriksaan' }}</h2><span><span data-opname-count="scans">{{ $summary['scans'] }}</span> scan · <span data-opname-count="duplicates">{{ $summary['duplicates'] }}</span> duplikat</span></div>
<nav class="opname-tabs" aria-label="Rincian hasil opname">
    @foreach(['scans'=>'Semua scan', 'ng'=>'Scan NG', 'missing'=>'Belum terscan', 'found'=>'Sesuai', 'changes'=>'Perubahan stok', 'all'=>'Semua box'] as $key => $label)
        <a href="{{ route($base.'.show', ['opname'=>$opname->id, 'tab'=>$key, 'q'=>$search]) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
<form method="GET" action="{{ route($base.'.show', $opname) }}" class="opname-result-search" data-opname-search>
    <input type="hidden" name="tab" value="{{ $tab }}">
    <label class="visually-hidden" for="opname-result-query">Cari ID Box pada rincian</label>
    <input id="opname-result-query" type="search" name="q" class="form-control" maxlength="100" value="{{ $search }}" placeholder="Cari ID Box pada rincian">
    <button class="btn btn-outline-primary" type="submit">Cari</button>
</form>
<div class="opname-table-scroll" role="region" tabindex="0" aria-label="Rincian opname {{ $scopeLabel }}">
    <table class="table plan-table mb-0">
        <thead><tr><th>ID Box / part</th><th>Pallet / lokasi</th><th>Hasil</th><th>Waktu / operator</th></tr></thead>
        <tbody>
        @forelse($rows as $row)
            @php
                $snapshot = $scanTable ? $row->snapshot : $row->current_snapshot;
                $number = $scanTable ? $row->input : $row->box_number;
                $labels = ['ok'=>'OK', 'ng'=>'NG', 'duplicate'=>'Duplikat'];
                $changeLabels = ['unchanged'=>'Sesuai stok awal', 'changed'=>'Posisi / data berubah', 'received'=>'Masuk selama sesi', 'left'=>'Keluar / ID diganti', 'received_left'=>'Masuk lalu keluar / ID diganti'];
                $result = $scanTable ? $labels[$row->outcome] : (!$row->present ? $changeLabels[$row->change_kind] : ($row->first_scanned_at ? 'Sudah terscan' : 'Belum terscan'));
            @endphp
            <tr>
                <th scope="row">{{ $number }}@if($snapshot)<small>{{ $snapshot['part_number'] }} · {{ $snapshot['pcs_quantity'] }} PCS</small>@endif</th>
                <td>{{ $snapshot['pallet'] ?? 'Tidak ditemukan' }}<small>{{ $snapshot['location'] ?? '—' }}</small>
                    @if(!$scanTable && $row->change_kind === 'changed')<small>Awal {{ $row->initial_snapshot['pallet'] }} · {{ $row->initial_snapshot['location'] }} · {{ $row->initial_snapshot['pcs_quantity'] }} PCS</small>@endif
                </td>
                <td><span class="opname-badge {{ $scanTable && $row->outcome === 'ng' ? 'is-ng' : '' }}">{{ $result }}</span>
                    @if($scanTable)<small>{{ $row->reason }}</small>
                    @else<small>{{ $changeLabels[$row->change_kind] }}@if(!$row->present) · {{ $snapshot['reason'] }}@endif</small>@endif
                </td>
                <td>{{ ($scanTable ? $row->scanned_at : $row->first_scanned_at)?->format('d/m/Y H:i:s') ?? 'Belum terscan' }}<small>{{ $row->scanner?->name ?? '—' }}</small></td>
            </tr>
        @empty
            <tr><td colspan="4" class="opname-empty">{{ $search !== '' ? 'Tidak ada ID Box yang cocok.' : match($tab) {
                'missing' => 'Seluruh box yang masih tercatat sudah terscan.',
                'ng' => 'Belum ada scan NG pada sesi ini.',
                'changes' => 'Belum ada perubahan stok yang terdeteksi.',
                default => 'Belum ada data pada bagian ini.',
            } }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if($rows->hasPages())<div class="plan-panel-body">{{ $rows->links('pagination::bootstrap-5') }}</div>@endif
