<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/pages/export-plan-print.css') }}">
</head>
<body>
    <nav class="print-toolbar no-print" aria-label="Aksi cetak"><a href="{{ $returnUrl }}">Kembali ke rencana</a><div><span>@yield('print-help')</span><button type="button" id="export-plan-print">Cetak / simpan PDF</button></div></nav>
    <main class="print-sheet">@yield('content')</main>
    <script>document.getElementById('export-plan-print').addEventListener('click', function () { window.print(); });</script>
</body>
</html>
