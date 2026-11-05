(() => {
    'use strict';
    const root = document.getElementById('expired-workspace');
    if (!root) return;
    let controller, version = 0, timer;
    const load = async (url, push = true) => {
        const target = new URL(url, location.href);
        if (target.origin !== location.origin) return;
        controller?.abort(); controller = new AbortController();
        const current = ++version;
        const field = document.activeElement;
        const focus = field && root.contains(field) ? {id: field.id, start: field.selectionStart, end: field.selectionEnd} : null;
        root.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(target.href, {headers: {'Accept': 'application/json'}, signal: controller.signal});
            const data = await response.json();
            if (!response.ok || typeof data.html !== 'string') throw new Error('Daftar belum dapat diperbarui.');
            if (current !== version) return;
            const previousAction = root.querySelector('[data-expired-filter]').action;
            const previousSwitch = root.querySelector('.warehouse-scope-switch');
            root.innerHTML = data.html;
            const nextSwitch = root.querySelector('.warehouse-scope-switch');
            if (previousSwitch && nextSwitch && typeof nextSwitch.replaceWith === 'function') {
                previousSwitch.dataset.scope = nextSwitch.dataset.scope;
                for (const next of nextSwitch.querySelectorAll('a[data-warehouse-scope]')) {
                    const existing = previousSwitch.querySelector(`a[data-warehouse-scope="${next.dataset.warehouseScope}"]`);
                    if (existing) {
                        existing.href = next.href;
                        if (next.hasAttribute('aria-current')) existing.setAttribute('aria-current', 'page');
                        else existing.removeAttribute('aria-current');
                    }
                }
                nextSwitch.replaceWith(previousSwitch);
            }
            for (const link of document.querySelectorAll?.('#mobileSidebar a[href]') || []) {
                if (link.href === previousAction) link.href = target.origin + target.pathname;
            }
            if (push) history.pushState({expiredWorkspace: true}, '', target.href);
            if (focus?.id) {
                const replacement = document.getElementById(focus.id);
                replacement?.focus({preventScroll: true});
                if (replacement && typeof replacement.setSelectionRange === 'function' && focus.start !== null) {
                    try { replacement.setSelectionRange(focus.start, focus.end); } catch (_) {}
                }
            }
        } catch (error) {
            if (error.name !== 'AbortError' && current === version) {
                root.querySelector('[data-expired-feedback]').textContent = 'Daftar belum diperbarui. Periksa koneksi dan coba lagi.';
                const retry = document.createElement('a'); retry.href = target.href; retry.textContent = ' Coba muat halaman';
                root.querySelector('[data-expired-feedback]').append(retry);
            }
        } finally {
            if (current === version) { root.removeAttribute('aria-busy'); root.querySelector('.warehouse-scope-switch')?.removeAttribute('data-pending-scope'); }
        }
    };
    const filter = () => {
        const form = root.querySelector('[data-expired-filter]');
        const url = new URL(form.action);
        new FormData(form).forEach((value, key) => { if (value) url.searchParams.set(key, value); });
        load(url.href);
    };
    root.addEventListener('click', event => {
        const link = event.target.closest('[data-expired-link], [data-warehouse-scope], [data-expired-pagination] a');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault(); clearTimeout(timer);
        if (link.dataset.warehouseScope) link.closest('.warehouse-scope-switch').dataset.pendingScope = link.dataset.warehouseScope;
        load(link.href);
    });
    root.addEventListener('input', event => { if (event.target.name === 'search') { controller?.abort(); version++; root.removeAttribute('aria-busy'); clearTimeout(timer); timer = setTimeout(filter, 200); } });
    root.addEventListener('change', event => { if (event.target.name === 'location_id') { clearTimeout(timer); filter(); } });
    root.addEventListener('submit', event => { if (event.target.matches('[data-expired-filter]')) { event.preventDefault(); clearTimeout(timer); filter(); } });
    window.addEventListener('popstate', () => { clearTimeout(timer); load(location.href, false); });
})();
