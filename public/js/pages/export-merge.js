(() => {
    'use strict';
    const form = document.getElementById('export-merge-form');
    if (form) {
        const checks = [...form.querySelectorAll('.merge-source-check')];
        const rows = [...form.querySelectorAll('[data-merge-source]')];
        const search = document.getElementById('merge-search');
        const locationSearch = document.getElementById('merge-location-search');
        const select = document.getElementById('merge-location');
        const options = [...select.options];
        const update = () => {
            const checked = checks.filter(check => check.checked);
            const selected = new Set(checked.map(check => check.value));
            const planned = checked.some(check => check.dataset.planned === '1');
            const limit = document.getElementById('merge-box-limit-field');
            limit.hidden = selected.size < 2 || planned;
            limit.querySelector('input').disabled = limit.hidden;
            document.getElementById('merge-plan-options').hidden = !planned;
            form.querySelector('button[type="submit"]').disabled = selected.size < 2;
            document.getElementById('merge-selection-count').textContent = `${selected.size} pallet dipilih`;
            options.forEach(option => {
                const available = option.dataset.available === '1' || selected.has(option.dataset.source);
                option.disabled = !available;
                option.hidden = !(option.dataset.code || '').includes(locationSearch.value.trim().toLowerCase());
                if (option.selected && !available) select.selectedIndex = -1;
            });
        };
        search.addEventListener('input', () => {
            const q = search.value.trim().toLowerCase();
            rows.forEach(row => { row.hidden = !row.dataset.search.includes(q); });
            document.getElementById('merge-no-match').hidden = !rows.length || rows.some(row => !row.hidden);
        });
        checks.forEach(check => check.addEventListener('change', update));
        locationSearch.addEventListener('input', update);
        update();
    }
    document.querySelectorAll('[data-merge-confirm]').forEach(confirmForm => {
        confirmForm.addEventListener('submit', () => {
            const button = confirmForm.querySelector('button[type="submit"]');
            button.disabled = true;
            button.textContent = 'Memproses merge…';
        });
    });
})();
