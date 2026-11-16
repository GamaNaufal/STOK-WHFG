// Small, interruptible presentation effects. Callers keep business state immediate.
export const UI_EASE_OUT = 'cubic-bezier(0.23, 1, 0.32, 1)';
const easingFor = element => element.ownerDocument.defaultView.getComputedStyle(element)
    .getPropertyValue?.('--ui-ease-out')?.trim() || UI_EASE_OUT;

export function createSurfaceMotion(element, {enter = 180, exit = 120, offset = 'translateY(4px)',
    windowRef = element.ownerDocument.defaultView} = {}) {
    const reduce = windowRef.matchMedia?.('(prefers-reduced-motion: reduce)');
    let active = null, target = !element.hidden;
    const settle = open => {
        element.hidden = !open;
        element.inert = !open;
        element.setAttribute('aria-hidden', String(!open));
    };
    const cancel = (completed = false) => {
        if (!active) return;
        const previous = active; active = null;
        previous.animation.cancel(); previous.resolve(completed);
    };
    const setVisible = (open, {animate = false} = {}) => {
        const hidden = element.hidden;
        const current = active ? windowRef.getComputedStyle(element) : null;
        const from = current ? {opacity: current.opacity, transform: current.transform}
            : {opacity: hidden ? 0 : 1, transform: hidden ? offset : 'none'};
        cancel(); target = open;
        if (!animate || reduce?.matches || !element.animate || (hidden && !open)) {
            settle(open); return Promise.resolve(true);
        }
        element.hidden = false;
        element.inert = !open;
        element.setAttribute('aria-hidden', String(!open));
        const animation = element.animate([from, {opacity: open ? 1 : 0, transform: open ? 'none' : offset}], {
            duration: open ? enter : exit, easing: easingFor(element), fill: 'both',
        });
        return new Promise(resolve => {
            const request = {animation, resolve}; active = request;
            const finish = () => {
                if (active !== request) return;
                active = null; settle(open); animation.cancel(); resolve(true);
            };
            animation.finished.then(finish, finish);
        });
    };
    const finishImmediately = () => { cancel(true); settle(target); };
    // Typing/focus and a changed OS preference never wait for decorative movement.
    element.addEventListener('focusin', finishImmediately);
    element.ownerDocument.addEventListener('keydown', finishImmediately, true);
    reduce?.addEventListener?.('change', finishImmediately);
    windowRef.addEventListener('pagehide', finishImmediately);
    return {setVisible, finishImmediately};
}

export function enhanceDisclosure(details) {
    const summary = details.querySelector('summary');
    const body = details.querySelector('.ui-disclosure-body');
    const motion = createSurfaceMotion(body, {enter: 160, offset: 'none'});
    let desired = details.open, revision = 0;
    const setOpen = (open, {animate = false} = {}) => {
        desired = open;
        const request = ++revision;
        details.dataset.uiMotion = String(animate);
        // Establish the indicator's current pose before changing its target.
        if (animate) details.ownerDocument.defaultView.getComputedStyle(summary, '::after').transform;
        details.dataset.uiOpen = String(open);
        if (open) details.open = true;
        return motion.setVisible(open, {animate}).then(completed => {
            if (completed && request === revision && !desired) details.open = false;
        });
    };
    summary.addEventListener('click', event => {
        if (event.defaultPrevented) return;
        event.preventDefault();
        setOpen(!desired, {animate: event.detail > 0});
    });
    details.ownerDocument.addEventListener('keydown', () => {
        // Also resolve a pointer-initiated close before keyboard focus moves.
        if (details.dataset.uiMotion === 'true') setOpen(desired);
    }, true);
    setOpen(desired);
    return {setOpen};
}

const cues = new WeakMap();
export function animateCue(element, {animate = false, duration = 180, scale = .92} = {}) {
    const view = element?.ownerDocument.defaultView;
    if (!view) return;
    const previous = cues.get(element);
    const current = previous ? view.getComputedStyle(element) : null;
    const from = current ? {opacity: current.opacity, transform: current.transform}
        : {opacity: 0, transform: `scale(${scale})`};
    previous?.cancel(); cues.delete(element);
    if (!animate || view.matchMedia?.('(prefers-reduced-motion: reduce)').matches || !element.animate) return;
    const animation = element.animate([from, {opacity: 1, transform: 'none'}], {
        duration, easing: easingFor(element), fill: 'both',
    });
    cues.set(element, animation);
    const finish = () => {
        if (cues.get(element) !== animation) return;
        cues.delete(element); animation.cancel();
    };
    const reduce = view.matchMedia?.('(prefers-reduced-motion: reduce)');
    const stop = () => { finish(); cleanup(); };
    const cleanup = () => {
        element.ownerDocument.removeEventListener('keydown', stop, true);
        reduce?.removeEventListener?.('change', stop);
        view.removeEventListener('pagehide', stop);
    };
    element.ownerDocument.addEventListener('keydown', stop, true);
    reduce?.addEventListener?.('change', stop);
    view.addEventListener('pagehide', stop);
    animation.finished.then(() => { finish(); cleanup(); }, cleanup);
}

export function initAccountMenuMotion(documentRef = document) {
    const trigger = documentRef.querySelector('.avatar-trigger');
    const menu = documentRef.querySelector('.navigation-account-menu');
    if (!trigger || !menu) return;
    let activation = null;
    // Bootstrap delegates dropdown clicks in document capture; window must observe first.
    documentRef.defaultView.addEventListener('click', event => {
        activation = event;
    }, true);
    documentRef.defaultView.addEventListener('keydown', () => {
        activation = null; menu.dataset.uiMotion = 'false';
    }, true);
    for (const name of ['show.bs.dropdown', 'hide.bs.dropdown']) trigger.addEventListener(name, () => {
        // Bootstrap dispatches inside the click. A later API call has eventPhase 0.
        menu.dataset.uiMotion = String(Boolean(activation?.detail > 0 && activation.eventPhase !== 0));
    });
    trigger.addEventListener('shown.bs.dropdown', () => { menu.inert = false; });
    trigger.addEventListener('hidden.bs.dropdown', () => { menu.inert = true; });
    documentRef.defaultView.addEventListener('pagehide', () => { menu.dataset.uiMotion = 'false'; });
}
