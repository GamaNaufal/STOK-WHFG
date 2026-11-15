(() => {
    const same = document.getElementById('sameDocumentNumber');
    const documents = document.getElementById('documentNumbersInput');
    if (!same || !documents) return;
    const details = document.getElementById('documentNumberDetails');
    const summary = details?.querySelector('[data-document-summary]');
    const refresh = () => {
        documents.readOnly = same.checked;
        documents.required = !same.checked;
        documents.setAttribute('aria-disabled', String(same.checked));
        if (summary) summary.textContent = same.checked ? 'Sama dengan label / scan' : 'Kode berbeda, periksa pemetaan';
        if (details && (!same.checked || documents.getAttribute('aria-invalid') === 'true')) details.open = true;
    };
    // Enhance the common path only; without JS, all fields remain available.
    if (details && same.checked && documents.getAttribute('aria-invalid') !== 'true'
        && !same.matches('[aria-invalid="true"]')) details.open = false;
    documents.form?.addEventListener('invalid', event => {
        const disclosure = event.target.closest('details');
        if (disclosure) disclosure.open = true;
    }, true);
    same.addEventListener('change', () => {
        refresh();
        if (!same.checked) documents.focus();
    });
    window.addEventListener('pageshow', refresh);
    refresh();
})();
