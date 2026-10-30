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
    let timer, lastUrl = windowRef.location.href, historyMode = 'replace';
    const format = value => Number(value).toLocaleString('id-ID');
    const state = ({busy, error}) => {
        results.setAttribute('aria-busy', String(busy));
        status.textContent = busy ? 'Memperbarui stok…' : error ? 'Daftar belum diperbarui. '+error : count.textContent+' box ditemukan.';
        retry.hidden = !error;
    };
    const loader = createStockLoader({fetcher, state, render: (data, url) => {
        results.innerHTML = data.html;
        count.textContent = format(data.count);
        if (historyMode !== 'none') windowRef.history[historyMode+'State']({}, '', url);
    }});
    const filterUrl = () => {
        const url = new URL(form.action);
        for (const [key, value] of new FormData(form)) if (value !== '') url.searchParams.set(key, value);
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
        for (const name of ['q', 'status', 'location_id', 'mode']) form.elements.namedItem(name).value = params.get(name) ?? (name === 'mode' ? 'box' : '');
    };

    form.addEventListener('input', event => { if (event.target.name === 'q') apply(true); });
    form.addEventListener('change', event => { if (event.target.matches('select')) apply(false); });
    form.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.target.name === 'q') { event.preventDefault(); apply(false); }
    });
    form.addEventListener('submit', event => {
        if (event.submitter?.matches('[data-stock-export]')) return;
        event.preventDefault(); apply(false);
    });
    retry.addEventListener('click', () => load(lastUrl, historyMode));
    documentRef.querySelector('[data-stock-reset]').addEventListener('click', event => {
        event.preventDefault(); form.reset();
        syncForm(form.action); apply(false);
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
