let sequence = 0;

export function matchingOptions(options, query) {
    const term = String(query).trim().toLocaleLowerCase('id-ID');
    return options.filter(option => !term || option.label.toLocaleLowerCase('id-ID').includes(term));
}

// Suggestions occupy normal document flow: opening a list never covers another field.
export function attachSuggestions(input, {options, onSelect, onEdit} = {}) {
    const doc = input.ownerDocument;
    const wrapper = doc.createElement('div'); wrapper.className = 'search-field';
    input.before(wrapper); wrapper.append(input);
    const list = doc.createElement('div');
    list.className = 'search-suggestions'; list.id = 'search-options-' + ++sequence;
    list.setAttribute('role', 'listbox'); list.hidden = true; wrapper.append(list);
    input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', list.id); input.setAttribute('aria-expanded', 'false');
    input.setAttribute('autocomplete', 'off');
    let matches = [], active = -1, selecting = false;
    const close = () => {
        list.hidden = true; input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant'); active = -1;
    };
    const highlight = index => {
        active = index;
        [...list.children].forEach((node, i) => {
            node.classList.toggle('is-active', i === index);
            node.setAttribute('aria-selected', String(i === index));
        });
        const selected = list.children[index];
        if (selected) {
            input.setAttribute('aria-activedescendant', selected.id);
            selected.scrollIntoView({block: 'nearest'});
        }
    };
    const choose = index => {
        const option = matches[index]; if (!option || option.disabled) return;
        selecting = true; input.value = option.label;
        onSelect?.(option);
        input.dispatchEvent(new Event('input', {bubbles: true}));
        selecting = false; close(); input.focus({preventScroll: true});
    };
    const render = () => {
        matches = matchingOptions(typeof options === 'function' ? options() : options, input.value);
        active = -1; list.replaceChildren(); input.removeAttribute('aria-activedescendant');
        if (!matches.length) {
            const empty = doc.createElement('p'); empty.className = 'search-no-results';
            empty.textContent = 'Tidak ada yang cocok.'; list.append(empty);
        }
        matches.forEach((option, i) => {
            const button = doc.createElement('button'); button.type = 'button'; button.tabIndex = -1;
            button.id = list.id + '-' + i; button.dataset.optionIndex = String(i);
            button.setAttribute('role', 'option'); button.setAttribute('aria-selected', 'false');
            button.setAttribute('aria-disabled', String(Boolean(option.disabled)));
            button.disabled = Boolean(option.disabled);
            const label = doc.createElement('strong'); label.textContent = option.label; button.append(label);
            if (option.detail) { const detail = doc.createElement('small'); detail.textContent = option.detail; button.append(detail); }
            list.append(button);
        });
        list.hidden = false; input.setAttribute('aria-expanded', 'true');
    };
    input.addEventListener('focus', render);
    input.addEventListener('input', () => { if (!selecting) { onEdit?.(input.value); render(); } });
    input.addEventListener('blur', event => { if (!wrapper.contains(event.relatedTarget)) close(); });
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); close(); return; }
        if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
        if (list.hidden) {
            if (event.key === 'Enter') return;
            render();
        }
        event.preventDefault();
        const enabled = matches.map((option, i) => option.disabled ? -1 : i).filter(i => i >= 0);
        if (!enabled.length) return;
        if (event.key === 'Enter') { choose(active < 0 ? enabled[0] : active); return; }
        const current = enabled.indexOf(active), direction = event.key === 'ArrowDown' ? 1 : -1;
        highlight(enabled[current < 0 ? (direction === 1 ? 0 : enabled.length - 1) : (current + direction + enabled.length) % enabled.length]);
    });
    list.addEventListener('pointerdown', event => event.preventDefault());
    list.addEventListener('click', event => {
        const option = event.target.closest('[data-option-index]');
        if (option) choose(Number(option.dataset.optionIndex));
    });
    return {close, refresh: () => { if (!list.hidden) render(); }};
}
