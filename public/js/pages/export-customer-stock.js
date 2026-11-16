(() => {
    const form = document.querySelector('[data-customer-reservations]');
    if (!form) return;
    const boxes = [...form.querySelectorAll('input[name^="boxes["]:not(:disabled)')];
    const all = form.querySelector('[data-select-all]');
    const editor = form.querySelector('.customer-reservation-editor');
    const actions = form.querySelector('.customer-reservation-actions');
    let editing = null;
    const close = () => {
        if (!editor) return;
        editing = null; editor.hidden = true;
        editor.querySelector('[name=reason]').required = false;
    };
    if (editor && actions) {
        form.querySelector('.customer-stock-selection').insertAdjacentElement('afterend', editor);
        close(); actions.hidden = false;
        const cancel = document.createElement('button');
        cancel.type = 'button'; cancel.className = 'btn btn-outline-secondary'; cancel.textContent = 'Tutup';
        cancel.addEventListener('click', () => { close(); actions.querySelector('button:not(:disabled)')?.focus(); });
        editor.querySelector('.plan-inline-actions').append(cancel);
        actions.querySelectorAll('[data-open-reservation]').forEach(button => button.addEventListener('click', () => {
            editing = button.dataset.openReservation;
            editor.hidden = false;
            editor.querySelector('h2').textContent = editing === 'assign' ? 'Tetapkan customer untuk box terpilih' : 'Lepas reservasi menjadi stok umum';
            const target = editor.querySelector('[name=customer_id]');
            target.closest('.col-md-5').hidden = editing === 'release'; target.disabled = editing === 'release';
            editor.querySelector('[name=reason]').required = true;
            editor.querySelectorAll('[data-reservation-action]').forEach(action => { action.hidden = action.dataset.reservationAction !== editing; });
            editor.querySelector('[name=reason]').focus();
        }));
    }
    const update = () => {
        const selected = boxes.filter(box => box.checked);
        form.querySelector('[data-selected-count]').textContent = String(selected.length);
        const assign = form.querySelector('[data-reservation-action="assign"]');
        const release = form.querySelector('[data-reservation-action="release"]');
        if (assign) assign.disabled = !selected.length || selected.some(box => box.dataset.boxOwner !== '');
        if (release) release.disabled = !selected.length || selected.some(box => box.dataset.boxOwner === '');
        if (actions) actions.querySelectorAll('[data-open-reservation]').forEach(button => {
            button.disabled = button.dataset.openReservation === 'assign' ? assign.disabled : release.disabled;
        });
        if (editing && (editing === 'assign' ? assign.disabled : release.disabled)) close();
        all.checked = !!boxes.length && selected.length === boxes.length;
        all.indeterminate = selected.length > 0 && !all.checked;
    };
    boxes.forEach(box => box.addEventListener('change', update));
    all.addEventListener('change', () => { boxes.forEach(box => { box.checked = all.checked; }); update(); });
    update();
})();
