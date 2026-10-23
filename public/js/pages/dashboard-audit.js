(() => {
    const form = document.getElementById('audit-filter-form');
    const content = document.getElementById('audit-report-content');
    const feedback = document.getElementById('audit-feedback');
    const message = document.getElementById('audit-feedback-message');
    const retry = document.getElementById('audit-retry');
    if (!form || !content || !feedback || !message || !retry) return;

    let sequence = 0;
    let controller;
    let failedUrl;

    const announce = (text, error = false) => {
        message.textContent = text;
        feedback.classList.toggle('audit-feedback--error', error);
        retry.hidden = !error;
    };
    const busy = (value) => {
        content.setAttribute('aria-busy', String(value));
        form.setAttribute('aria-busy', String(value));
        const button = form.querySelector('[type="submit"]');
        if (button) button.textContent = value ? 'Memuat…' : 'Terapkan';
    };
    const load = async (url) => {
        const fromRetry = document.activeElement === retry;
        const request = ++sequence;
        controller?.abort();
        const current = new AbortController();
        controller = current;
        let timedOut = false;
        const timeout = setTimeout(() => { timedOut = true; current.abort(); }, 20000);
        failedUrl = null;
        busy(true);
        announce('Memuat laporan audit… Hasil sebelumnya tetap ditampilkan.');
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: current.signal,
            });
            if (!response.ok) throw new Error('response');
            const html = await response.text();
            if (request !== sequence) return;
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextContent = doc.getElementById('audit-report-content');
            const nextForm = doc.getElementById('audit-filter-form');
            if (!nextContent || !nextForm) throw new Error('markup');

            // Restore focus only if the element being replaced still owns it.
            const active = document.activeElement;
            const inForm = form.contains(active);
            const inContent = content.contains(active);
            const wasRetry = active === retry || (fromRetry && active === document.body);
            const activeId = active?.id;
            content.innerHTML = nextContent.innerHTML;
            form.innerHTML = nextForm.innerHTML;
            window.history.replaceState({}, '', url);
            announce('Laporan audit diperbarui.');
            if (inForm || wasRetry) {
                const target = (activeId && form.querySelector(`#${CSS.escape(activeId)}`))
                    || form.querySelector('[type="submit"]');
                target?.focus({ preventScroll: true });
            } else if (inContent) {
                content.focus({ preventScroll: true });
            }
        } catch (error) {
            if (request !== sequence) return;
            failedUrl = url;
            announce(timedOut
                ? 'Permintaan terlalu lama. Hasil sebelumnya tetap ditampilkan. Silakan coba lagi.'
                : 'Laporan audit gagal dimuat. Hasil dan pilihan filter sebelumnya tetap tersedia. Silakan coba lagi.', true);
        } finally {
            clearTimeout(timeout);
            if (request === sequence) busy(false);
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        // Keep the existing form action and GET parameter semantics.
        const url = new URL(form.action);
        new FormData(form).forEach((value, key) => {
            if (value !== '') url.searchParams.set(key, value);
            else url.searchParams.delete(key);
        });
        load(url.toString());
    });
    form.addEventListener('click', (event) => {
        const link = event.target.closest('#audit-filter-reset');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        load(link.href);
    });
    form.addEventListener('change', () => {
        ++sequence;
        controller?.abort();
        failedUrl = null;
        busy(false);
        announce('Pilihan filter berubah. Tekan Terapkan untuk memperbarui hasil.');
    });
    content.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link || !link.href || !new URL(link.href).searchParams.has('page')
            || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        load(link.href);
    });
    retry.addEventListener('click', () => { if (failedUrl) load(failedUrl); });
})();
