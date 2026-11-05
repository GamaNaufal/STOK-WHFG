(() => {
    'use strict';
    const form = document.querySelector('[data-packing-receipt]');
    if (!form) return;
    const input = form.querySelector('[data-packing-scan]');
    const group = form.querySelector('[data-receipt-group]');
    const list = form.querySelector('[data-packing-box-list]');
    const feedback = form.querySelector('[data-packing-feedback]');
    const count = form.querySelector('[data-receipt-count]');
    const save = form.querySelector('[data-save-receipt]');
    let chain = Promise.resolve();
    let submitting = false;
    let selectedGroup = group.value;
    const rows = () => Array.from(list.querySelectorAll('[data-staged-box]'));
    const remaining = () => Number(group.selectedOptions[0]?.dataset.remaining || 0);
    const inform = (message, outcome = '') => {
        feedback.textContent = message;
        feedback.dataset.outcome = outcome;
        if (outcome) window.ScanFeedback?.[outcome === 'ok' ? 'success' : 'error']();
    };
    const update = () => {
        const staged = rows();
        const ready = staged.filter(row => row.dataset.state === 'ok').length;
        const checking = staged.filter(row => row.dataset.state === 'checking').length;
        count.textContent = `${ready} box siap diterima · ${staged.length} di daftar · ${remaining()} box menunggu packing${checking ? ` · ${checking} diperiksa` : ''}`;
        save.disabled = submitting || !group.value || !staged.length || ready !== staged.length || staged.length > remaining();
        save.textContent = submitting ? 'Menyimpan penerimaan…' : `Simpan penerimaan${ready ? ` · ${ready} box` : ''}`;
        const load = form.querySelector('[data-packing-load]');
        if (load) {
            const pallet = form.querySelector('#batch-pallet');
            const existing = form.querySelector('[name="destination_mode"]:checked')?.value === 'existing';
            const current = existing ? Number(pallet.selectedOptions[0]?.dataset.boxes || 0) : 0;
            const total = current + staged.length;
            load.hidden = !staged.length;
            load.dataset.level = total >= 72 ? 'danger' : total >= 36 ? 'warning' : 'normal';
            load.textContent = `Pallet tujuan: ${current} box + ${staged.length} hasil packing = ${total} box.${total >= 72 ? ' Perhatian: mencapai 72 box atau lebih.' : total >= 36 ? ' Peringatan: mencapai 36 box atau lebih.' : ''}`;
        }
    };
    const validate = row => {
        row.dataset.state = 'checking';
        row.querySelector('[data-box-state]').textContent = 'Memeriksa ID Box…';
        row.querySelector('[data-retry-box]').hidden = true;
        const groupKey = group.value;
        update();
        chain = chain.then(async () => {
            if (!row.isConnected) return;
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000);
            try {
                const response = await fetch(form.dataset.scanUrl, {
                    method: 'POST', credentials: 'same-origin', signal: controller.signal,
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value},
                    body: JSON.stringify({box_number: row.dataset.stagedBox, group_key: groupKey}),
                });
                const data = await response.json();
                if (!response.ok || data.outcome !== 'ok' || data.box_number !== row.dataset.stagedBox) {
                    throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'ID Box belum dapat diperiksa. Coba lagi.');
                }
                if (!row.isConnected) return;
                row.dataset.state = 'ok';
                row.querySelector('[data-box-state]').textContent = 'OK · siap diterima';
                inform(`${row.dataset.stagedBox} · OK, ditambahkan ke daftar penerimaan.`, 'ok');
            } catch (error) {
                if (!row.isConnected) return;
                row.dataset.state = 'ng';
                const message = error.name === 'AbortError' ? 'Pemeriksaan terlalu lama. Periksa koneksi, lalu coba lagi.'
                    : error instanceof SyntaxError ? 'Respons tidak valid. Periksa koneksi atau login kembali.' : error.message;
                row.querySelector('[data-box-state]').textContent = `NG · ${message}`;
                row.querySelector('[data-retry-box]').hidden = false;
                inform(`${row.dataset.stagedBox} · belum lolos pemeriksaan. Hapus atau coba periksa ulang.`, 'ng');
            } finally {
                clearTimeout(timeout);
                update();
            }
        });
    };
    const wire = row => {
        row.querySelector('[data-remove-box]').addEventListener('click', () => {
            row.remove(); update(); input.focus();
        });
        row.querySelector('[data-retry-box]').addEventListener('click', () => validate(row));
        validate(row);
    };
    const add = () => {
        if (submitting) return;
        const number = input.value.trim();
        if (!group.value) { inform('Pilih kelompok part dan PCS terlebih dahulu.', 'ng'); group.focus(); return; }
        if (!/^[0-9]{8}$/.test(number)) { inform('NG · ID Box harus tepat 8 angka.', 'ng'); input.select(); return; }
        if (rows().some(row => row.dataset.stagedBox === number)) { inform(`${number} sudah ada di daftar scan.`, 'ng'); input.select(); return; }
        if (rows().length >= Math.min(remaining(), 50)) { inform('Jumlah scan sudah mencapai sisa kelompok ini. Periksa daftar atau muat ulang transaksi.', 'ng'); input.select(); return; }
        const row = document.createElement('tr');
        row.dataset.stagedBox = number;
        const identity = document.createElement('td');
        const label = document.createElement('strong'); label.textContent = number;
        const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'new_box_numbers[]'; hidden.value = number;
        identity.append(label, hidden);
        const state = document.createElement('td'); state.dataset.boxState = '';
        const actions = document.createElement('td');
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-sm btn-outline-secondary'; remove.dataset.removeBox = ''; remove.textContent = 'Hapus'; remove.setAttribute('aria-label', `Hapus ID Box ${number}`);
        const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-sm btn-outline-primary ms-2'; retry.dataset.retryBox = ''; retry.textContent = 'Periksa ulang'; retry.setAttribute('aria-label', `Periksa ulang ID Box ${number}`); retry.hidden = true;
        actions.append(remove, retry); row.append(identity, state, actions); list.append(row);
        wire(row); input.value = ''; input.focus();
    };
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); add(); }
    });
    form.querySelector('[data-add-packing-box]').addEventListener('click', add);
    form.addEventListener('change', event => { if (event.target.name === 'pallet_id' || event.target.name === 'destination_mode') update(); });
    group.addEventListener('change', () => {
        if (rows().length) { group.value = selectedGroup; inform('Kosongkan daftar scan sebelum mengganti kelompok part.', 'ng'); }
        else selectedGroup = group.value;
        update();
    });
    form.addEventListener('submit', event => {
        update();
        if (save.disabled || input.value.trim()) {
            event.preventDefault();
            if (input.value.trim()) { inform('Masih ada ID Box di kolom input. Tekan Enter untuk menambahkan atau kosongkan kolom.', 'ng'); input.focus(); }
            return;
        }
        submitting = true; update();
    });
    window.addEventListener('beforeunload', event => {
        if (!submitting && rows().length) { event.preventDefault(); event.returnValue = ''; }
    });
    window.addEventListener('pageshow', event => { if (event.persisted) { submitting = false; update(); } });
    rows().forEach(wire);
    update(); input.focus();
})();
