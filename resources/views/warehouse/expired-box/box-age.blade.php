@php
    $enteredAt = $box->stored_at ? \Illuminate\Support\Carbon::parse($box->stored_at) : null;
    $ageAt = $ageAsOf ?? now();
    $age = $enteredAt && $enteredAt->lte($ageAt) ? $enteredAt->diff($ageAt) : null;
@endphp
@if($age)
    <strong class="expired-box-age is-{{ $box->expired_status }}">{{ $age->y * 12 + $age->m }} bulan {{ $age->d }} hari</strong>
    <small class="d-block text-muted">{{ number_format($age->days, 0, ',', '.') }} hari sejak masuk asli</small>
@else
    <span class="text-muted">Umur belum tersedia</span>
@endif
@if($box->expired_status === 'expired' && $box->reactivated_until)
    <small class="d-block mt-1">Batas aktif berakhir {{ \Illuminate\Support\Carbon::parse($box->reactivated_until)->format('d M Y H:i') }}</small>
@endif
