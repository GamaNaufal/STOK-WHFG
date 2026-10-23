(() => {
    const form = document.getElementById('delivery-schedule-filters');
    const results = document.getElementById('delivery-schedule-results');
    if (!form || !results) return;
    const search = document.getElementById('delivery-schedule-search');
    const timing = document.getElementById('delivery-schedule-timing');
    const count = document.getElementById('delivery-schedule-filter-count');
    const empty = document.getElementById('delivery-schedule-no-results');
    const rows = [...results.querySelectorAll('[data-schedule-search]')];
    const entries = rows.map(row => ({ row, text: row.dataset.scheduleSearch.toLocaleLowerCase(), timing: row.dataset.scheduleTiming }));
    const apply = () => {
        const query = search.value.trim().toLocaleLowerCase().replace(/^#(?=\d)/, '');
        let visible = 0;
        for (const entry of entries) {
            const matches = entry.text.includes(query) && (timing.value === 'all' || entry.timing === timing.value);
            entry.row.hidden = !matches;
            if (matches) visible++;
        }
        count.textContent = `Menampilkan ${visible.toLocaleString('en-US')} dari ${rows.length.toLocaleString('en-US')} jadwal aktif`;
        empty.hidden = visible !== 0 || rows.length === 0;
    };
    search.addEventListener('input', apply);
    timing.addEventListener('change', apply);
    form.addEventListener('submit', event => { event.preventDefault(); apply(); });
    form.addEventListener('reset', event => {
        event.preventDefault();
        search.value = '';
        timing.value = 'all';
        apply();
        search.focus({ preventScroll: true });
    });
    apply();
    form.hidden = false;
})();
