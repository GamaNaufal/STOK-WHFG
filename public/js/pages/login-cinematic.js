/** Decorative video is progressively enhanced; authentication never depends on it. */
export function initLoginCinema({documentRef = globalThis.document, windowRef = globalThis.window} = {}) {
    const cinema = documentRef?.querySelector('[data-login-cinema]');
    const video = cinema?.querySelector('[data-login-video]');
    const toggle = cinema?.querySelector('[data-login-motion]');
    if (!video || !toggle || !windowRef?.matchMedia) return;

    const label = toggle.querySelector('[data-login-motion-label]');
    const icon = toggle.querySelector('[data-login-motion-icon]');
    const desktop = windowRef.matchMedia('(min-width: 1024px)');
    const reduced = windowRef.matchMedia('(prefers-reduced-motion: reduce)');
    const connection = windowRef.navigator?.connection;
    let userPaused = false;
    let blocked = false;
    let failed = false;
    let attempt = 0;

    const eligible = () => desktop.matches && !reduced.matches && !connection?.saveData
        && !['slow-2g', '2g'].includes(connection?.effectiveType);

    const updateToggle = () => {
        const paused = video.paused;
        toggle.hidden = !eligible() || failed || (!video.getAttribute('src') && !userPaused);
        const text = paused ? 'Putar animasi' : 'Jeda animasi';
        toggle.setAttribute('aria-label', paused ? 'Putar animasi gudang' : 'Jeda animasi gudang');
        if (label) label.textContent = text;
        icon?.classList.toggle('bi-play-fill', paused);
        icon?.classList.toggle('bi-pause-fill', !paused);
    };

    const play = async () => {
        const token = ++attempt;
        try {
            await video.play();
            if (token !== attempt || !eligible() || documentRef.hidden || userPaused) {
                if (!eligible() || documentRef.hidden || userPaused) video.pause();
                return;
            }
            blocked = false;
        } catch {
            if (token !== attempt) return;
            blocked = true;
            cinema.classList.remove('has-video-frame');
        }
        updateToggle();
    };

    const sync = () => {
        if (!eligible()) {
            ++attempt;
            video.pause();
            if (video.hasAttribute('src')) {
                video.removeAttribute('src');
                video.load();
            }
            cinema.classList.remove('has-video-frame');
            updateToggle();
            return;
        }
        if (documentRef.hidden || userPaused || failed) {
            ++attempt;
            video.pause();
            updateToggle();
            return;
        }
        if (!video.hasAttribute('src')) {
            video.muted = true;
            video.setAttribute('src', video.dataset.src);
            video.load();
        }
        if (!blocked) void play();
        updateToggle();
    };

    toggle.addEventListener('click', () => {
        if (!eligible() || failed) return;
        if (!video.paused) {
            userPaused = true;
            ++attempt;
            video.pause();
            updateToggle();
        } else {
            userPaused = false;
            blocked = false;
            sync();
        }
    });
    video.addEventListener('playing', () => {
        if (!eligible() || documentRef.hidden || userPaused) {
            video.pause();
            return;
        }
        cinema.classList.add('has-video-frame');
        updateToggle();
    });
    video.addEventListener('pause', updateToggle);
    video.addEventListener('error', () => {
        if (!video.hasAttribute('src')) return;
        ++attempt;
        failed = true;
        video.pause();
        cinema.classList.remove('has-video-frame');
        updateToggle();
    });
    for (const query of [desktop, reduced]) {
        if (query.addEventListener) query.addEventListener('change', sync);
        else query.addListener?.(sync);
    }
    connection?.addEventListener?.('change', sync);
    documentRef.addEventListener('visibilitychange', sync);
    windowRef.addEventListener('pagehide', () => { ++attempt; video.pause(); });
    windowRef.addEventListener('pageshow', sync);
    sync();
    return {sync};
}

if (typeof document !== 'undefined') initLoginCinema();
