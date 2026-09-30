/**
 * Toasts: window.toast()
 * ---------------------------------------------------------------------------
 *   toast('Patient saved.')                              // success (default)
 *   toast('Could not send SMS.', 'error')                // success|error|danger|warning|info
 *   toast('Stock is low', 'warning', { title: 'Heads up', delay: 6000 })
 *   toast({ type: 'info', title: 'Synced', message: '12 records updated' })
 *
 * Error toasts stay until closed (autohide: false) unless a delay is given.
 * Server flashes are rendered by <x-ui.flash-toasts /> (markup inside a
 * .c-toasts container with [data-toast]) and shown on page load.
 * The container is created on demand if the page does not render one.
 */
import { Toast } from 'bootstrap';

const ICONS = {
    success: 'check-circle-fill',
    error: 'exclamation-octagon-fill',
    danger: 'exclamation-octagon-fill',
    warning: 'exclamation-triangle-fill',
    info: 'info-circle-fill',
};
const DEFAULT_DELAY = 4000;

function container() {
    let el = document.getElementById('toastStack') || document.querySelector('.c-toasts');
    if (!el) {
        el = document.createElement('div');
        el.id = 'toastStack';
        el.className = 'toast-container c-toasts';
        el.setAttribute('aria-live', 'polite');
        el.setAttribute('aria-atomic', 'false');
        document.body.appendChild(el);
    }
    return el;
}

function build({ type, title, message, autohide, delay }) {
    const el = document.createElement('div');
    el.className = `toast fade toast-${type}`;
    el.setAttribute('role', type === 'error' || type === 'danger' ? 'alert' : 'status');
    el.setAttribute('aria-atomic', 'true');
    el.style.setProperty('--toast-delay', `${delay}ms`);

    const icon = document.createElement('i');
    icon.className = `bi bi-${ICONS[type] || ICONS.info} c-icon toast-icon`;
    icon.setAttribute('aria-hidden', 'true');

    const text = document.createElement('div');
    text.className = 'toast-text';
    const t = document.createElement('p');
    t.className = 'toast-title';
    t.textContent = title || message;
    text.appendChild(t);
    if (title && message) {
        const m = document.createElement('p');
        m.className = 'toast-message';
        m.textContent = message;
        text.appendChild(m);
    }

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close-c btn-close-sm';
    close.setAttribute('data-bs-dismiss', 'toast');
    close.setAttribute('aria-label', 'Close');
    close.innerHTML = '<i class="bi bi-x-lg c-icon" aria-hidden="true"></i>';

    el.append(icon, text, close);
    if (autohide) {
        const bar = document.createElement('span');
        bar.className = 'toast-progress';
        el.appendChild(bar);
    }
    return el;
}

function show(el, { autohide, delay }) {
    const instance = Toast.getOrCreateInstance(el, { autohide, delay });
    el.addEventListener('hidden.bs.toast', () => {
        instance.dispose();
        el.remove();
    }, { once: true });
    instance.show();
    return instance;
}

/**
 * @param {string|object} message  text, or { type, title, message, delay, autohide }
 * @param {string} [type]          success | error | danger | warning | info
 * @param {object} [options]       { title, delay, autohide }
 * @returns {HTMLElement} the toast element
 */
export function toast(message, type = 'success', options = {}) {
    let opts = typeof message === 'object' && message !== null
        ? { ...message }
        : { ...options, message: String(message ?? ''), type };
    opts.type = (opts.type || 'success').toLowerCase();
    if (!ICONS[opts.type]) opts.type = 'info';
    const isError = opts.type === 'error' || opts.type === 'danger';
    opts.autohide = opts.autohide ?? (opts.delay ? true : !isError);
    opts.delay = Number(opts.delay) || DEFAULT_DELAY;

    const el = build(opts);
    container().appendChild(el);
    show(el, opts);
    return el;
}

/** Show server-rendered toasts (x-ui.flash-toasts). */
export function initServerToasts(root = document) {
    root.querySelectorAll('.c-toasts .toast[data-toast]:not([data-toast-ready])').forEach((el) => {
        el.setAttribute('data-toast-ready', '1');
        const autohide = el.dataset.bsAutohide !== 'false';
        const delay = Number(el.dataset.bsDelay) || DEFAULT_DELAY;
        el.style.setProperty('--toast-delay', `${delay}ms`);
        show(el, { autohide, delay });
    });
}

window.toast = toast;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initServerToasts());
} else {
    initServerToasts();
}
