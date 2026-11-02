// Shared navigation: presentation and preferences only; server permissions remain authoritative.
export function normalizeNavigationQuery(value) {
    const aliases = {stock: 'stok', export: 'ekspor', import: 'impor', palet: 'pallet', lokal: 'domestik'};
    return String(value ?? '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()
        .replace(/[^\p{L}\p{N}]+/gu, ' ').trim().split(/\s+/).filter(Boolean)
        .map(word => aliases[word] ?? word).join(' ');
}

export function searchNavigation(features, query, recentRoutes = []) {
    const normalized = normalizeNavigationQuery(query);
    if (!normalized) {
        const byRoute = new Map(features.map(item => [item.route, item]));
        const recent = recentRoutes.map(route => byRoute.get(route)).filter(Boolean);
        return [...new Map([...recent, ...features].map(item => [item.route, item])).values()];
    }
    const words = normalized.split(' ');
    return features.map(item => {
        const label = normalizeNavigationQuery(item.label);
        const haystack = normalizeNavigationQuery(`${item.label} ${item.group ?? ''} ${item.keywords ?? ''}`);
        if (!words.every(word => haystack.includes(word))) return null;
        const labelWords = label.split(' ');
        const score = (label.startsWith(normalized) ? 6 : label.includes(normalized) ? 4 : 0)
            + words.reduce((score, word) => score + (labelWords.some(part => part.startsWith(word)) ? 2 : label.includes(word) ? 1 : 0), 0);
        return {item, score};
    }).filter(Boolean).sort((a, b) => b.score - a.score || a.item.label.localeCompare(b.item.label, 'id'))
        .map(result => result.item);
}

export function nextNavigationSuggestion(index, direction, count) {
    if (!count) return -1;
    if (index < 0) return direction === 'up' ? count - 1 : 0;
    return (index + (direction === 'up' ? -1 : 1) + count) % count;
}

export function readNavigationPreferences(storage, key) {
    try {
        const value = JSON.parse(storage.getItem(key) ?? '{}');
        return {collapsed: value?.collapsed === true,
            groups: Object.fromEntries(Object.entries(value?.groups ?? {}).filter(([, open]) => typeof open === 'boolean')),
            recent: Array.isArray(value?.recent) ? value.recent.filter(route => typeof route === 'string').slice(0, 5) : []};
    } catch (_) {
        return {collapsed: false, groups: {}, recent: []};
    }
}

export function initNavigation({documentRef = globalThis.document, windowRef = globalThis.window} = {}) {
    const sidebar = documentRef.getElementById('mobileSidebar');
    const toggle = documentRef.getElementById('sidebarToggleBtn');
    const navbar = documentRef.querySelector('.top-navbar');
    if (!sidebar || !toggle || !navbar) return;
    const scrollArea = sidebar.querySelector('.offcanvas-body');
    const input = documentRef.getElementById('globalSearchInput');
    const form = documentRef.getElementById('globalSearchForm');
    const popup = documentRef.getElementById('globalSearchDropdown');
    const results = documentRef.getElementById('globalSearchResults');
    const summary = documentRef.getElementById('globalSearchSummary');
    const empty = documentRef.getElementById('globalSearchEmpty');
    const clear = documentRef.getElementById('globalSearchClear');
    const showAll = documentRef.getElementById('globalSearchShowAll');
    const account = navbar.querySelector('.avatar-trigger');
    const key = `whfg.navigation.v2.${sidebar.dataset.navigationUser}`;
    let storage;
    try { storage = windowRef.localStorage; } catch (_) { /* Preferences are optional. */ }
    const preferences = readNavigationPreferences(storage, key);
    const persist = () => { try { storage?.setItem(key, JSON.stringify(preferences)); } catch (_) {} };
    const desktop = () => windowRef.innerWidth >= 992;
    const drawerActive = () => ['show', 'showing', 'hiding'].some(name => sidebar.classList.contains(name));
    let pendingHeaderAction = null;
    let suppressSearchFocus = false;
    let suggestions = [], activeIndex = -1, expandedResults = false;
    let features = [];
    try { features = JSON.parse(documentRef.getElementById('global-search-features-json')?.textContent ?? '[]'); } catch (_) {}

    function cleanupBackdrops() {
        if (documentRef.querySelector('.modal.show, .modal.showing, .offcanvas.show, .offcanvas.showing, .offcanvas.hiding')) return;
        documentRef.querySelectorAll('.modal-backdrop, .offcanvas-backdrop').forEach(node => node.remove());
        documentRef.body.classList.remove('modal-open');
        documentRef.body.style.removeProperty('overflow');
        documentRef.body.style.removeProperty('padding-right');
    }

    function syncHeight() {
        documentRef.documentElement.style.setProperty('--top-navbar-height', `${Math.ceil(navbar.getBoundingClientRect().height)}px`);
    }

    function syncSidebar() {
        const collapsed = desktop() && preferences.collapsed;
        documentRef.body.classList.toggle('sidebar-collapsed', collapsed);
        sidebar.toggleAttribute('inert', collapsed);
        if (desktop()) sidebar.removeAttribute('aria-hidden');
        const open = desktop() ? !collapsed : drawerActive() && !sidebar.classList.contains('hiding');
        toggle.setAttribute('aria-expanded', String(open));
        const label = open ? 'Sembunyikan navigasi' : 'Tampilkan navigasi';
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
    }

    function saveScroll() {
        if (!scrollArea || (!desktop() && !drawerActive()) || documentRef.body.classList.contains('sidebar-collapsed')) return;
        try { windowRef.sessionStorage.setItem(`${key}.scroll`, String(scrollArea.scrollTop)); } catch (_) {}
    }

    function ensureActiveVisible() {
        if ((!desktop() && !drawerActive()) || documentRef.body.classList.contains('sidebar-collapsed')) return;
        const active = sidebar.querySelector('[aria-current="page"]');
        if (!active || !scrollArea?.clientHeight) return;
        const target = active.getBoundingClientRect(), area = scrollArea.getBoundingClientRect();
        if (target.top < area.top + 12) scrollArea.scrollTop += target.top - area.top - 12;
        else if (target.bottom > area.bottom - 12) scrollArea.scrollTop += target.bottom - area.bottom + 12;
    }

    function restoreScroll() {
        try {
            const stored = Number(windowRef.sessionStorage.getItem(`${key}.scroll`));
            if (Number.isFinite(stored) && stored >= 0) scrollArea.scrollTop = stored;
        } catch (_) {}
        ensureActiveVisible();
    }

    function hideSearch() {
        if (!popup) return;
        popup.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        activeIndex = -1;
        expandedResults = false;
    }

    function hideAccount() {
        if (account) windowRef.bootstrap?.Dropdown.getInstance(account)?.hide();
    }

    function closeDrawer(after) {
        if (!drawerActive()) { after?.(); return; }
        pendingHeaderAction = after ?? null;
        if (!sidebar.classList.contains('hiding')) windowRef.bootstrap.Offcanvas.getOrCreateInstance(sidebar).hide();
    }

    function renderSearch() {
        const matches = searchNavigation(features, input.value, preferences.recent);
        suggestions = expandedResults ? matches : matches.slice(0, 8);
        activeIndex = -1;
        input.removeAttribute('aria-activedescendant');
        results.replaceChildren();
        suggestions.forEach((item, index) => {
            const button = documentRef.createElement('button');
            button.type = 'button'; button.className = 'global-search-item'; button.id = `global-search-option-${index}`;
            button.dataset.index = String(index); button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', 'false'); button.tabIndex = -1;
            const label = documentRef.createElement('span'); label.className = 'feature-label';
            const icon = documentRef.createElement('i'); icon.className = `bi bi-${item.icon || 'arrow-right-circle'}`; icon.setAttribute('aria-hidden', 'true');
            const text = documentRef.createElement('span'); text.textContent = item.label;
            label.append(icon, text);
            const group = documentRef.createElement('span'); group.className = 'feature-group'; group.textContent = item.group ?? '';
            button.append(label, group); results.append(button);
        });
        const noun = input.value.trim() ? 'hasil' : 'menu';
        summary.textContent = `${suggestions.length} dari ${matches.length} ${noun}`;
        empty.hidden = matches.length > 0;
        showAll.hidden = suggestions.length >= matches.length;
        showAll.textContent = `Lihat semua ${matches.length} ${noun}`;
        clear.hidden = !input.value;
        popup.hidden = false; input.setAttribute('aria-expanded', 'true');
    }

    function openSearch() {
        hideAccount();
        if (!desktop() && drawerActive()) {
            closeDrawer(() => { input.focus({preventScroll: true}); renderSearch(); });
            return;
        }
        renderSearch();
    }

    function setActive(index) {
        activeIndex = index;
        const nodes = [...results.querySelectorAll('.global-search-item')];
        nodes.forEach((node, i) => {
            node.classList.toggle('active', index === i);
            node.setAttribute('aria-selected', String(index === i));
        });
        const selected = nodes[index];
        if (selected) {
            input.setAttribute('aria-activedescendant', selected.id);
            selected.scrollIntoView({block: 'nearest'});
        }
    }

    function navigate(index) {
        const item = suggestions[index];
        if (!item) return;
        preferences.recent = [item.route, ...preferences.recent.filter(route => route !== item.route)].slice(0, 5);
        persist(); saveScroll();
        hideSearch(); hideAccount();
        // Native navigation retains each editor's existing unsaved-change protection.
        windowRef.location.assign(item.route);
    }

    sidebar.querySelectorAll('.menu-group-toggle').forEach(button => {
        const panel = documentRef.querySelector(button.dataset.bsTarget);
        if (!panel) return;
        const open = !!panel.querySelector('[aria-current="page"]') || preferences.groups[panel.id] === true;
        panel.classList.toggle('show', open); button.classList.toggle('collapsed', !open);
        button.setAttribute('aria-expanded', String(open));
        panel.addEventListener('shown.bs.collapse', () => {
            preferences.groups[panel.id] = true; persist();
            if (panel.querySelector('[aria-current="page"]')) ensureActiveVisible();
            saveScroll();
        });
        panel.addEventListener('hidden.bs.collapse', () => { preferences.groups[panel.id] = false; persist(); saveScroll(); });
    });

    toggle.addEventListener('click', () => {
        hideSearch(); hideAccount();
        if (!desktop()) { windowRef.bootstrap.Offcanvas.getOrCreateInstance(sidebar).toggle(); return; }
        documentRef.body.classList.toggle('navigation-motion', true);
        preferences.collapsed = !preferences.collapsed; persist(); syncSidebar();
        if (!preferences.collapsed) restoreScroll();
    });
    sidebar.addEventListener('show.bs.offcanvas', () => { hideSearch(); hideAccount(); toggle.setAttribute('aria-expanded', 'true'); });
    sidebar.addEventListener('shown.bs.offcanvas', () => { syncSidebar(); restoreScroll(); });
    sidebar.addEventListener('hide.bs.offcanvas', () => { saveScroll(); toggle.setAttribute('aria-expanded', 'false'); });
    sidebar.addEventListener('hidden.bs.offcanvas', () => {
        syncSidebar(); cleanupBackdrops();
        const action = pendingHeaderAction; pendingHeaderAction = null;
        if (action) action();
        else if (!desktop()) toggle.focus({preventScroll: true});
    });
    scrollArea?.addEventListener('scroll', saveScroll, {passive: true});
    account?.addEventListener('show.bs.dropdown', event => {
        hideSearch();
        if (!desktop() && drawerActive()) {
            event.preventDefault();
            closeDrawer(() => { account.focus({preventScroll: true}); windowRef.bootstrap.Dropdown.getOrCreateInstance(account).show(); });
        }
    });

    if (input && popup && results) {
        form.addEventListener('pointerdown', event => {
            if (!desktop() && drawerActive()) {
                event.preventDefault();
                closeDrawer(() => { input.focus({preventScroll: true}); renderSearch(); });
            }
        }, {capture: true});
        input.addEventListener('focus', () => { if (!suppressSearchFocus) openSearch(); });
        input.addEventListener('click', () => { if (popup.hidden) openSearch(); });
        input.addEventListener('input', () => { expandedResults = false; openSearch(); });
        form.addEventListener('submit', event => { event.preventDefault(); if (!popup.hidden) navigate(activeIndex >= 0 ? activeIndex : 0); });
        input.addEventListener('keydown', event => {
            if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
            event.preventDefault();
            if (popup.hidden) openSearch();
            if (event.key === 'Enter') navigate(activeIndex >= 0 ? activeIndex : 0);
            else setActive(nextNavigationSuggestion(activeIndex, event.key === 'ArrowUp' ? 'up' : 'down', suggestions.length));
        });
        results.addEventListener('click', event => {
            const button = event.target.closest('.global-search-item');
            if (button) navigate(Number(button.dataset.index));
        });
        clear.addEventListener('click', () => { input.value = ''; expandedResults = false; input.focus({preventScroll: true}); renderSearch(); });
        showAll.addEventListener('click', () => { input.focus({preventScroll: true}); expandedResults = true; renderSearch(); });
        documentRef.addEventListener('pointerdown', event => { if (!form.contains(event.target)) hideSearch(); });
        form.addEventListener('focusout', event => { if (!form.contains(event.relatedTarget)) hideSearch(); });
    }

    documentRef.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (popup && !popup.hidden) {
            event.preventDefault(); hideSearch();
            suppressSearchFocus = true; input.focus({preventScroll: true}); suppressSearchFocus = false;
        } else if (!desktop() && drawerActive()) {
            event.preventDefault(); closeDrawer();
        } else if (account?.getAttribute('aria-expanded') === 'true') {
            event.preventDefault(); hideAccount(); account.focus({preventScroll: true});
        }
    });
    windowRef.addEventListener('pagehide', saveScroll);
    windowRef.addEventListener('pageshow', () => {
        documentRef.body.classList.remove('navigation-motion');
        hideSearch(); hideAccount(); cleanupBackdrops(); syncSidebar(); syncHeight(); windowRef.requestAnimationFrame(restoreScroll);
    });
    windowRef.addEventListener('resize', () => {
        documentRef.body.classList.remove('navigation-motion');
        syncHeight();
        if (desktop() && drawerActive()) closeDrawer();
        syncSidebar();
    });
    if (windowRef.ResizeObserver) new windowRef.ResizeObserver(syncHeight).observe(navbar);
    syncHeight(); syncSidebar(); cleanupBackdrops();
    windowRef.requestAnimationFrame(restoreScroll);
}

if (typeof document !== 'undefined') initNavigation();
