// These hints keep unavailable actions out of the UI. The server enforces access.
const compiledPolicies = new WeakMap();
export function routeRule(policy, href, method = 'GET', origin = globalThis.location?.origin) {
    let url;
    try { url = new URL(href, origin); } catch { return null; }
    if (url.origin !== origin) return null;
    const root = (policy.root || '').replace(/\/$/, '');
    if (root && !url.pathname.startsWith(root + '/')) return null;
    const path = decodeURI(url.pathname.slice(root.length)).replace(/^\/+|\/+$/g, '');
    if (!compiledPolicies.has(policy)) compiledPolicies.set(policy, policy.rules.map(rule => {
        const pattern = rule.path.replace(/^\/+|\/+$/g, '').split('/').map(segment => /^\{[^}]+\}$/.test(segment)
            ? '(?:' + (rule.where?.[segment.slice(1, -1).replace(/\?$/, '')] || (segment.endsWith('?}') ? '[^/]*' : '[^/]+')) + ')'
            : segment.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('/');
        return {rule, match: new RegExp('^' + pattern + '/?$')};
    }));
    const found = compiledPolicies.get(policy).find(({rule, match}) => rule.methods.includes(method.toUpperCase()) && match.test(path))?.rule;
    return found ? {...found, queryValue: found.field && url.searchParams.get(found.field)} : null;
}

export function permits(rule, values) {
    if (!rule) return true;
    const value = rule.field && (values?.get(rule.field) || rule.queryValue);
    return value && Object.hasOwn(rule.choices || {}, value) ? rule.choices[value] : rule.allowed;
}

export function installActionPermissions(doc, policy, origin) {
    const denied = element => { element.hidden = true; element.dataset.accessDenied = 'true';
        if ('disabled' in element) element.disabled = true; };
    const allowed = element => {
        const key = element.dataset.permission;
        if (key && !key.split(/\s+/).every(k => policy.features[k] === true)) return false;
        const custom = [['deleteUrl', 'DELETE'], ['splitUrl', 'POST'], ['previewUrl', 'GET'], ['readyUrl', 'GET']];
        for (const [attribute, method] of custom) {
            if (element.dataset[attribute] && !permits(routeRule(policy, element.dataset[attribute], method, origin))) return false;
        }
        if (element.matches('a[href]')) return permits(routeRule(policy, element.href, 'GET', origin));
        if (element.matches('form')) {
            const values = new FormData(element);
            const method = values.get('_method') || element.method || 'GET';
            const rule = routeRule(policy, element.action, method, origin);
            // Shared forms may submit different actions through their buttons.
            const buttons = rule?.field && [...element.querySelectorAll('button[name],input[type=submit][name]')]
                .some(button => button.name === rule.field);
            return buttons ? rule.allowed : permits(rule, values);
        }
        if (element.form && element.name) {
            const values = new FormData(element.form);
            values.set(element.name, element.value);
            return permits(routeRule(policy, element.form.action, values.get('_method') || element.form.method || 'GET', origin), values);
        }
        return true;
    };
    const selector = '[data-permission],a[href],form,button[name],input[type=submit][name],[data-delete-url],[data-split-url],[data-preview-url],[data-ready-url]';
    const scan = node => {
        if (node.nodeType !== 1 && node.nodeType !== 9) return;
        if (node.matches?.(selector) && !allowed(node)) denied(node);
        node.querySelectorAll(selector).forEach(element => { if (!allowed(element)) denied(element); });
    };
    scan(doc);
    const observer = new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(scan)));
    observer.observe(doc.documentElement, { childList: true, subtree: true });
    doc.addEventListener('click', event => {
        const control = event.target.closest(selector);
        if (control && (control.dataset.accessDenied || !allowed(control))) {
            event.preventDefault(); event.stopImmediatePropagation();
        }
    }, true);
    doc.addEventListener('submit', event => {
        const form = event.target;
        const values = new FormData(form);
        if (event.submitter?.name) values.set(event.submitter.name, event.submitter.value);
        const rule = routeRule(policy, form.action, values.get('_method') || form.method || 'GET', origin);
        if (!allowed(form) || !permits(rule, values)) {
            event.preventDefault(); event.stopImmediatePropagation();
        }
    }, true);
    return observer;
}

if (typeof document !== 'undefined') {
    const start = () => {
        const data = document.getElementById('action-permissions');
        if (data) installActionPermissions(document, JSON.parse(data.textContent), location.origin);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
}
