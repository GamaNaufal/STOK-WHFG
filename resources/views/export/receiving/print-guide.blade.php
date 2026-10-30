<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panduan peletakan {{ $receipt['code'] }}</title>
    <link rel="stylesheet" href="{{ asset('css/pages/export-placement-guide.css') }}">
</head>
<body>
    <nav class="guide-toolbar no-print" aria-label="Aksi panduan cetak"><a href="{{ $receipt['detail_url'] }}">Kembali ke transaksi</a><button type="button" id="placement-guide-print">Cetak / simpan PDF</button></nav>
    <main class="guide-sheet">
        <header class="guide-header"><p>PT. YAMATOGOMU INDONESIA · WAREHOUSE FG EKSPOR</p><h1>Panduan peletakan box</h1><strong class="guide-code">{{ $receipt['code'] }}</strong></header>
        <table class="guide-meta"><tbody><tr><th>Penerimaan</th><td>{{ $receipt['received_at']->format('d/m/Y H:i') }}</td><th>Operator</th><td>{{ $receipt['operator'] }}</td></tr><tr><th>Dicetak</th><td>{{ $printedAt->format('d/m/Y H:i') }}</td><th>Jumlah</th><td>{{ $receipt['total'] }} box · {{ $receipt['pending'] }} menunggu · {{ $receipt['placed'] }} selesai</td></tr></tbody></table>
        @if($guideWarning)<p class="guide-warning">{{ $guideWarning }}</p>@endif
        @if($receipt['pending'] === 0)<p class="guide-complete">Seluruh box pada transaksi ini sudah ditempatkan.</p>@endif
        @foreach($printGroups as $destination)
            <table class="guide-table">
                <colgroup><col style="width:7%"><col style="width:19%"><col style="width:25%"><col style="width:9%"><col style="width:30%"><col style="width:10%"></colgroup>
                <thead>
                    <tr><th colspan="6" class="guide-destination {{ !$destination['location'] ? 'is-unassigned' : '' }}">
                        <span class="guide-section-label">{{ $receipt['code'] }} · TUJUAN PELETAKAN · {{ count($destination['rows']) }} BOX</span>
                        <span class="guide-target-pallet">{{ $destination['pallet'] }}</span>
                        <span class="guide-target-location">LOKASI: {{ $destination['location'] ?? 'BELUM DITENTUKAN' }}</span>
                        @if($destination['delivery'])<span class="guide-delivery">{{ $destination['delivery']->plan_code }} · {{ $destination['delivery']->customer_name }} · Tenggat {{ $destination['delivery']->delivery_deadline->format('d/m/Y') }}</span>@endif
                        @if($destination['load'] !== null && \App\Support\ExportPalletLoad::level($destination['load']) !== 'normal')<span class="guide-load">Setelah penempatan kelompok ini: {{ $destination['load'] }} box · {{ \App\Support\ExportPalletLoad::note($destination['load']) }}</span>@endif
                    </th></tr>
                    <tr><th class="col-number">No.</th><th class="col-box">Nomor box</th><th class="col-part">Nomor part</th><th class="col-pcs">PCS</th><th class="col-source">Posisi saat dicetak</th><th class="col-check">Cek</th></tr>
                </thead>
                <tbody>
                    @foreach($destination['rows'] as $row)
                        <tr><td>{{ $loop->iteration }}</td><td><strong class="guide-box">{{ $row['box']->box_number }}</strong></td><td>{{ $row['box']->part_number }}</td><td class="number">{{ $row['box']->pcs_quantity }}</td><td>{{ $row['box']->currentPallet ? $row['box']->currentPallet->display_name.' · '.$row['box']->currentPallet->location_code : 'Belum ditempatkan' }}</td><td class="check-cell">@if($row['waiting_for_review'])<strong>Menunggu Oke FIFO</strong>@elseif($row['pending'])<span class="paper-check" aria-label="Belum ditempatkan"></span>@else<strong>Sudah</strong>@endif</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
        <footer class="guide-footer">Centang setelah peletakan fisik, lalu konfirmasikan penempatan di sistem. Gunakan instruksi terbaru bila tujuan berubah setelah panduan dicetak.</footer>
    </main>
    <script>document.getElementById('placement-guide-print').addEventListener('click', function () { window.print(); });</script>
</body>
</html>
