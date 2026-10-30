<div class="modal receiving-page" id="export-stock-box-modal" tabindex="-1" aria-hidden="true" aria-labelledby="export-stock-box-title" data-open-on-load="{{ $selected ? 'true' : 'false' }}">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="export-stock-box-title">Detail box ekspor</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup detail box"></button>
            </div>
            <div class="modal-body" id="export-stock-box-content" aria-live="polite">
                @if($selected)@include('export.stock.box-detail')@endif
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>
