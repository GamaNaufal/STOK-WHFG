<script>
    (function () {
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        const sidebarEl = document.getElementById('mobileSidebar');
        const topNavbarEl = document.querySelector('.top-navbar');
        const collapseKey = 'sidebarCollapsed';
        const globalSearchInput = document.getElementById('globalSearchInput');
        const globalSearchDropdown = document.getElementById('globalSearchDropdown');
        const globalSearchForm = document.getElementById('globalSearchForm');
        const globalSearchFeaturesJsonEl = document.getElementById('global-search-features-json');
        const recentSearchKey = 'globalFeatureSearchRecent';
        let globalSearchFeatures = [];
        try {
            globalSearchFeatures = JSON.parse(globalSearchFeaturesJsonEl?.textContent || '[]');
        } catch (error) {
            globalSearchFeatures = [];
        }
        let activeSuggestionIndex = -1;
        let currentSuggestions = [];

        function normalizeText(text) {
            return (text || '').toString().toLowerCase().trim();
        }

        function getRecentRoutes() {
            try {
                const raw = localStorage.getItem(recentSearchKey);
                const parsed = JSON.parse(raw || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        }

        function saveRecentRoute(route) {
            if (!route) return;
            const current = getRecentRoutes().filter((item) => item !== route);
            current.unshift(route);
            const trimmed = current.slice(0, 5);
            try {
                localStorage.setItem(recentSearchKey, JSON.stringify(trimmed));
            } catch (error) {
            }
        }

        function getRecentSuggestions() {
            const routeToFeature = new Map(globalSearchFeatures.map((feature) => [feature.route, feature]));
            return getRecentRoutes()
                .map((route) => routeToFeature.get(route))
                .filter(Boolean);
        }

        function getDefaultSuggestions() {
            const recent = getRecentSuggestions();
            if (recent.length > 0) {
                return recent;
            }
            return globalSearchFeatures.slice(0, 8);
        }

        function hideSuggestions() {
            if (!globalSearchDropdown) return;
            globalSearchDropdown.style.display = 'none';
            globalSearchDropdown.innerHTML = '';
            activeSuggestionIndex = -1;
            currentSuggestions = [];
        }

        function renderSuggestions(items) {
            if (!globalSearchDropdown) return;

            if (items.length === 0) {
                globalSearchDropdown.innerHTML = '<div class="global-search-empty">Fitur tidak ditemukan.</div>';
                globalSearchDropdown.style.display = 'block';
                activeSuggestionIndex = -1;
                currentSuggestions = [];
                return;
            }

            currentSuggestions = items.slice(0, 8);
            globalSearchDropdown.innerHTML = currentSuggestions.map((item, index) => `
                <button type="button" class="global-search-item" data-index="${index}" data-route="${item.route}" role="option" aria-selected="false">
                    <span class="feature-label"><i class="bi bi-${item.icon || 'arrow-right-circle'}"></i> ${item.label}</span>
                    <span class="feature-group">${item.group || ''}</span>
                </button>
            `).join('');

            globalSearchDropdown.style.display = 'block';
            activeSuggestionIndex = -1;
        }

        function findSuggestions(query) {
            const q = normalizeText(query);
            if (!q) {
                return getDefaultSuggestions();
            }

            const scored = globalSearchFeatures
                .map((item) => {
                    const label = normalizeText(item.label);
                    const group = normalizeText(item.group);
                    const keywords = normalizeText(item.keywords);
                    const haystack = `${label} ${group} ${keywords}`;

                    if (!haystack.includes(q)) {
                        return null;
                    }

                    let score = 1;
                    if (label.startsWith(q)) score += 4;
                    else if (label.includes(q)) score += 3;
                    else if (keywords.includes(q)) score += 2;

                    return { item, score };
                })
                .filter(Boolean)
                .sort((a, b) => b.score - a.score || a.item.label.localeCompare(b.item.label));

            return scored.map((row) => row.item);
        }

        function setActiveSuggestion(index) {
            if (!globalSearchDropdown) return;
            const nodes = Array.from(globalSearchDropdown.querySelectorAll('.global-search-item'));
            nodes.forEach((node, idx) => {
                const isActive = idx === index;
                node.classList.toggle('active', isActive);
                node.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
        }

        function navigateToSuggestion(index) {
            const item = currentSuggestions[index];
            if (!item || !item.route) return;
            saveRecentRoute(item.route);
            window.location.href = item.route;
        }

        function syncTopNavbarHeight() {
            if (!topNavbarEl) return;
            const h = Math.max(56, Math.round(topNavbarEl.getBoundingClientRect().height));
            document.documentElement.style.setProperty('--top-navbar-height', `${h}px`);
        }

        syncTopNavbarHeight();
        window.addEventListener('resize', syncTopNavbarHeight);

        if (window.innerWidth >= 992 && localStorage.getItem(collapseKey) === '1') {
            document.body.classList.add('sidebar-collapsed');
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                if (window.innerWidth < 992) {
                    if (!sidebarEl) return;
                    const instance = bootstrap.Offcanvas.getOrCreateInstance(sidebarEl);
                    instance.toggle();
                    return;
                }

                document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem(collapseKey, document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
            });
        }

        if (globalSearchInput && globalSearchDropdown) {
            globalSearchInput.addEventListener('focus', function () {
                renderSuggestions(findSuggestions(globalSearchInput.value));
            });

            globalSearchInput.addEventListener('input', function () {
                renderSuggestions(findSuggestions(globalSearchInput.value));
            });

            globalSearchInput.addEventListener('keydown', function (event) {
                const visibleItems = Array.from(globalSearchDropdown.querySelectorAll('.global-search-item'));
                if (!visibleItems.length) return;

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    activeSuggestionIndex = (activeSuggestionIndex + 1) % visibleItems.length;
                    setActiveSuggestion(activeSuggestionIndex);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    activeSuggestionIndex = (activeSuggestionIndex - 1 + visibleItems.length) % visibleItems.length;
                    setActiveSuggestion(activeSuggestionIndex);
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    const targetIndex = activeSuggestionIndex >= 0 ? activeSuggestionIndex : 0;
                    navigateToSuggestion(targetIndex);
                } else if (event.key === 'Escape') {
                    hideSuggestions();
                }
            });

            globalSearchDropdown.addEventListener('click', function (event) {
                const button = event.target.closest('.global-search-item');
                if (!button) return;
                const index = Number(button.dataset.index);
                navigateToSuggestion(index);
            });

            document.addEventListener('click', function (event) {
                if (!globalSearchForm?.contains(event.target)) {
                    hideSuggestions();
                }
            });
        }

        window.addEventListener('resize', function () {
            syncTopNavbarHeight();
            if (window.innerWidth < 992) {
                document.body.classList.remove('sidebar-collapsed');
            } else if (localStorage.getItem(collapseKey) === '1') {
                document.body.classList.add('sidebar-collapsed');
            }
        });
    })();
</script>