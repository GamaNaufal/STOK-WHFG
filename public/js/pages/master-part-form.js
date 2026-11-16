import {enhanceDisclosure} from '../ui-motion.js';

(() => {
    const same = document.getElementById('sameDocumentNumber');
    const documents = document.getElementById('documentNumbersInput');
    if (!same || !documents) return;
    const details = document.getElementById('documentNumberDetails');
    const summary = details?.querySelector('[data-document-summary]');
    const disclosure = details ? enhanceDisclosure(details) : null;
    const refresh = () => {
        documents.readOnly = same.checked;
        documents.required = !same.checked;
        documents.setAttribute('aria-disabled', String(same.checked));
        if (summary) summary.textContent = same.checked ? 'Sama dengan label / scan' : 'Kode berbeda, periksa pemetaan';
        if (!same.checked || documents.getAttribute('aria-invalid') === 'true') disclosure?.setOpen(true);
    };
    // Enhance the common path only; without JS, all fields remain available.
    if (details && same.checked && documents.getAttribute('aria-invalid') !== 'true'
        && !same.matches('[aria-invalid="true"]')) disclosure.setOpen(false);
    documents.form?.addEventListener('invalid', event => {
        if (event.target.closest('details') === details) disclosure?.setOpen(true);
    }, true);
    same.addEventListener('change', () => {
        refresh();
        if (!same.checked) documents.focus();
    });
    window.addEventListener('pageshow', refresh);
    refresh();
})();
