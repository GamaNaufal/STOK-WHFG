export const excelCookieName = token => `whfg_excel_${token}`;

export function readExcelCookie(cookies, name) {
    return String(cookies).split(';').map(part => part.trim()).find(part => part.startsWith(name + '='))?.slice(name.length + 1);
}

export function createExcelRepeatGuard({now = () => Date.now(), pauseMs = 1000} = {}) {
    const recent = new Map();
    return {
        hold(key) {
            const time = now();
            for (const [address, until] of recent) if (until <= time) recent.delete(address);
            recent.set(key, time + pauseMs);
        },
        blocked(key) {
            if ((recent.get(key) ?? 0) > now()) return true;
            recent.delete(key); return false;
        },
        clear() { recent.clear(); },
    };
}

export function excelFormUrl(form, submitter, base, entries) {
    const url = new URL(submitter?.getAttribute('formaction') || form.action, base);
    url.search = ''; url.hash = '';
    for (const [key, value] of entries) if (typeof value === 'string') url.searchParams.append(key, value);
    if (submitter?.name) url.searchParams.append(submitter.name, submitter.value);
    return url;
}

export function createExcelTracker({token, readCookie, clearCookie, changed, finished,
    setTimer = (callback, delay) => globalThis.setTimeout(callback, delay),
    clearTimer = id => globalThis.clearTimeout(id),
    setPoll = (callback, delay) => globalThis.setInterval(callback, delay),
    clearPoll = id => globalThis.clearInterval(id)}) {
    let done = false;
    const name = excelCookieName(token);
    const finish = (phase, message) => {
        if (done) return;
        done = true; clearPoll(poll); clearTimer(slow); clearTimer(waiting);
        clearCookie(name); changed(phase, message); finished(phase);
    };
    const check = () => {
        if (!done && readCookie(name) === 'ready') finish('ready');
        return done;
    };
    const poll = setPoll(check, 250);
    const slow = setTimer(() => { if (!done) changed('slow'); }, 1800);
    const waiting = setTimer(() => { if (!check()) finish('unconfirmed'); }, 60000);
    changed('preparing');
    return {check, fail: message => finish('error', message), cancel: () => finish('cancelled')};
}

export function initExcelDownloads({documentRef = globalThis.document, windowRef = globalThis.window} = {}) {
    const container = documentRef?.getElementById('toast-container');
    if (!container || !windowRef.bootstrap?.Toast || !windowRef.crypto?.getRandomValues) return;
    const jobs = new Map(), frames = new Set();
    const repeatGuard = createExcelRepeatGuard();
    const eligible = control => !control.disabled && control.getAttribute('aria-disabled') !== 'true'
        && !control.classList.contains('disabled') && !control.closest('[data-access-denied="true"]');

    const begin = (address, control) => {
        const url = new URL(address, windowRef.location.href);
        if (url.origin !== windowRef.location.origin || !['http:', 'https:'].includes(url.protocol)) return false;
        url.hash = ''; url.searchParams.delete('_excel_token');
        const key = url.href;
        if (jobs.has(key)) { jobs.get(key).toast.show(); return true; }
        if (repeatGuard.blocked(key)) return true;

        const token = [...windowRef.crypto.getRandomValues(new Uint8Array(16))].map(value => value.toString(16).padStart(2, '0')).join('');
        const panel = documentRef.createElement('div');
        panel.className = 'toast excel-download-feedback';
        panel.setAttribute('role', 'status'); panel.setAttribute('aria-live', 'polite'); panel.setAttribute('aria-atomic', 'true');
        panel.innerHTML = '<div class="toast-body"><div class="excel-download-heading"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i><strong data-excel-title></strong><span class="excel-download-spinner" aria-hidden="true"></span></div><p data-excel-message></p><div class="excel-download-actions"><button type="button" class="btn btn-sm btn-outline-primary" data-excel-retry hidden>Coba lagi</button><a class="btn btn-sm btn-outline-primary" data-excel-direct hidden>Unduh langsung</a><button type="button" class="btn btn-sm btn-outline-secondary" data-excel-close>Tutup</button></div></div>';
        const title = panel.querySelector('[data-excel-title]'), message = panel.querySelector('[data-excel-message]');
        const retry = panel.querySelector('[data-excel-retry]'), direct = panel.querySelector('[data-excel-direct]');
        const close = panel.querySelector('[data-excel-close]');
        const frame = documentRef.createElement('iframe');
        frame.hidden = true; frame.title = 'Unduhan Excel'; frame.setAttribute('aria-hidden', 'true');
        const originalBusy = control.getAttribute('aria-busy');
        control.setAttribute('aria-busy', 'true'); control.setAttribute('data-excel-busy', 'true');
        direct.href = key;
        container.appendChild(panel);
        const toast = new windowRef.bootstrap.Toast(panel, {autohide: false});
        const job = {toast, message, pending: true, dismissTimer: null};
        jobs.set(key, job);
        let removed = false;
        const removePanel = () => {
            if (removed) return;
            removed = true; windowRef.clearTimeout(job.dismissTimer);
            if (panel.contains(documentRef.activeElement) && control.isConnected) control.focus({preventScroll: true});
            toast.dispose(); panel.remove();
        };
        const changed = (phase, error) => {
            panel.setAttribute('data-excel-state', phase);
            title.textContent = phase === 'ready' ? 'Excel siap' : phase === 'unconfirmed' ? 'Belum terkonfirmasi'
                : phase === 'error' ? 'Unduhan belum dimulai' : 'Menyiapkan Excel…';
            message.textContent = phase === 'ready' ? 'File diserahkan ke browser. Periksa daftar unduhan browser.'
                : phase === 'error' ? error || 'File belum dapat disiapkan. Coba lagi atau gunakan unduhan langsung.'
                    : phase === 'unconfirmed' ? 'Status file belum dapat dipastikan. Periksa daftar unduhan browser sebelum memakai Unduh langsung.'
                        : phase === 'slow' ? 'Masih menyiapkan Excel. Unduhan akan dimulai setelah file siap.'
                            : 'Permintaan diterima. File mengikuti filter saat tombol ditekan.';
            retry.hidden = phase !== 'error'; direct.hidden = !['error', 'unconfirmed'].includes(phase);
            if (phase === 'ready' && documentRef.activeElement === close && control.isConnected) control.focus({preventScroll: true});
            close.hidden = phase === 'ready';
        };
        const tracker = createExcelTracker({token,
            readCookie: name => { try { return readExcelCookie(documentRef.cookie, name); } catch { return undefined; } },
            clearCookie: name => { try { documentRef.cookie = `${name}=; Max-Age=0; Path=/; SameSite=Lax`; } catch {} }, changed,
            finished: phase => {
                job.pending = false; jobs.delete(key);
                if (phase === 'ready') repeatGuard.hold(key);
                control.removeAttribute('data-excel-busy');
                if (originalBusy === null) control.removeAttribute('aria-busy'); else control.setAttribute('aria-busy', originalBusy);
                if (!['ready', 'unconfirmed'].includes(phase)) { frames.delete(frame); frame.remove(); }
                if (phase === 'cancelled') removePanel();
                else if (!panel.classList.contains('show')) removePanel();
                else if (phase === 'ready') job.dismissTimer = windowRef.setTimeout(() => toast.hide(), 3000);
            }});
        job.tracker = tracker;
        close.addEventListener('click', () => toast.hide());
        panel.addEventListener('hidden.bs.toast', () => { if (!job.pending) removePanel(); });
        retry.addEventListener('click', () => {
            removePanel();
            if (begin(key, control)) jobs.get(key)?.message.focus({preventScroll: true});
        });
        message.setAttribute('tabindex', '-1');
        frame.addEventListener('load', () => {
            if (!job.pending || tracker.check()) return;
            try {
                if (frame.contentWindow.location.href === 'about:blank') return;
                if (new URL(frame.contentWindow.location.href).pathname.endsWith('/login')) {
                    tracker.fail('Sesi berakhir. Masuk kembali lewat unduhan langsung, lalu ulangi.'); return;
                }
                const text = frame.contentDocument?.body?.textContent?.trim() || '';
                let detail;
                if (text.startsWith('{') && text.length < 16000) {
                    try { const data = JSON.parse(text); detail = Object.values(data.errors || {}).flat().find(value => typeof value === 'string') || (typeof data.message === 'string' ? data.message : undefined); } catch {}
                }
                tracker.fail(detail || 'File belum dapat disiapkan. Coba lagi atau gunakan unduhan langsung.');
            } catch { tracker.fail('Status unduhan tidak dapat dibaca. Gunakan unduhan langsung.'); }
        });
        url.searchParams.set('_excel_token', token);
        frame.src = url.href; frames.add(frame); documentRef.body.appendChild(frame);
        toast.show();
        return true;
    };

    const click = event => {
        if (event.defaultPrevented || event.button > 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const control = event.target.closest('a[data-report-export], a[data-excel-download], button[data-stock-export], button[data-excel-download]');
        if (!control || (control.target && control.target !== '_self') || control.formTarget || control.form?.target) return;
        if (!eligible(control) || event.detail > 1) { event.preventDefault(); return; }
        if (control.matches('a[href]') && begin(control.href, control)) event.preventDefault();
    };
    const submit = event => {
        const button = event.submitter, form = event.target;
        if (event.defaultPrevented || !button?.matches('[data-stock-export], [data-excel-download]')) return;
        if (!eligible(button)) { event.preventDefault(); return; }
        if ((button.getAttribute('formmethod') || form.method).toLowerCase() !== 'get' || (button.formTarget || form.target)) return;
        const url = excelFormUrl(form, button, windowRef.location.href, new FormData(form));
        if (begin(url.href, button)) event.preventDefault();
    };
    documentRef.addEventListener('click', click);
    documentRef.addEventListener('submit', submit);
    windowRef.addEventListener('pagehide', () => {
        for (const job of [...jobs.values()]) job.tracker.cancel();
        for (const frame of frames) frame.remove();
        frames.clear();
        repeatGuard.clear();
    });
    return {begin};
}

if (typeof document !== 'undefined') initExcelDownloads();
