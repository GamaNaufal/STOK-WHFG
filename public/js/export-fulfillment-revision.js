(() => {
    const form = document.getElementById('revision-editor');
    if (!form) return;

    const load = form.querySelector('[data-pallet-load]');
    const loadCount = load.querySelector('[data-load-count]');
    const loadNote = load.querySelector('[data-load-note]');
    const warningAt = Number(load.dataset.warningAt);
    const dangerAt = Number(load.dataset.dangerAt);
    const numberFormat = new Intl.NumberFormat('id-ID');

    function updateTotal() {
        const total = [...form.querySelectorAll('[data-revision-quantity]')]
            .reduce((sum, input) => sum + (input.validity.valid ? input.valueAsNumber || 0 : 0), 0);
        loadCount.textContent = numberFormat.format(total) + ' box';
        load.dataset.level = total >= dangerAt ? 'danger' : total >= warningAt ? 'warning' : 'normal';
        loadNote.textContent = total >= dangerAt
            ? 'Perlu perhatian · ≥ ' + dangerAt + ' box'
            : total >= warningAt ? 'Peringatan · ≥ ' + warningAt + ' box' : '';
        loadNote.hidden = !loadNote.textContent;
    }

    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-revision-quantity]')) updateTotal();
    });

    const picker = document.getElementById('revision-part-picker');
    if (picker) {
        const search = document.getElementById('revision-part-search');
        const results = document.getElementById('revision-part-results');
        const status = document.getElementById('revision-part-status');
        const rows = document.getElementById('revision-added-parts');
        const template = document.getElementById('revision-part-template');
        const parts = JSON.parse(document.getElementById('revision-part-data').textContent);
        const initial = JSON.parse(document.getElementById('revision-added-data').textContent) ?? {};
        const selected = new Set();

        function renderResults() {
            const query = search.value.trim().toLowerCase();
            const matches = parts.filter((part) => !selected.has(part.id)
                && part.part_number.toLowerCase().includes(query));
            results.replaceChildren();
            for (const part of matches.slice(0, 8)) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-outline-secondary';
                button.textContent = part.part_number + ' · ' + numberFormat.format(part.qty_box) + ' PCS/box';
                button.addEventListener('click', () => addPart(part, 1, true));
                results.append(button);
            }
            status.textContent = matches.length > 8
                ? 'Menampilkan 8 dari ' + matches.length + ' part. Ketik untuk menyaring.'
                : matches.length + ' part tersedia.';
        }

        function addPart(part, quantity, focus) {
            if (selected.has(part.id)) return;

            const row = template.content.firstElementChild.cloneNode(true);
            const input = row.querySelector('input');
            const label = row.querySelector('[data-part-label]');
            const remove = row.querySelector('[data-remove-part]');
            input.id = 'addition-' + part.id;
            input.name = 'additions[' + part.id + ']';
            input.value = quantity;
            input.setAttribute('aria-label', 'Jumlah box untuk ' + part.part_number);
            label.htmlFor = input.id;
            label.textContent = part.part_number;
            row.querySelector('[data-part-capacity]').textContent = numberFormat.format(part.qty_box) + ' PCS/box · Baris baru';
            remove.setAttribute('aria-label', 'Hapus ' + part.part_number);
            remove.addEventListener('click', () => {
                selected.delete(part.id);
                row.remove();
                picker.open = true;
                renderResults();
                updateTotal();
                search.focus();
            });
            selected.add(part.id);
            rows.append(row);
            if (focus) {
                search.value = '';
                renderResults();
                updateTotal();
                input.focus();
                input.select();
            }
        }

        for (const part of parts) {
            if (Object.hasOwn(initial, part.id)) addPart(part, initial[part.id], false);
        }

        picker.addEventListener('toggle', () => { if (picker.open) renderResults(); });
        search.addEventListener('input', renderResults);
        search.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            if (results.childElementCount === 1) results.firstElementChild.click();
            else results.firstElementChild?.focus();
        });
    }

    updateTotal();
})();
