@if(!$announcement->enabled)
    <span class="badge text-bg-secondary">Nonaktif</span>
@elseif($usage['remaining'] === 0)
    <span class="badge text-bg-warning">{{ $announcement->quota_type === 'box' && !$usage['reserved'] ? 'Kuota habis' : 'Kuota teralokasi' }}</span>
@else
    <span class="badge text-bg-success">Aktif</span>
@endif
