export function initExportPicking({documentRef = globalThis.document, fetcher = (...args) => globalThis.fetch(...args),
    feedback = globalThis.ScanFeedback, formData = form => new FormData(form)} = {}) {
    const root = documentRef.getElementById('export-pick-workbench');
    const outcome = documentRef.getElementById('export-pick-feedback');
    if (!root || !outcome) return;
    let busy = false;
    const report = (ok, message) => {
        outcome.hidden = false;
        outcome.classList.toggle('is-ok', ok); outcome.classList.toggle('is-ng', !ok);
        outcome.textContent = message; feedback?.[ok ? 'success' : 'error']();
    };
    documentRef.addEventListener('submit', async event => {
        const form = event.target.closest('[data-export-pick-scan]');
        if (!form) return;
        event.preventDefault(); if (busy) return;
        const input = form.querySelector('[name="box_number"]');
        const number = input.value.trim();
        if (!/^[0-9]{8}$/.test(number)) { report(false, 'NG · Nomor box harus tepat 8 angka. Scan ulang; input ini tidak membuat approval.'); input.focus(); input.select(); return; }
        busy = true; form.setAttribute('aria-busy', 'true');
        const controls = [...form.querySelectorAll('input,button')];
        const payload = Object.fromEntries(formData(form)); payload.box_number = number;
        controls.forEach(control => { control.disabled = true; });
        try {
            const response = await fetcher(form.action, {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': payload._token}, body: JSON.stringify(payload)});
            const data = await response.json();
            if (!response.ok && !data.blocked) throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'Scan belum dapat diproses. Coba lagi.');
            if (typeof data.html === 'string') root.innerHTML = data.html;
            report(data.success === true, data.message ?? 'Scan selesai.');
            const next = root.querySelector('[name="box_number"]');
            if (next) { next.value = ''; next.focus({preventScroll: true}); }
        } catch (error) { report(false, error.message || 'Koneksi bermasalah. Coba scan ulang; token permintaan tetap dipertahankan.'); }
        finally {
            busy = false;
            if (form.isConnected) { controls.forEach(control => { control.disabled = false; }); form.removeAttribute('aria-busy'); input.focus({preventScroll: true}); }
        }
    });
}
