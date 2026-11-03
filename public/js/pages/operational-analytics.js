const numberFormat = new Intl.NumberFormat('id-ID');
export const formatMetric = (value, decimals = 0) => new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: decimals, maximumFractionDigits: decimals,
}).format(Number.isFinite(Number(value)) ? Number(value) : 0);

// Hit testing uses the same fixed SVG coordinate system as the server-rendered plot.
export function nearestChartIndex(rows, clientX, rect) {
    if (!rows.length || !rect || rect.width <= 0 || !Number.isFinite(clientX)) return -1;
    const scale = Math.min(rect.width / 720, rect.height > 0 ? rect.height / 268 : Infinity);
    const left = rect.left + (rect.width - 720 * scale) / 2;
    const x = (clientX - left) / scale;
    let low = 0, high = rows.length - 1;
    while (low < high) {
        const middle = Math.floor((low + high) / 2);
        if (rows[middle].x < x) low = middle + 1;
        else high = middle;
    }
    return low > 0 && Math.abs(rows[low - 1].x - x) <= Math.abs(rows[low].x - x) ? low - 1 : low;
}

export function chartKeyIndex(key, index, count) {
    if (!count) return -1;
    if (key === 'Home') return 0;
    if (key === 'End') return count - 1;
    if (key === 'ArrowLeft') return Math.max(0, index - 1);
    if (key === 'ArrowRight') return Math.min(count - 1, index + 1);
    return index;
}

export function toggleChartSeries(state, key) {
    if (!['first', 'second'].includes(key)) return state;
    const next = {...state, [key]: !state[key]};
    // Keep one visible series so an accidental double toggle cannot blank a chart.
    if (!next.first && !next.second) next[key === 'first' ? 'second' : 'first'] = true;
    return next;
}

export function initOperationalAnalytics({documentRef = globalThis.document, windowRef = globalThis.window} = {}) {
    const host = documentRef?.querySelector('[data-report-browser]');
    if (!host?.querySelector('[data-analytics-dashboard]')) return;
    const preference = windowRef.matchMedia('(prefers-reduced-motion: reduce)');
    const state = {view: 'flow', detail: 'orders', advanced: null, motion: true, series: new Map(), focusId: null};
    try { state.motion = windowRef.localStorage.getItem('whfg.dashboard.motion') !== 'off'; } catch {}
    let cleanup = () => {};
    const mount = () => {
        cleanup();
        const dashboard = host.querySelector('[data-analytics-dashboard]');
        if (!dashboard) return;
        const abort = new AbortController(), frames = new Map();
        const options = {signal: abort.signal};
        const motion = () => state.motion && !preference.matches;
        const stopCounters = () => {
            frames.forEach((id, element) => { windowRef.cancelAnimationFrame(id); element.textContent = formatMetric(element.dataset.count, Number(element.dataset.decimals || 0)); });
            frames.clear();
        };
        const updateMotion = () => {
            dashboard.dataset.motion = motion() ? 'on' : 'off';
            const toggle = dashboard.querySelector('[data-motion-toggle]');
            toggle.hidden = false;
            toggle.setAttribute('aria-pressed', String(motion()));
            toggle.querySelector('[data-motion-label]').textContent = preference.matches ? 'Gerak dikurangi' : state.motion ? 'Animasi aktif' : 'Animasi mati';
            toggle.title = preference.matches ? 'Mengikuti pengaturan kurangi gerakan perangkat' : 'Aktifkan atau matikan animasi dashboard';
            if (!motion()) stopCounters();
        };
        updateMotion();
        preference.addEventListener('change', updateMotion, options);
        dashboard.querySelector('[data-motion-toggle]').addEventListener('click', () => {
            state.motion = !state.motion;
            try { windowRef.localStorage.setItem('whfg.dashboard.motion', state.motion ? 'on' : 'off'); } catch {}
            updateMotion();
        }, options);
        const animateCounter = element => {
            if (!motion()) return;
            const target = Number(element.dataset.count), decimals = Number(element.dataset.decimals || 0);
            if (!target || !Number.isFinite(target)) return;
            let start;
            // The screen reader receives the final value throughout the visual count-up.
            element.setAttribute('aria-label', formatMetric(target, decimals));
            const step = time => {
                start ??= time;
                const progress = Math.min(1, (time - start) / 700);
                element.textContent = formatMetric(target * (1 - (1 - progress) ** 3), decimals);
                if (progress < 1) frames.set(element, windowRef.requestAnimationFrame(step));
                else frames.delete(element);
            };
            frames.set(element, windowRef.requestAnimationFrame(step));
        };
        dashboard.querySelectorAll('[data-count]').forEach(animateCounter);
        dashboard.classList.add('is-enhanced');
        const advanced = dashboard.querySelector('[data-advanced-filters]');
        if (state.advanced !== null) advanced.open = state.advanced;
        advanced.addEventListener('toggle', () => { state.advanced = advanced.open; }, options);

        const tabs = [...dashboard.querySelectorAll('[data-detail-tab]')];
        const panels = [...dashboard.querySelectorAll('[data-detail-panel]')];
        dashboard.querySelector('.od-detail-tabs').setAttribute('role', 'tablist');
        const selectDetail = (key, focus = false) => {
            if (!tabs.some(tab => tab.dataset.detailTab === key)) return;
            state.detail = key;
            tabs.forEach(tab => {
                const active = tab.dataset.detailTab === key;
                tab.setAttribute('role', 'tab');
                tab.setAttribute('aria-controls', tab.hash.slice(1));
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
                if (focus && active) tab.focus({preventScroll: true});
            });
            panels.forEach(panel => {
                const active = panel.dataset.detailPanel === key;
                panel.hidden = !active;
                panel.setAttribute('role', 'tabpanel');
                panel.setAttribute('aria-labelledby', `od-tab-${panel.dataset.detailPanel}`);
                panel.tabIndex = 0;
            });
        };
        const hashTab = tabs.find(tab => tab.hash === windowRef.location.hash);
        if (hashTab) state.detail = hashTab.dataset.detailTab;
        // Opening a paginated audit link directly must reveal the audit panel.
        if (new URL(windowRef.location.href).searchParams.has('audit_page')) state.detail = 'audit';
        selectDetail(state.detail);
        const hashChanged = () => {
            const tab = tabs.find(item => item.hash === windowRef.location.hash);
            if (tab) selectDetail(tab.dataset.detailTab);
        };
        windowRef.addEventListener('hashchange', hashChanged, options);
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', event => {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                event.preventDefault(); selectDetail(tab.dataset.detailTab);
            }, options);
            tab.addEventListener('keydown', event => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
                selectDetail(tabs[next].dataset.detailTab, true);
                tabs[next].scrollIntoView({block: 'nearest', inline: 'nearest', behavior: 'instant'});
            }, options);
        });
        dashboard.querySelectorAll('[data-open-detail]').forEach(link => link.addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            selectDetail(link.dataset.openDetail, true);
            dashboard.querySelector('[data-analytics-details]').scrollIntoView({block: 'start', behavior: motion() ? 'smooth' : 'instant'});
        }, options));

        const charts = [...dashboard.querySelectorAll('[data-analytics-chart]')];
        const observer = 'IntersectionObserver' in windowRef ? new windowRef.IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) { entry.target.classList.add('is-chart-visible'); observer.unobserve(entry.target); }
            });
        }, {threshold: .15}) : null;
        const viewButtons = [...dashboard.querySelectorAll('[data-chart-view]')];
        dashboard.querySelector('.od-metric-switch').hidden = false;
        const selectView = key => {
            state.view = key;
            viewButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.chartView === key)));
            charts.forEach(chart => {
                chart.hidden = chart.dataset.analyticsChart !== key;
                if (!chart.hidden && !chart.classList.contains('is-chart-visible')) {
                    if (observer) observer.observe(chart); else chart.classList.add('is-chart-visible');
                }
            });
        };
        viewButtons.forEach(button => button.addEventListener('click', () => selectView(button.dataset.chartView), options));
        selectView(state.view);
        charts.forEach(chart => {
            let rows;
            try { rows = JSON.parse(chart.querySelector('[data-chart-data]').textContent); } catch { return; }
            const plot = chart.querySelector('[data-chart-plot]');
            const cursor = chart.querySelector('[data-chart-cursor]');
            const date = chart.querySelector('[data-chart-date]'), first = chart.querySelector('[data-chart-first]'), second = chart.querySelector('[data-chart-second]');
            const unpin = chart.querySelector('[data-unpin-point]');
            const defaults = [date.textContent, first.textContent, second.textContent];
            let index = -1, pinned = false, pointerStart = null;
            const seriesKey = chart.dataset.chartId;
            let series = state.series.get(seriesKey) ?? {first: true, second: true};
            const setSeries = () => {
                for (const key of ['first', 'second']) {
                    chart.querySelectorAll(`[data-series="${key}"]`).forEach(element => element.classList.toggle('is-series-hidden', !series[key]));
                    chart.querySelector(`[data-toggle-series="${key}"]`).setAttribute('aria-pressed', String(series[key]));
                    (key === 'first' ? first : second).hidden = !series[key];
                }
                state.series.set(seriesKey, series);
            };
            chart.querySelectorAll('[data-toggle-series]').forEach(button => {
                button.disabled = false;
                button.addEventListener('click', () => { series = toggleChartSeries(series, button.dataset.toggleSeries); setSeries(); }, options);
            });
            setSeries();
            if (!rows.length || !rows.some(row => row.first || row.second)) return;
            chart.querySelector('[data-chart-hint]').textContent = 'Geser · klik untuk tahan';
            plot.tabIndex = 0;
            plot.setAttribute('role', 'group');
            plot.removeAttribute('aria-labelledby');
            plot.setAttribute('aria-describedby', `${chart.dataset.chartId}-description`);
            plot.setAttribute('aria-label', `${chart.querySelector('h4').textContent}. Gunakan panah kiri/kanan untuk meninjau titik, Home/End, Enter untuk menahan, Escape untuk melepas.`);
            const show = (next, announce = false) => {
                if (next < 0 || next >= rows.length) return;
                index = next;
                const row = rows[index];
                cursor.setAttribute('visibility', 'visible');
                cursor.querySelector('line').setAttribute('x1', row.x); cursor.querySelector('line').setAttribute('x2', row.x);
                for (const key of ['first', 'second']) {
                    const circle = cursor.querySelector(`[data-series="${key}"]`);
                    circle.setAttribute('cx', row.x); circle.setAttribute('cy', row[`${key}Y`]);
                }
                date.textContent = row.label;
                first.textContent = `${chart.dataset.firstLabel} ${numberFormat.format(row.first)} ${chart.dataset.unit}`;
                second.textContent = `${chart.dataset.secondLabel} ${numberFormat.format(row.second)} ${chart.dataset.unit}`;
                if (announce) dashboard.querySelector('[data-chart-announcement]').textContent = `${row.label}. ${first.hidden ? '' : first.textContent}. ${second.hidden ? '' : second.textContent}.`;
            };
            const release = () => {
                pinned = false; index = -1; unpin.hidden = true;
                cursor.setAttribute('visibility', 'hidden');
                [date, first, second].forEach((element, i) => { element.textContent = defaults[i]; });
            };
            unpin.addEventListener('click', () => { release(); plot.focus({preventScroll: true}); }, options);
            plot.addEventListener('pointermove', event => { if (!pinned) show(nearestChartIndex(rows, event.clientX, plot.getBoundingClientRect())); }, options);
            plot.addEventListener('pointerdown', event => { pointerStart = {x: event.clientX, y: event.clientY}; }, options);
            plot.addEventListener('pointerup', event => {
                if (!pointerStart || Math.abs(event.clientY - pointerStart.y) > 18) { pointerStart = null; return; }
                show(nearestChartIndex(rows, event.clientX, plot.getBoundingClientRect()), true);
                pinned = true; unpin.hidden = false; pointerStart = null;
            }, options);
            plot.addEventListener('pointercancel', () => { pointerStart = null; }, options);
            plot.addEventListener('pointerleave', () => { if (!pinned) release(); }, options);
            plot.addEventListener('focus', () => { if (index < 0) show(rows.length - 1, true); }, options);
            plot.addEventListener('blur', () => { if (!pinned) release(); }, options);
            plot.addEventListener('keydown', event => {
                if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) { event.preventDefault(); show(chartKeyIndex(event.key, Math.max(0, index), rows.length), true); }
                else if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); pinned = !pinned; unpin.hidden = !pinned; }
                else if (event.key === 'Escape') { event.preventDefault(); release(); }
            }, options);
        });
        // A collapsed server fallback may precede restoring the open filter panel.
        // Restore focus only if replacing the old element released it to the body.
        if (state.focusId && documentRef.activeElement === documentRef.body) {
            documentRef.getElementById(state.focusId)?.focus({preventScroll: true});
        }
        cleanup = () => { abort.abort(); observer?.disconnect(); stopCounters(); };
    };
    const observer = new windowRef.MutationObserver(mount);
    const observe = () => observer.observe(host, {childList: true}); // Only AJAX replacement; chart readouts never remount the page.
    const focusChanged = event => { state.focusId = host.contains(event.target) ? event.target.id || null : null; };
    const pageHidden = () => { cleanup(); observer.disconnect(); };
    const pageShown = event => { if (event.persisted) { observe(); mount(); } };
    documentRef.addEventListener('focusin', focusChanged);
    windowRef.addEventListener('pagehide', pageHidden);
    windowRef.addEventListener('pageshow', pageShown);
    observe();
    mount();
    return {state, destroy: () => {
        cleanup(); observer.disconnect();
        documentRef.removeEventListener('focusin', focusChanged);
        windowRef.removeEventListener('pagehide', pageHidden);
        windowRef.removeEventListener('pageshow', pageShown);
    }};
}

if (typeof document !== 'undefined') initOperationalAnalytics();
