export function createReportLoader({fetcher = (...args) => globalThis.fetch(...args), render, state}) {
    let version = 0, controller;
    const cancel = () => { ++version; controller?.abort(); controller = null; };
    const load = async (url, mode = 'push') => {
        cancel(); const current = version;
        controller = new AbortController();
        state({busy: true});
        try {
            const response = await fetcher(url, {headers: {'Accept': 'application/json'}, signal: controller.signal});
            if (!response.ok) {
                let message = response.status === 401 ? 'Sesi berakhir. Muat ulang halaman untuk masuk kembali.'
                    : response.status === 403 ? 'Akses laporan tidak tersedia.' : 'Laporan belum dapat dimuat. Coba lagi.';
                if (response.status === 422) {
                    const data = await response.json(); message = Object.values(data.errors ?? {}).flat()[0] ?? message;
                }
                throw new Error(message);
            }
            const data = await response.json();
            if (typeof data.html !== 'string') throw new Error('Respons laporan tidak dapat dibaca. Muat ulang untuk memeriksa sesi.');
            if (current !== version) return false;
            render(data, data.url || response.url || url, mode);
            state({busy: false});
            return true;
        } catch (error) {
            if (current === version && error.name !== 'AbortError') state({busy: false,
                error: error.name === 'SyntaxError' ? 'Respons laporan tidak dapat dibaca. Muat ulang untuk memeriksa sesi.'
                    : error.name === 'TypeError' ? 'Koneksi bermasalah. Coba lagi.' : error.message || 'Koneksi bermasalah. Coba lagi.'});
            return false;
        } finally { if (current === version) controller = null; }
    };
    return {load, cancel};
}

export function reportFormUrl(action, entries, base) {
    const url = new URL(action, base); url.search = ''; url.hash = '';
    for (const [key, value] of entries) if (typeof value === 'string' && value !== '' && key !== 'page') url.searchParams.append(key, value);
    return url.href;
}

export function initReportBrowser({documentRef = globalThis.document, windowRef = globalThis.window,
    fetcher = (...args) => globalThis.fetch(...args)} = {}) {
    const root = documentRef?.querySelector('[data-report-browser]');
    if (!root) return;
    const message = documentRef.querySelector('[data-report-message]');
    const retry = documentRef.querySelector('[data-report-retry]');
    let timer, composing = false, pending = false, lastUrl = windowRef.location.href, lastMode = 'push';
    const enhance = () => root.querySelectorAll('[data-report-scope-form] button[type="submit"], [data-report-filter-form] button[type="submit"]')
        .forEach(button => { button.hidden = true; });
    const state = ({busy, error}) => {
        pending = busy || Boolean(error);
        root.setAttribute('aria-busy', String(busy));
        message.textContent = busy ? 'Memperbarui laporan…' : error ? 'Hasil sebelumnya tetap ditampilkan. '+error : 'Laporan diperbarui. Excel mengikuti filter ini.';
        retry.hidden = !error;
        root.querySelectorAll('[data-report-export], .pagination a[href]').forEach(link => {
            link.setAttribute('aria-disabled', String(pending)); link.classList.toggle('disabled', pending);
            if (pending) link.setAttribute('tabindex', '-1'); else link.removeAttribute('tabindex');
        });
    };
    const loader = createReportLoader({fetcher, state, render: (data, address, mode) => {
        const url = new URL(address, windowRef.location.href);
        if (url.origin !== windowRef.location.origin) throw new Error('Alamat laporan berubah. Muat ulang halaman.');
        const active = documentRef.activeElement;
        const focus = root.contains(active) && active.id ? {id: active.id, start: active.selectionStart, end: active.selectionEnd} : null;
        const scroll = {top: windowRef.scrollY, left: windowRef.scrollX};
        const tableScroll = root.querySelector('[role="region"]')?.scrollLeft ?? 0;
        root.innerHTML = data.html; enhance();
        if (data.title) documentRef.title = data.title;
        if (mode !== 'none' && url.href !== windowRef.location.href) windowRef.history[mode+'State']({}, '', url.href);
        if (focus) {
            const input = documentRef.getElementById(focus.id);
            input?.focus({preventScroll: true});
            if (input && typeof focus.start === 'number') { try { input.setSelectionRange(focus.start, focus.end); } catch {} }
        }
        const region = root.querySelector('[role="region"]'); if (region) region.scrollLeft = tableScroll;
        windowRef.scrollTo({...scroll, behavior: 'auto'});
    }});
    const load = (url, mode = 'push') => {
        windowRef.clearTimeout(timer); lastUrl = url; lastMode = mode;
        return loader.load(url, mode);
    };
    const apply = (form, delayed = false) => {
        windowRef.clearTimeout(timer); loader.cancel(); state({busy: true});
        const url = reportFormUrl(form.action, new FormData(form), windowRef.location.href);
        lastUrl = url; lastMode = 'push';
        if (delayed) timer = windowRef.setTimeout(() => load(url), 300);
        else return load(url);
    };
    const formFor = target => target.closest('[data-report-filter-form], [data-report-scope-form]');
    root.addEventListener('input', event => {
        const form = formFor(event.target);
        if (form && !composing && !event.isComposing && event.target.matches('input[type="text"], input[type="search"]')) apply(form, true);
    });
    root.addEventListener('change', event => {
        const form = formFor(event.target);
        if (form && event.target.matches('select, input[type="date"]')) apply(form);
    });
    root.addEventListener('compositionstart', event => {
        if (formFor(event.target)) { composing = true; windowRef.clearTimeout(timer); loader.cancel(); state({busy: true}); }
    });
    root.addEventListener('compositionend', event => { composing = false; const form = formFor(event.target); if (form) apply(form, true); });
    root.addEventListener('submit', event => { const form = formFor(event.target); if (form) { event.preventDefault(); if (!composing) apply(form); } });
    root.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link) return;
        if (pending && (link.matches('[data-report-export]') || link.closest('.pagination'))) { event.preventDefault(); return; }
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button > 0) return;
        if (link.matches('[data-report-reset]') || link.closest('.pagination')) { event.preventDefault(); load(link.href); }
    });
    retry.addEventListener('click', () => load(lastUrl, lastMode));
    windowRef.addEventListener('popstate', () => load(windowRef.location.href, 'none'));
    windowRef.addEventListener('pagehide', () => { windowRef.clearTimeout(timer); loader.cancel(); });
    enhance();
    return {load, apply};
}

if (typeof document !== 'undefined') initReportBrowser();
