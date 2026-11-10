export function createStockLoader({fetcher = (...args) => globalThis.fetch(...args), render, state}) {
    let version = 0, controller;
    const cancel = () => { ++version; controller?.abort(); controller = null; };
    const load = async url => {
        cancel();
        const current = version;
        controller = new AbortController();
        state({busy: true});
        try {
            const response = await fetcher(url, {headers: {'Accept': 'application/json'}, signal: controller.signal});
            if (!response.ok) {
                let message = response.status === 401 ? 'Sesi berakhir. Muat ulang halaman untuk masuk kembali.'
                    : response.status === 403 ? 'Anda tidak memiliki akses ke stok ekspor.' : 'Daftar belum dapat dimuat. Coba lagi.';
                if (response.status === 422) {
                    const data = await response.json();
                    message = Object.values(data.errors ?? {}).flat()[0] ?? message;
                }
                throw new Error(message);
            }
            const data = await response.json();
            if (typeof data.html !== 'string') throw new Error('Respons tidak dapat dibaca. Muat ulang halaman untuk memeriksa sesi.');
            if (current !== version) return false;
            render(data, url);
            state({busy: false});
            return true;
        } catch (error) {
            if (current === version && error.name !== 'AbortError') {
                state({busy: false, error: error.message || 'Koneksi bermasalah. Coba lagi.'});
            }
            return false;
        } finally {
            if (current === version) controller = null;
        }
    };
    return {load, cancel};
}

export function initStockBrowser({documentRef = globalThis.document, windowRef = globalThis.window,
    fetcher = (...args) => globalThis.fetch(...args)} = {}) {
    const form = documentRef.getElementById('export-stock-filters');
    if (!form) return;
    const results = documentRef.getElementById('export-stock-results');
    const status = documentRef.getElementById('stock-request-status');
    const retry = documentRef.getElementById('stock-request-retry');
    const count = documentRef.getElementById('stock-result-count');
    const readiness = documentRef.getElementById('stock-customer-readiness');
    const customer = documentRef.getElementById('stock-customer');
    const summary = documentRef.getElementById('export-stock-summary');
    const reset = documentRef.querySelector('[data-stock-reset]');
    const partFilter = form.querySelector('[data-stock-part-filter]');
    let timer, lastUrl = windowRef.location.href, historyMode = 'replace';
    const format = value => Number(value).toLocaleString('id-ID');
    const state = ({busy, error}) => {
        results.setAttribute('aria-busy', String(busy));
        readiness?.setAttribute('aria-busy', String(busy));
        status.textContent = busy ? 'Memperbarui stok…' : error ? 'Daftar belum diperbarui. '+error : count.textContent+' box ditemukan.';
        retry.hidden = !error;
    };
    const loader = createStockLoader({fetcher, state, render: (data, url) => {
        results.innerHTML = data.html;
        count.textContent = format(data.count);
        if (readiness && typeof data.readiness === 'string') readiness.innerHTML = data.readiness;
        if (summary && data.stockCounts) {
            for (const link of summary.querySelectorAll('[data-stock-quick-status]')) {
                const category = link.dataset.stockQuickStatus;
                link.querySelector('[data-stock-count]').textContent = format(data.stockCounts[category]);
                link.href = summaryUrl(category);
                if (form.elements.namedItem('status').value === category) link.setAttribute('aria-current', 'true');
                else link.removeAttribute('aria-current');
            }
        }
        if (customer) reset.href = summaryUrl();
        if (historyMode !== 'none') windowRef.history[historyMode+'State']({}, '', url);
    }});
    const filterUrl = () => {
        const url = new URL(form.action);
        for (const [key, value] of new FormData(form)) if (value !== '') url.searchParams.set(key, value);
        return url.href;
    };
    const summaryUrl = category => {
        const url = new URL(form.action);
        if (customer?.value) url.searchParams.set('customer', customer.value);
        if (category) {
            url.searchParams.set('status', category);
            url.searchParams.set('mode', form.elements.namedItem('mode').value);
            url.hash = 'export-stock-results';
        }
        return url.href;
    };
    const load = (url, mode = 'replace') => {
        windowRef.clearTimeout(timer);
        lastUrl = url; historyMode = mode;
        return loader.load(url);
    };
    const apply = delayed => {
        windowRef.clearTimeout(timer); loader.cancel();
        state({busy: true});
        if (delayed) timer = windowRef.setTimeout(() => load(filterUrl()), 250);
        else return load(filterUrl());
    };
    const syncForm = url => {
        const params = new URL(url).searchParams;
        for (const name of ['q', 'part_number', 'pcs_per_box', 'status', 'location_id', 'mode', 'customer']) {
            const field = form.elements.namedItem(name);
            if (!field) continue;
            let value = params.get(name) ?? (name === 'mode' ? 'box' : '');
            if (name === 'status' && ['pending', 'reserved'].includes(value)) value = 'spare';
            if (name === 'customer') value = value.trim().toLowerCase();
            field.value = value;
        }
        const part = form.elements.namedItem('part_number').value;
        const pcs = form.elements.namedItem('pcs_per_box')?.value;
        partFilter.hidden = !part && !pcs;
        partFilter.querySelector('[data-stock-part-label]').textContent = [part && `Nomor part ${part}`, pcs && `${pcs} PCS/box`].filter(Boolean).join(' · ');
    };

    form.addEventListener('input', event => { if (event.target.name === 'q') apply(true); });
    form.addEventListener('change', event => { if (event.target.matches('select') || event.target.name === 'mode') apply(false); });
    customer?.addEventListener('change', () => apply(false));
    form.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.target.name === 'q') { event.preventDefault(); apply(false); }
    });
    form.addEventListener('click', event => {
        if (event.target.closest('[data-stock-clear-part]')) {
            form.elements.namedItem('part_number').value = '';
            const pcs = form.elements.namedItem('pcs_per_box');
            if (pcs) pcs.value = '';
            partFilter.hidden = true;
            apply(false);
        }
    });
    form.addEventListener('submit', event => {
        if (event.submitter?.matches('[data-stock-export]')) return;
        event.preventDefault(); apply(false);
    });
    retry.addEventListener('click', () => load(lastUrl, historyMode));
    reset.addEventListener('click', event => {
        event.preventDefault();
        const url = summaryUrl();
        form.reset();
        syncForm(url); apply(false);
    });
    summary?.addEventListener('click', async event => {
        const link = event.target.closest('[data-stock-quick-status]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        const url = summaryUrl(link.dataset.stockQuickStatus);
        syncForm(url);
        if (await load(url, 'push')) results.scrollIntoView({block: 'start'});
    });
    windowRef.addEventListener('popstate', () => { syncForm(windowRef.location.href); load(windowRef.location.href, 'none'); });

    results.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        if (link.closest('.pagination')) {
            event.preventDefault(); load(link.href, 'push');
        }
    });
    windowRef.addEventListener('pagehide', () => { windowRef.clearTimeout(timer); loader.cancel(); });
    return {apply, load};
}
