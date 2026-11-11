<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #111827; }
        h2 { margin-top: 24px; border-bottom: 1px solid #d1d5db; padding-bottom: 6px; }
        h3 { font-size: 15px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; font-size: 12px; }
        th { background: #f3f4f6; text-align: left; }
    </style>
</head>
<body>
    <h1>Ringkasan Umur Box</h1>
    <p>Warning berlaku mulai umur 9 bulan. Box menjadi Expired mulai umur 12 bulan atau saat masa aktif hasil Handle berakhir.</p>

    @foreach($sections as $scope => $statuses)
        <h2>{{ $scope }}</h2>
        @foreach(['warning' => 'Warning (9 hingga kurang dari 12 bulan)', 'expired' => 'Expired / Masa Aktif Habis'] as $status => $label)
            @php($boxes = $statuses->get($status, collect()))
            <h3>{{ $label }} · {{ $boxes->count() }} box</h3>
            @if($boxes->isEmpty())
                <p>Tidak ada box pada status ini.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th scope="col">ID Box</th>
                            <th scope="col">Nomor Part</th>
                            <th scope="col">Pallet</th>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Pertama Diterima</th>
                            <th scope="col">Masa Aktif Hasil Handle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($boxes as $box)
                            @php($pallet = $scope === 'Ekspor' && $box->pallet_id
                                ? ($box->source_position ? 'Pallet '.$box->source_position : 'Pallet cadangan')
                                : $box->pallet_number)
                            <tr>
                                <td>{{ $box->box_number }}</td>
                                <td>{{ $box->part_number }}</td>
                                <td>{{ $pallet ?? '—' }}</td>
                                <td>{{ $box->warehouse_location ?? '—' }}</td>
                                <td>{{ $box->stored_at ? \Illuminate\Support\Carbon::parse($box->stored_at)->format('d/m/Y') : '—' }}</td>
                                <td>{{ $box->reactivated_until ? \Illuminate\Support\Carbon::parse($box->reactivated_until)->format('d/m/Y H:i') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
    @endforeach

    <p style="margin-top: 16px;">Ringkasan harian Warehouse FG dijadwalkan pukul 07.00 WIB.</p>
</body>
</html>
