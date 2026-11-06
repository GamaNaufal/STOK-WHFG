<input type="hidden" name="confirmed" value="0" data-stock-confirmed>
<noscript>
    <div class="form-check mb-3">
        <input id="{{ $reasonId }}-confirm" class="form-check-input" type="checkbox" name="confirmed" value="1" required>
        <label class="form-check-label" for="{{ $reasonId }}-confirm">Saya yakin ingin melakukan aksi ini.</label>
    </div>
</noscript>
<div class="mb-3">
    <label class="form-label" for="{{ $reasonId }}">Alasan</label>
    <textarea id="{{ $reasonId }}" name="reason" class="form-control" rows="2" minlength="3" maxlength="500" required>{{ old('_method') === $method && is_string(old('reason')) ? old('reason') : '' }}</textarea>
</div>
