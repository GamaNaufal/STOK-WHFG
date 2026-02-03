<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #111827; }
        .section-title { font-weight: 700; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; font-size: 12px; }
        th { background: #f3f4f6; text-align: left; }
        .badge { display: inline-block; padding: 2px 6px; font-size: 11px; border-radius: 6px; }
        .warning { background: #fff7ed; color: #9a3412; }
        .expired { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <h2>Peringatan Box Mendekati Expired</h2>
    <p>Berikut ringkasan box yang berumur 9+ bulan (warning) dan 12+ bulan (expired).</p>

    <div class="section-title">Warning (9-12 bulan)</div>
    @if($warningBoxes->isEmpty())
        <p>Tidak ada data.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Box</th>
                    <th>Part</th>
                    <th>Pallet</th>
                    <th>Lokasi</th>
                    <th>Stored At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($warningBoxes as $box)
                    <tr>
                        <td>{{ $box->box_number }}</td>
                        <td>{{ $box->part_number }}</td>
                        <td>{{ $box->pallet_number ?? '-' }}</td>
                        <td>{{ $box->warehouse_location ?? '-' }}</td>
                        <td>{{ $box->stored_at ? \Illuminate\Support\Carbon::parse($box->stored_at)->format('d M Y') : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-title">Expired (>=12 bulan)</div>
    @if($expiredBoxes->isEmpty())
        <p>Tidak ada data.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Box</th>
                    <th>Part</th>
                    <th>Pallet</th>
                    <th>Lokasi</th>
                    <th>Stored At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expiredBoxes as $box)
                    <tr>
                        <td>{{ $box->box_number }}</td>
                        <td>{{ $box->part_number }}</td>
                        <td>{{ $box->pallet_number ?? '-' }}</td>
                        <td>{{ $box->warehouse_location ?? '-' }}</td>
                        <td>{{ $box->stored_at ? \Illuminate\Support\Carbon::parse($box->stored_at)->format('d M Y') : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p style="margin-top: 16px;">Email ini dikirim otomatis setiap hari pukul 07:00 (Asia/Jakarta).</p>
</body>
</html>
