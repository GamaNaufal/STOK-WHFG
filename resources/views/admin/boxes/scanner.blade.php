@extends('shared.layouts.app')

@section('title', 'Barcode Scanner')

@section('content')
<div class="mb-4">
    <h4 class="mb-1">Barcode Scanner</h4>
    <p class="text-muted mb-0">Scan box untuk melihat detail</p>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label">Input Barcode</label>
            <input type="text" id="barcodeInput" class="form-control" placeholder="Scan/ketik barcode box">
        </div>
        <button class="btn btn-primary" id="scanBtn"><i class="bi bi-upc-scan"></i> Scan</button>

        <div class="mt-4" id="scanResult" style="display:none;">
            <h6>Hasil Scan</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <tbody id="resultBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.getElementById('scanBtn').addEventListener('click', async () => {
    const barcode = document.getElementById('barcodeInput').value.trim();
    if (!barcode) return;

    const response = await fetch("{{ route('barcode.scan') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ barcode })
    });

    const result = await response.json();
    const resultBody = document.getElementById('resultBody');
    resultBody.innerHTML = '';

    if (!result.success) {
        resultBody.innerHTML = `<tr><td class="text-danger">${result.message}</td></tr>`;
    } else {
        Object.entries(result.data).forEach(([key, value]) => {
            resultBody.innerHTML += `<tr><th>${key}</th><td>${value ?? '-'}</td></tr>`;
        });
    }

    document.getElementById('scanResult').style.display = 'block';
});
</script>
@endsection
