const scopeSwitch = document.querySelector('.warehouse-scope-switch');

if (scopeSwitch) {
    scopeSwitch.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-warehouse-scope]');
        if (!link || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }

        const scope = link.dataset.warehouseScope;
        if (scope === scopeSwitch.dataset.scope && !scopeSwitch.dataset.pendingScope) {
            event.preventDefault();
            return;
        }

        scopeSwitch.dataset.pendingScope = scope;
    });

    window.addEventListener('pageshow', () => {
        delete scopeSwitch.dataset.pendingScope;
    });
}
