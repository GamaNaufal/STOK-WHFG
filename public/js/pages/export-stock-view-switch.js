// Native links keep server authorization, forms, history and per-page scripts intact.
// Loaded in the head so pagereveal can run before the first rendered frame.
(() => {
    const nav = () => document.querySelector('[data-stock-view]');
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    let keyboard = false;
    const reset = () => {
        const control = nav();
        if (!control) return;
        control.dataset.motion = 'false';
        delete control.dataset.pendingView;
        control.removeAttribute('aria-busy');
        keyboard = false;
    };
    const isViewChange = (from, to) => {
        const link = nav()?.querySelector('[data-stock-view-link="stock"]');
        if (!link || !from || !to) return false;
        try {
            const a = new URL(from), b = new URL(to), stock = new URL(link.href);
            const view = url => url.searchParams.get('customer_view') === 'reservations' ? 'reservations' : 'stock';
            return a.origin === stock.origin && b.origin === stock.origin
                && a.pathname === stock.pathname && b.pathname === stock.pathname && view(a) !== view(b);
        } catch (_) { return false; }
    };
    document.addEventListener('click', event => {
        const link = event.target.closest?.('[data-stock-view-link]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey
            || event.shiftKey || event.altKey || link.hasAttribute('download') || link.target && link.target !== '_self') return;
        const control = link.closest('[data-stock-view]');
        if (!control) return;
        const target = link.dataset.stockViewLink;
        if (target === control.dataset.stockView && !control.dataset.pendingView) {
            event.preventDefault();
            return;
        }
        keyboard = event.detail === 0;
        control.dataset.motion = String(!keyboard && !reduce.matches);
        control.dataset.pendingView = target;
        control.setAttribute('aria-busy', 'true');
        // No timer or interception: navigation starts in this same click.
    });
    document.addEventListener('keydown', event => {
        keyboard = true;
        const control = nav();
        if (control) {
            control.dataset.motion = 'false';
            if (event.key === 'Escape') {
                delete control.dataset.pendingView;
                control.removeAttribute('aria-busy');
            }
        }
    }, true);
    window.addEventListener('pageswap', event => {
        if (event.viewTransition && (keyboard || reduce.matches
            || !isViewChange(location.href, event.activation?.entry?.url))) event.viewTransition.skipTransition();
    });
    window.addEventListener('pagereveal', event => {
        reset();
        if (event.viewTransition && (reduce.matches
            || !isViewChange(window.navigation?.activation?.from?.url, location.href))) event.viewTransition.skipTransition();
    });
    reduce.addEventListener('change', () => {
        if (reduce.matches && nav()) nav().dataset.motion = 'false';
    });
    window.addEventListener('pageshow', reset);
    window.navigation?.addEventListener('navigateerror', reset);
})();
