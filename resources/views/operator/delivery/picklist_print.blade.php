<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Print Pick List Order #{{ $order->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 10px; }
        .header { margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .title { font-size: 20px; font-weight: bold; }
        .order-info { display: flex; justify-content: space-between; margin-top: 8px; font-size: 11px; }
        .info-block { flex: 1; }
        .info-block strong { font-weight: bold; }
        .summary { background: #fff; padding: 10px; margin: 15px 0; border-left: 4px solid #000; }
        .summary-row { display: flex; justify-content: space-between; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th {
            background: #fff;
            color: #111;
            text-align: left;
            padding: 8px;
            font-weight: 700;
            border: 1px solid #000;
        }
        td { border: 1px solid #000; padding: 8px; }
        tr:nth-child(even) { background: #fff; }
        .location { font-weight: bold; }
        .section-title { font-size: 13px; font-weight: bold; background: #fff; padding: 8px; margin-top: 20px; margin-bottom: 10px; border-left: 3px solid #000; }
        .box-number { font-weight: bold; }
        .part-number { font-family: monospace; font-weight: bold; }
        .instructions { background: #fff; padding: 10px; margin: 15px 0; border-radius: 4px; border-left: 4px solid #000; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; border-top: 1px solid #000; padding-top: 10px; }
        @media print {
            body { margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">📋 PICK LIST - ORDER #{{ $order->id }}</div>
        <div class="order-info">
            <div class="info-block">
                <strong>Customer:</strong> {{ $order->customer_name }}
            </div>
            <div class="info-block">
                <strong>Delivery Date:</strong> {{ $order->delivery_date->format('d M Y') }}
            </div>
            <div class="info-block">
                <strong>Generated:</strong> {{ now()->format('d M Y H:i') }}
            </div>
        </div>
    </div>

    <div class="summary">
        <div class="summary-row">
            <div><strong>Total Items:</strong> {{ $session->items->count() }} box(es)</div>
            <div><strong>Total PCS:</strong> {{ $session->items->sum('pcs_quantity') }} unit(s)</div>
            <div><strong>Session ID:</strong> {{ $session->id }}</div>
        </div>
    </div>

    @php
        $rawNotes = trim((string) ($order->notes ?? ''));
        $ppcOnlyNotes = '';

        if ($rawNotes !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $rawNotes) ?: [];
            $ppcLines = [];

            foreach ($lines as $line) {
                if (preg_match('/^\s*\[PPC\]\s*:/i', (string) $line)) {
                    $ppcLines[] = trim((string) $line);
                }
            }

            if (!empty($ppcLines)) {
                $latestPpcLine = end($ppcLines);
                $ppcOnlyNotes = trim((string) preg_replace('/^\s*\[PPC\]\s*:\s*/i', '', (string) $latestPpcLine));
            } elseif (preg_match('/\[(?:Sales|PPC)\]\s*:/i', $rawNotes)) {
                $ppcOnlyNotes = '';
            } else {
                $ppcOnlyNotes = $rawNotes;
            }
        }
    @endphp

    <div class="summary">
        <div class="summary-row">
            <div><strong>Keterangan Pengambilan (PPC):</strong> {{ $ppcOnlyNotes !== '' ? $ppcOnlyNotes : '-' }}</div>
        </div>
    </div>

    <div class="instructions">
        <strong>📌 PETUNJUK PENGAMBILAN:</strong>
        <br>
        1. Ikuti urutan nomor box sesuai tabel di bawah<br>
        2. Cek lokasi pallet di kolom "LOKASI PALLET"<br>
        3. Verifikasi part number dan qty sebelum mengambil<br>
        4. Scan setiap box setelah diambil<br>
    </div>

    <div class="section-title">📦 DAFTAR BOX YANG HARUS DIAMBIL</div>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>ID Box</th>
                <th>Pallet</th>
                <th>Lokasi Pallet</th>
                <th>Part Number</th>
                <th>Qty (PCS)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($session->items as $index => $item)
                @php
                    $pallet = $item->box->pallets->first();
                    $locationCode = 'NO-LOC';

                    if ($pallet) {
                        if ($pallet->stockLocation && $pallet->stockLocation->warehouse_location) {
                            $locationCode = $pallet->stockLocation->warehouse_location;
                        } elseif ($pallet->stockLocation && $pallet->stockLocation->masterLocation) {
                            $locationCode = $pallet->stockLocation->masterLocation->code;
                        } elseif ($pallet->currentLocation) {
                            $locationCode = $pallet->currentLocation->code;
                        } else {
                            $masterLoc = \App\Models\MasterLocation::where('current_pallet_id', $pallet->id)->first();
                            if ($masterLoc) {
                                $locationCode = $masterLoc->code;
                            } else if ($pallet->stockLocation) {
                                $locationCode = $pallet->stockLocation->warehouse_location ?: 'NO-LOC';
                            }
                        }
                    }
                @endphp
                <tr>
                    <td style="text-align: center; font-weight: bold;">{{ $index + 1 }}</td>
                    <td class="box-number">{{ $item->box->box_number }}</td>
                    <td class="box-number">{{ $pallet ? $pallet->pallet_number : '-' }}</td>
                    <td class="location">{{ $locationCode }}</td>
                    <td class="part-number">{{ $item->part_number }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ $item->pcs_quantity }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dokumen ini untuk panduan operator warehouse dalam pengambilan part.<br>
        Cetak dan bawa saat melakukan picking process.
    </div>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.focus();
                window.print();
            }, 250);
        });
    </script>
</body>
</html>
