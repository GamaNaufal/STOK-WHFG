import {scanToken} from './export-stock/scan-token.mjs';
import {initBoxModal} from './export-stock/box-modal.mjs';
import {initPlacementGuide} from './export-stock/placement-guide.mjs';
import {initDestinationLoads} from './export-stock/pallet-load.mjs';
import {initStockBrowser} from './export-stock/stock-browser.mjs';
import {initExportPicking} from './export-stock/picking.mjs';

initBoxModal();
initPlacementGuide();
initDestinationLoads();
initStockBrowser();
initExportPicking();
document.addEventListener('submit', event => {
    const form = event.target.closest('[data-placement-form], #export-place-form');
    if (!form) return;
    if (form.dataset.submitting) { event.preventDefault(); return; }
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
});
window.addEventListener('pageshow', () => document.querySelectorAll('[data-submitting]').forEach(form => {
    delete form.dataset.submitting; form.removeAttribute('aria-busy');
}));

const form = document.getElementById('export-bulk-scan-form');
if (form) {
    const box = document.getElementById('receive-box-number'), part = document.getElementById('receive-part-number');
    const items = document.getElementById('scan-items'), processForm = document.getElementById('process-batch-form');
    const processButton = document.getElementById('process-batch-button'), clearButton = document.getElementById('discard-batch-button');
    const status = document.getElementById('receiving-scan-status'), outcome = document.getElementById('scan-outcome');
    const label = document.getElementById('scan-outcome-label'), existing = document.getElementById('receiving-existing-box');
    const base = form.dataset.batchesUrl, csrf = form.querySelector('[name="_token"]').value;
    let state = JSON.parse(document.getElementById('receiving-batch-bootstrap').textContent);
    let busy = false, pendingBox = null;
    const format = value => Number(value).toLocaleString('id-ID');
    const feedback = (ok, message) => {
        label.textContent = ok ? 'OK' : 'NG';
        outcome.classList.toggle('is-ok', ok); outcome.classList.toggle('is-ng', !ok);
        status.textContent = message;
        window.ScanFeedback?.[ok ? 'success' : 'error']();
    };
    const request = async (url, method = 'GET', data) => {
        const response = await fetch(url, {method, headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
            ...(data === undefined ? {} : {body: JSON.stringify(data)})});
        let body;
        try { body = await response.json(); } catch (_) { throw new Error('Sesi atau koneksi bermasalah. Muat ulang halaman; daftar scan tetap tersimpan.'); }
        if (!response.ok) {
            const error = new Error(Object.values(body.errors ?? {}).flat()[0] ?? body.message ?? 'Permintaan belum berhasil.');
            error.fields = body.errors ?? {}; error.httpStatus = response.status;
            throw error;
        }
        return body;
    };
    const render = () => {
        items.replaceChildren();
        [...state.items].reverse().forEach((item, index) => {
            const row = document.createElement('tr');
            [state.items.length - index, item.box_number, item.part_number, format(item.pcs_quantity)].forEach((value, column) => {
                const cell = document.createElement(column === 1 ? 'th' : 'td');
                if (column === 1) cell.scope = 'row';
                if (column === 2) cell.className = 'receiving-draft-part';
                cell.textContent = String(value); row.append(cell);
            });
            const cell = document.createElement('td'), button = document.createElement('button');
            button.type = 'button'; button.className = 'btn btn-sm btn-outline-danger'; button.textContent = 'Hapus';
            button.dataset.removeScan = item.id; button.disabled = busy;
            button.setAttribute('aria-label', 'Hapus scan box ' + item.box_number);
            cell.append(button); row.append(cell); items.append(row);
        });
        document.getElementById('scan-count').textContent = format(state.items.length);
        document.getElementById('scan-empty').hidden = state.items.length > 0;
        const parts = new Set(state.items.map(item => item.part_number));
        document.getElementById('scan-summary').textContent = format(parts.size) + ' part · ' +
            format(state.items.reduce((sum, item) => sum + item.pcs_quantity, 0)) + ' PCS · Draft tersimpan';
        document.getElementById('batch-revision').value = state.revision;
        if (state.id) processForm.action = base + '/' + state.id + '/process';
        processButton.disabled = busy || !state.items.length || state.status !== 'draft';
        clearButton.disabled = busy || !state.items.length || state.status !== 'draft';
    };
    const setBusy = value => {
        busy = value; box.readOnly = value || pendingBox !== null; part.readOnly = value;
        form.querySelectorAll('button').forEach(button => { button.disabled = value; });
        form.setAttribute('aria-busy', String(value)); render();
    };
    const resetInput = () => {
        pendingBox = null; box.value = ''; part.value = ''; box.readOnly = busy;
        existing.hidden = true;
        box.focus({preventScroll: true});
    };
    const recover = async error => {
        if (state.id && (error.fields?.revision || error.fields?.batch)) {
            try {
                state = await request(base + '/' + state.id);
                if (state.status === 'processed') {
                    const url = new URL(location.href); url.search = ''; url.searchParams.set('batch', state.id);
                    location.assign(url.href); return;
                }
            } catch (_) { /* Keep the visible list if refresh is unavailable. */ }
        }
        feedback(false, error.message);
    };
    const normalizedBox = () => box.value.replace(/[\u0000-\u001f\u007f\s]+/g, '');
    const checkBox = async () => {
        if (busy) { feedback(false, 'Tunggu validasi scan sebelumnya selesai.'); return; }
        const number = normalizedBox();
        if (!/^[0-9]{8}$/.test(number)) { feedback(false, 'Nomor box harus tepat 8 angka.'); resetInput(); return; }
        setBusy(true);
        try {
            const result = await request(form.dataset.checkUrl, 'POST', {box_number: number, batch_id: state.id});
            if (!result.available) {
                feedback(false, result.message); resetInput();
                if (result.box_id) {
                    const url = new URL(form.dataset.stockUrl, location.href); url.searchParams.set('box', result.box_id);
                    existing.href = url.href; existing.hidden = false;
                }
            } else {
                pendingBox = number; box.value = number; feedback(true, result.message);
                part.focus({preventScroll: true}); part.select();
            }
        } catch (error) { await recover(error); box.focus({preventScroll: true}); }
        finally { setBusy(false); }
    };
    const addScan = async () => {
        if (busy) { feedback(false, 'Tunggu validasi scan sebelumnya selesai.'); return; }
        const number = normalizedBox(), numberPart = part.value.trim();
        if (!/^[0-9]{8}$/.test(number)) { feedback(false, 'Nomor box harus tepat 8 angka.'); resetInput(); return; }
        if (!numberPart || numberPart.length > 100) { feedback(false, 'Scan nomor part yang valid, maksimal 100 karakter.'); part.focus(); part.select(); return; }
        setBusy(true);
        try {
            if (!state.id) state = await request(base, 'POST', {});
            state = await request(base + '/' + state.id + '/scan', 'POST', {
                box_number: number, part_number: numberPart, revision: state.revision, scan_token: scanToken(),
            });
            const accepted = state.items.find(item => item.box_number === number);
            feedback(true, number + ' · ' + numberPart + ' · ' + format(accepted.pcs_quantity) + ' PCS masuk daftar scan.');
            resetInput();
        } catch (error) {
            await recover(error);
            if (error.fields?.box_number) resetInput(); else { part.focus({preventScroll: true}); part.select(); }
        } finally { setBusy(false); }
    };
    form.addEventListener('submit', event => { event.preventDefault(); addScan(); });
    box.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); checkBox(); } });
    document.getElementById('scan-box-button').addEventListener('click', checkBox);
    document.getElementById('reset-scan-input').addEventListener('click', resetInput);
    box.addEventListener('input', () => {
        const value = box.value, digits = value.replace(/[^0-9]/g, '').slice(0, 8);
        if (value !== digits) {
            const caret = box.selectionStart;
            box.value = digits;
            if (caret !== null) {
                const position = Math.min(value.slice(0, caret).replace(/[^0-9]/g, '').length, 8);
                box.setSelectionRange(position, position);
            }
        }
        existing.hidden = true;
    });
    items.addEventListener('click', async event => {
        const button = event.target.closest('[data-remove-scan]');
        if (!button || busy) return;
        setBusy(true);
        try { state = await request(base + '/' + state.id + '/items/' + button.dataset.removeScan, 'DELETE', {revision: state.revision}); }
        catch (error) { await recover(error); }
        finally { setBusy(false); box.focus({preventScroll: true}); }
    });
    clearButton.addEventListener('click', () => {
        if (busy || !state.id) return;
        WarehouseAlert.confirm({title: 'Kosongkan daftar scan?', message: 'Daftar ini belum diproses menjadi stok. Semua baris scan pada daftar akan dibatalkan.', confirmText: 'Kosongkan daftar', onConfirm: async () => {
            setBusy(true);
            try {
                state = await request(base + '/' + state.id, 'DELETE', {revision: state.revision});
                resetInput(); label.textContent = 'Siap scan'; outcome.classList.remove('is-ok', 'is-ng');
                status.textContent = 'Daftar dikosongkan. Mulai scan box berikutnya.';
            } catch (error) { await recover(error); }
            finally { setBusy(false); }
        }});
    });
    processForm.addEventListener('submit', event => {
        if (busy || !state.items.length || pendingBox || box.value.trim() || part.value.trim()) {
            event.preventDefault();
            feedback(false, busy ? 'Tunggu validasi scan selesai.' : !state.items.length ? 'Daftar scan masih kosong.' : 'Selesaikan scan part atau ganti nomor box sebelum memproses daftar.');
            return;
        }
        setBusy(true);
    });
    render();
    (document.getElementById('receiving-errors') ?? box).focus({preventScroll: true});
}
