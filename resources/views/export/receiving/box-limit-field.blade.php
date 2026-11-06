<div class="receiving-box-limit mb-3" data-box-limit-field hidden>
    <label class="form-label">
        <span class="d-block mb-1">Maksimal box pallet baru <span class="text-muted fw-normal">(opsional)</span></span>
        <input class="form-control" type="number" min="0" max="4294967295" step="1" inputmode="numeric" data-box-limit-input disabled
            @if($named ?? false) name="box_limit" @endif>
    </label>
    <small class="plan-help d-block">Kosongkan atau isi 0 untuk kapasitas bebas.</small>
</div>
