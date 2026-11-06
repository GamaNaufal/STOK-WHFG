(() => {
    const form = document.getElementById('schedule-filters');
    const results = document.getElementById('schedule-results');
    if (!form || !results || !window.fetch || !window.AbortController) return;

    const search = document.getElementById('schedule-search');
    const month = document.getElementById('schedule-month');
    const status = document.getElementById('schedule-status');
    const reset = document.getElementById('schedule-reset');
    const retry = document.getElementById('schedule-retry');
    const feedback = document.getElementById('schedule-feedback');
    const list = document.getElementById('schedule-suggestions');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const hover = window.matchMedia('(hover: hover) and (pointer: fine)');
    const tooltip = window.bootstrap?.Tooltip;
    let tooltips = [];
    let displayedMonth = month.value;
    let stopMonthSlide = () => {};
    let sequence = 0, timer, controller, selection = -1, dismissed = false;

    const loadPreviews = () => {
        if (!tooltip) return;
        tooltips = [...results.querySelectorAll('[data-delivery-preview]')].map(link => new tooltip(link, {
            animation: false,
            boundary: 'viewport',
            container: document.body,
            customClass: 'export-schedule-preview',
            delay: {show: 130, hide: 0},
            trigger: hover.matches ? 'hover focus' : 'focus',
        }));
    };
    loadPreviews();

    form.classList.add('is-live');
    search.setAttribute('role', 'combobox');
    search.setAttribute('aria-autocomplete', 'list');
    search.setAttribute('aria-controls', list.id);
    search.setAttribute('aria-expanded', 'false');

    const showList = open => {
        list.hidden = !open;
        search.setAttribute('aria-expanded', String(open));
        if (open) return;
        list.children[selection]?.setAttribute('aria-selected', 'false');
        search.removeAttribute('aria-activedescendant');
        selection = -1;
    };
    const renderSuggestions = suggestions => {
        list.replaceChildren(...suggestions.map((item, index) => {
            const option = document.createElement('li');
            option.id = `schedule-suggestion-${index}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.dataset.url = item.url;
            const name = document.createElement('strong');
            name.textContent = item.customer;
            const detail = document.createElement('small');
            const [year, dateMonth, day] = item.deadline.split('-');
            detail.textContent = `${item.code || 'Tanpa kode'} · ${day}/${dateMonth}/${year}`;
            option.append(name, detail);
            return option;
        }));
        showList(!dismissed && document.activeElement === search && list.children.length > 0);
    };

    const urlFor = (selectedMonth, query, selectedStatus) => {
        const url = new URL(form.action);
        for (const [key, value] of [['month', selectedMonth], ['q', query], ['status', selectedStatus]]) {
            if (value) url.searchParams.set(key, value);
        }
        return url;
    };
    const prepareRequest = () => {
        sequence += 1;
        clearTimeout(timer);
        controller?.abort();
        controller = null;
        showList(false);
        list.replaceChildren();
        feedback.textContent = '';
        feedback.classList.add('visually-hidden');
        retry.hidden = true;
        reset.hidden = !search.value.trim() && !status.value;
        results.removeAttribute('aria-busy');
        return sequence;
    };
    const slideMonth = (outgoing, scrollLeft, direction) => {
        const incoming = results.querySelector('.export-schedule-scroll');
        if (!outgoing || !incoming?.animate) return;

        outgoing.classList.add('export-schedule-scroll--outgoing');
        outgoing.setAttribute('aria-hidden', 'true');
        outgoing.inert = true;
        incoming.after(outgoing);
        outgoing.scrollLeft = scrollLeft;

        const timing = {duration: 200, easing: 'cubic-bezier(.77, 0, .175, 1)'};
        const enter = incoming.animate([{transform: `translateX(${direction * 100}%)`}, {transform: 'translateX(0)'}], timing);
        const exit = outgoing.animate([{transform: 'translateX(0)'}, {transform: `translateX(${direction * -100}%)`}], timing);
        stopMonthSlide = () => {
            exit.onfinish = null;
            enter.cancel();
            exit.cancel();
            outgoing.remove();
            stopMonthSlide = () => {};
        };
        exit.onfinish = stopMonthSlide;
    };
    reducedMotion.addEventListener('change', () => {
        if (reducedMotion.matches) stopMonthSlide();
    });
    const load = async (version, monthAction) => {
        if (version !== sequence || !form.checkValidity()) return;
        const url = urlFor(month.value, search.value.trim(), status.value);
        controller = new AbortController();
        const request = controller;
        const timeout = setTimeout(() => request.abort(), 12000);
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}, signal: request.signal});
            if (!response.ok) throw new Error('Calendar request failed');
            const data = await response.json();
            if (version !== sequence) return;
            const active = document.activeElement;
            const hadFocus = results.contains(active);
            const step = active?.dataset?.scheduleMonth;
            const activeDelivery = active?.matches?.('[data-delivery-preview]') ? active.href : null;
            const monthChanged = displayedMonth !== data.month;
            const slideDirection = monthAction === 'next' ? 1 : monthAction === 'previous' ? -1 : 0;
            stopMonthSlide();
            const outgoing = monthChanged && slideDirection && !reducedMotion.matches
                ? results.querySelector('.export-schedule-scroll') : null;
            const scrollLeft = outgoing?.scrollLeft ?? 0;
            tooltips.forEach(instance => instance.dispose());
            results.innerHTML = data.calendar;
            loadPreviews();
            if (hadFocus) {
                const destination = (step && results.querySelector(`[data-schedule-month="${step}"]`))
                    || (activeDelivery && [...results.querySelectorAll('[data-delivery-preview]')].find(link => link.href === activeDelivery))
                    || results;
                destination.focus({preventScroll: true});
            }
            if (outgoing) {
                slideMonth(outgoing, scrollLeft, slideDirection);
            } else if (monthAction === 'today' && monthChanged && !reducedMotion.matches) {
                results.querySelector('.export-schedule-table')?.animate?.([{opacity: .8}, {opacity: 1}], {duration: 150, easing: 'cubic-bezier(.23, 1, .32, 1)'});
            }
            displayedMonth = month.value = data.month;
            reset.href = urlFor(data.month, '', '');
            window.history.replaceState(window.history.state, '', url);
            renderSuggestions(data.suggestions);
            feedback.textContent = `${data.total} delivery ditampilkan pada kalender.`;
        } catch {
            if (version !== sequence) return;
            feedback.textContent = 'Jadwal gagal diperbarui. Kalender sebelumnya masih ditampilkan.';
            feedback.classList.remove('visually-hidden');
            retry.hidden = false;
        } finally {
            clearTimeout(timeout);
            if (version === sequence) {
                results.removeAttribute('aria-busy');
                controller = null;
            }
        }
    };
    const apply = (delay = 0, monthAction = '') => {
        const version = prepareRequest();
        if (delay) timer = setTimeout(() => load(version, monthAction), delay);
        else load(version, monthAction);
    };
    const standardClick = event => event.button === 0 && !event.ctrlKey && !event.metaKey && !event.altKey && !event.shiftKey;
    const open = option => { if (option) window.location.assign(option.dataset.url); };

    search.addEventListener('input', event => {
        if (event.isComposing) return;
        dismissed = false;
        apply(search.value.trim() ? 220 : 0);
    });
    search.addEventListener('compositionend', () => { dismissed = false; apply(220); });
    search.addEventListener('focus', () => {
        dismissed = false;
        showList(list.children.length > 0);
    });
    search.addEventListener('blur', () => showList(false));
    search.addEventListener('keydown', event => {
        if (event.isComposing) return;
        if (event.key === 'Escape' && !list.hidden) {
            event.preventDefault();
            dismissed = true;
            showList(false);
        } else if (['ArrowDown', 'ArrowUp'].includes(event.key) && list.children.length) {
            event.preventDefault();
            dismissed = false;
            showList(true);
            const count = list.children.length;
            list.children[selection]?.setAttribute('aria-selected', 'false');
            selection = selection < 0 ? (event.key === 'ArrowDown' ? 0 : count - 1)
                : (selection + (event.key === 'ArrowDown' ? 1 : count - 1)) % count;
            const option = list.children[selection];
            option.setAttribute('aria-selected', 'true');
            search.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({block: 'nearest'});
        } else if (event.key === 'Enter' && selection >= 0 && !list.hidden) {
            event.preventDefault();
            open(list.children[selection]);
        }
    });
    list.addEventListener('mousedown', event => event.preventDefault());
    list.addEventListener('click', event => open(event.target.closest('[role="option"]')));
    form.addEventListener('submit', event => { event.preventDefault(); apply(); });
    status.addEventListener('change', () => apply());
    month.addEventListener('change', () => apply());
    retry.addEventListener('click', () => { search.focus({preventScroll: true}); apply(); });
    reset.addEventListener('click', event => {
        if (!standardClick(event)) return;
        event.preventDefault();
        search.value = status.value = '';
        search.focus({preventScroll: true});
        apply();
    });
    results.addEventListener('click', event => {
        const link = event.target.closest('[data-schedule-month]');
        if (!link || !standardClick(event)) return;
        event.preventDefault();
        month.value = new URL(link.href).searchParams.get('month');
        apply(0, event.detail > 0 ? link.dataset.scheduleMonth : '');
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') tooltips.forEach(instance => instance.hide());
    });
})();
