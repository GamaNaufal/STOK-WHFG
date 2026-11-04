import {scanToken} from './export-stock/scan-token.mjs';
import {createOpnameQueue} from './stock-opname/scan-queue.mjs';

const page = document.querySelector('[data-opname-page]');
if (page) {
    const results = page.querySelector('[data-opname-results]');
    let detailUrl = window.location.href, viewVersion = 0;
    let latestScanCount = Number(page.querySelector('[data-opname-count="scans"]').textContent.replaceAll('.', ''));
    const updateSummary = summary => {
        if (summary.scans < latestScanCount) return;
        latestScanCount = summary.scans;
        for (const [key, value] of Object.entries(summary)) {
            page.querySelectorAll('[data-opname-count="'+key+'"]').forEach(node => { node.textContent = Number(value).toLocaleString('id-ID'); });
        }
    };
    const read = async response => {
        let data;
        try { data = await response.json(); }
        catch (_) { throw new Error('Respons tidak dapat dibaca. Periksa koneksi dan sesi login.'); }
        if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'Permintaan belum berhasil.');
        if (typeof data.html !== 'string' || !data.summary) throw new Error('Respons sesi tidak sesuai. Periksa sesi login sebelum mengirim ulang.');
        return data;
    };
    const load = async (url, history = true) => {
        const version = ++viewVersion;
        results.setAttribute('aria-busy', 'true');
        try {
            const data = await read(await fetch(url, {headers: {'Accept': 'application/json'}}));
            if (version !== viewVersion) return;
            const focused = document.activeElement;
            const restoreFocus = results.contains(focused);
            const focusId = focused?.id;
            results.innerHTML = data.html;
            if (restoreFocus) {
                const target = focusId ? document.getElementById(focusId) : results.querySelector('.opname-tabs a[aria-current="page"]');
                target?.focus();
            }
            updateSummary(data.summary);
            detailUrl = url;
            if (history) window.history.pushState({}, '', url);
        } catch (error) {
            const message = document.createElement('p');
            message.className = 'alert alert-danger';
            message.setAttribute('role', 'alert');
            message.textContent = 'Rincian belum diperbarui. '+error.message;
            results.prepend(message);
        } finally {
            if (version === viewVersion) results.setAttribute('aria-busy', 'false');
        }
    };
    results.addEventListener('click', event => {
        const link = event.target.closest('.opname-tabs a, .pagination a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        load(link.href);
    });
    results.addEventListener('submit', event => {
        const form = event.target.closest('[data-opname-search]');
        if (!form) return;
        event.preventDefault();
        const url = new URL(form.action);
        for (const [key, value] of new FormData(form)) if (value !== '') url.searchParams.set(key, value);
        load(url.href);
    });
    window.addEventListener('popstate', () => load(window.location.href, false));

    const form = page.querySelector('[data-opname-scan]');
    if (form) {
        const input = form.elements.namedItem('box_number');
        const feedback = page.querySelector('[data-opname-feedback]');
        const queueStatus = page.querySelector('[data-opname-queue]');
        const retry = page.querySelector('[data-opname-retry]');
        const queue = createOpnameQueue({
            send: async payload => {
                const version = viewVersion;
                const filters = new URL(detailUrl).searchParams;
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements.namedItem('_token').value},
                    body: JSON.stringify({...payload, tab: filters.get('tab'), q: filters.get('q')}),
                });
                const data = await read(response);
                if (!['ok', 'ng', 'duplicate'].includes(data.outcome)) throw new Error('Hasil scan belum dapat dipastikan. Kirim ulang dengan tombol coba lagi.');
                return {...data, viewVersion: version};
            },
            acknowledge: data => {
                feedback.dataset.outcome = data.outcome;
                feedback.querySelector('[data-opname-outcome]').textContent = {ok: 'OK', ng: 'NG', duplicate: 'Duplikat'}[data.outcome];
                feedback.querySelector('[data-opname-message]').textContent = data.box_number+' · '+data.message;
                feedback.querySelector('[data-opname-snapshot]').textContent = data.snapshot
                    ? data.snapshot.part_number+' · '+data.snapshot.pcs_quantity+' PCS · '+data.snapshot.pallet+' · '+data.snapshot.location : '';
                window.ScanFeedback?.[data.outcome === 'ng' ? 'error' : 'success']();
                if (data.viewVersion === viewVersion && !results.contains(document.activeElement)) results.innerHTML = data.html;
                updateSummary(data.summary);
            },
            state: ({pending, paused, error}) => {
                queueStatus.textContent = paused ? error+' Belum tersimpan: '+pending.join(', ')
                    : pending.length ? pending.length+' scan menunggu tersimpan…' : 'Semua scan sudah tersimpan.';
                retry.hidden = !paused;
            },
        });
        form.addEventListener('submit', event => {
            event.preventDefault();
            const number = input.value.trim();
            if (!number) return;
            try {
                const request = {box_number: number, request_token: scanToken()};
                input.value = '';
                input.focus();
                queue.enqueue(request);
            } catch (error) {
                queueStatus.textContent = error.message;
            }
        });
        retry.addEventListener('click', () => { queue.retry(); input.focus(); });
        page.querySelectorAll('[data-opname-action]').forEach(action => {
            action.addEventListener('submit', event => {
                if (queue.size()) {
                    event.preventDefault();
                    queueStatus.textContent = 'Selesaikan pengiriman scan yang tertunda sebelum melanjutkan.';
                }
            });
        });
        window.addEventListener('beforeunload', event => {
            if (queue.size()) { event.preventDefault(); event.returnValue = ''; }
        });
    }
}
