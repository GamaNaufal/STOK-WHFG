@php
    $documentText = old('document_numbers', isset($partSetting) ? $partSetting->documentNumbers->pluck('document_number')->implode("\n") : '');
    $sameDocument = (string) old('same_document_number', $documentText === '' ? '1' : '0') === '1';
@endphp
<details class="ui-disclosure" id="documentNumberDetails" open>
    <summary><span class="ui-step" aria-hidden="true">02</span><span><strong>Kode dokumen delivery</strong><small data-document-summary>{{ $sameDocument ? 'Sama dengan label / scan' : 'Kode berbeda, periksa pemetaan' }}</small></span></summary>
    <div class="ui-disclosure-body part-form-field">
    <label for="documentNumbersInput" class="form-label">Nomor part dokumen delivery</label>
    <div class="form-check mb-2">
        <input type="hidden" name="same_document_number" value="0">
        <input class="form-check-input" type="checkbox" id="sameDocumentNumber" name="same_document_number" value="1" @checked($sameDocument)
            aria-invalid="{{ $errors->has('same_document_number') ? 'true' : 'false' }}" @error('same_document_number') aria-describedby="same-document-error" @enderror>
        <label class="form-check-label" for="sameDocumentNumber">Sama dengan nomor part label / scan</label>
    </div>
    @error('same_document_number')<div id="same-document-error" class="invalid-feedback d-block" role="alert">{{ $message }}</div>@enderror
    <textarea name="document_numbers" id="documentNumbersInput" rows="3" maxlength="10200"
        class="form-control @error('document_numbers') is-invalid @enderror"
        aria-describedby="document-number-help @error('document_numbers') document-number-error @enderror"
        aria-invalid="{{ $errors->has('document_numbers') ? 'true' : 'false' }}"
        placeholder="Contoh: 7100-5079">{{ $documentText }}</textarea>
    <p class="part-form-help" id="document-number-help">Jika berbeda, hilangkan centang dan isi kode yang tercantum di Excel delivery. Satu kode per baris, tanda / dibaca sebagai bagian kode, bukan pemisah. Pemetaan disimpan bersama master. Perubahan berlaku untuk input berikutnya.</p>
    @error('document_numbers')
        <div class="invalid-feedback" id="document-number-error" role="alert">{{ $message }}</div>
    @enderror
    </div>
</details>
