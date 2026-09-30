/**
 * Global confirm dialog: replaces native confirm() and per-page delete modals.
 * ---------------------------------------------------------------------------
 * Declarative (no JS needed in views):
 *
 *   <form method="POST" action="..." data-confirm="This permanently removes the record."
 *         data-confirm-title="Delete patient?" data-confirm-variant="danger"
 *         data-confirm-button="Delete">
 *
 *   <button type="submit" data-confirm="Send 24 SMS reminders now?">Send</button>   (inside a form)
 *   <a href="/export" data-confirm="Export all records?">Export</a>                  (navigates on OK)
 *   <button type="button" data-confirm="Clear chat?" data-confirm-action="/ai/clear"
 *           data-confirm-method="DELETE">Clear</button>                             (builds + submits a form)
 *
 * Attributes (on the <form>, the submit button, a link or a button):
 *   data-confirm               body message (required; may be empty string)
 *   data-confirm-title         heading                        default "Are you sure?"
 *   data-confirm-variant       danger | primary | warning | success | info
 *                              default: danger for DELETE forms, else primary
 *   data-confirm-button        confirm button label           default "Delete" (danger) / "Confirm"
 *   data-confirm-cancel        cancel button label            default "Cancel"
 *   data-confirm-icon          bootstrap-icon name override
 *   data-confirm-action / -method   (non-form triggers) URL + HTTP verb for a generated form
 *   data-confirm-input         field name → shows a textarea; its value is added to the
 *                              submitted form as a hidden input (e.g. cancelled_reason)
 *   data-confirm-input-label   textarea label
 *   data-confirm-input-required  "false" to make the textarea optional (default required)
 *
 * Programmatic:  const ok = await window.confirmDialog({ title, message, variant, confirmText });
 *                → false when cancelled, true (or the textarea text when `input` is set) when confirmed.
 *
 * The modal markup is rendered by <x-ui.confirm-dialog /> (id="confirmModal");
 * if a page does not include it, an identical modal is injected on first use.
 */
import { Modal } from 'bootstrap';

const MODAL_ID = 'confirmModal';

const VARIANTS = {
    danger:  { tone: 'danger',  icon: 'exclamation-triangle-fill', btn: 'btn-danger',  label: 'Delete' },
    primary: { tone: 'brand',   icon: 'question-circle-fill',      btn: 'btn-primary', label: 'Confirm' },
    warning: { tone: 'warning', icon: 'exclamation-circle-fill',   btn: 'btn-warning', label: 'Continue' },
    success: { tone: 'success', icon: 'check-circle-fill',         btn: 'btn-success', label: 'Confirm' },
    info:    { tone: 'info',    icon: 'info-circle-fill',          btn: 'btn-primary', label: 'Confirm' },
};

const TEMPLATE = `
<div class="modal fade modal-confirm" id="${MODAL_ID}" tabindex="-1" aria-hidden="true"
     aria-labelledby="${MODAL_ID}Title" aria-describedby="${MODAL_ID}Body" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="confirm-icon tone-brand" data-confirm-el="icon"><i class="bi bi-question-circle-fill c-icon" aria-hidden="true"></i></div>
      <h2 class="confirm-title" id="${MODAL_ID}Title" data-confirm-el="title">Are you sure?</h2>
      <p class="confirm-body" id="${MODAL_ID}Body" data-confirm-el="body"></p>
      <div class="mb-3 d-none" data-confirm-el="input-wrap">
        <label class="form-label" for="${MODAL_ID}Input" data-confirm-el="input-label">Reason</label>
        <textarea class="form-control" id="${MODAL_ID}Input" rows="3" maxlength="1000" data-confirm-el="input"></textarea>
        <div class="invalid-feedback">This field is required.</div>
      </div>
      <div class="confirm-actions">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-confirm-el="cancel">Cancel</button>
        <button type="button" class="btn btn-primary" data-confirm-el="ok">Confirm</button>
      </div>
    </div>
  </div>
</div>`;

let modalEl = null;
let pending = null; // { resolve, opts }

function ensureModal() {
    modalEl = document.getElementById(MODAL_ID);
    if (!modalEl) {
        const wrap = document.createElement('div');
        wrap.innerHTML = TEMPLATE.trim();
        modalEl = wrap.firstElementChild;
        document.body.appendChild(modalEl);
    }
    if (!modalEl.dataset.confirmBound) {
        modalEl.dataset.confirmBound = '1';
        part('ok').addEventListener('click', onOk);
        part('input').addEventListener('input', () => part('input').classList.remove('is-invalid'));
        modalEl.addEventListener('shown.bs.modal', () => {
            const o = pending?.opts;
            const target = o?.input ? part('input') : (o?.variant === 'danger' ? part('cancel') : part('ok'));
            target?.focus();
        });
        modalEl.addEventListener('hidden.bs.modal', () => settle(false));
    }
    return modalEl;
}

function part(name) {
    return modalEl.querySelector(`[data-confirm-el="${name}"]`);
}

function settle(value) {
    if (!pending) return;
    const { resolve } = pending;
    pending = null;
    resolve(value);
}

function onOk() {
    if (!pending) return;
    const { opts } = pending;
    let value = true;
    if (opts.input) {
        const field = part('input');
        value = field.value.trim();
        if (opts.inputRequired && value === '') {
            field.classList.add('is-invalid');
            field.focus();
            return;
        }
    }
    settle(value);
    Modal.getOrCreateInstance(modalEl).hide();
}

/**
 * @param {object} opts { title, message, variant, confirmText, cancelText, icon, input, inputLabel, inputRequired }
 * @returns {Promise<boolean|string>}
 */
export function confirmDialog(opts = {}) {
    ensureModal();
    settle(false); // a new request cancels any previous one

    const variant = VARIANTS[opts.variant] ? opts.variant : 'primary';
    const v = VARIANTS[variant];
    const o = {
        title: opts.title || 'Are you sure?',
        message: opts.message ?? '',
        variant,
        confirmText: opts.confirmText || v.label,
        cancelText: opts.cancelText || 'Cancel',
        icon: opts.icon || v.icon,
        input: opts.input || null,
        inputLabel: opts.inputLabel || 'Reason',
        inputRequired: opts.inputRequired !== false,
    };

    const icon = part('icon');
    icon.className = `confirm-icon tone-${v.tone}`;
    icon.innerHTML = `<i class="bi bi-${o.icon.replace(/^bi-/, '')} c-icon" aria-hidden="true"></i>`;
    part('title').textContent = o.title;
    const body = part('body');
    body.textContent = o.message;
    body.classList.toggle('d-none', !o.message);

    const inputWrap = part('input-wrap');
    const input = part('input');
    inputWrap.classList.toggle('d-none', !o.input);
    input.value = '';
    input.classList.remove('is-invalid');
    input.required = !!(o.input && o.inputRequired);
    part('input-label').textContent = o.inputLabel;

    const ok = part('ok');
    ok.className = `btn ${v.btn}`;
    ok.textContent = o.confirmText;
    part('cancel').textContent = o.cancelText;

    return new Promise((resolve) => {
        pending = { resolve, opts: o };
        Modal.getOrCreateInstance(modalEl).show();
    });
}

function optionsFrom(el, form = null) {
    const d = el.dataset;
    const methodInput = form?.querySelector('input[name="_method"]');
    const verb = (methodInput?.value || d.confirmMethod || '').toUpperCase();
    return {
        title: d.confirmTitle,
        message: d.confirm,
        variant: d.confirmVariant || (verb === 'DELETE' ? 'danger' : 'primary'),
        confirmText: d.confirmButton,
        cancelText: d.confirmCancel,
        icon: d.confirmIcon,
        input: d.confirmInput || null,
        inputLabel: d.confirmInputLabel,
        inputRequired: d.confirmInputRequired !== 'false',
    };
}

function setHidden(form, name, value) {
    let field = form.querySelector(`input[type="hidden"][name="${CSS.escape(name)}"][data-confirm-generated]`);
    if (!field) {
        field = document.createElement('input');
        field.type = 'hidden';
        field.name = name;
        field.setAttribute('data-confirm-generated', '');
        form.appendChild(field);
    }
    field.value = value;
}

function isSubmitControl(el) {
    return (el.tagName === 'BUTTON' && (el.type || 'submit') === 'submit')
        || (el.tagName === 'INPUT' && (el.type === 'submit' || el.type === 'image'));
}

function submitGenerated(action, method, extra = {}) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = action;
    form.style.display = 'none';
    const token = document.head.querySelector('meta[name="csrf-token"]')?.content;
    const fields = { _token: token, ...extra };
    if (method !== 'POST') fields._method = method;
    Object.entries(fields).forEach(([k, val]) => {
        if (val === undefined || val === null) return;
        const i = document.createElement('input');
        i.type = 'hidden';
        i.name = k;
        i.value = val;
        form.appendChild(i);
    });
    document.body.appendChild(form);
    form.requestSubmit ? form.requestSubmit() : form.submit();
}

// ── Forms: form[data-confirm] or a submit button with [data-confirm]
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    const submitter = e.submitter || null;
    const source = submitter?.hasAttribute('data-confirm') ? submitter
        : (form.hasAttribute('data-confirm') ? form : null);
    if (!source) return;

    if (form.dataset.confirmed === '1') {
        delete form.dataset.confirmed;
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();

    const opts = optionsFrom(source, form);
    confirmDialog(opts).then((result) => {
        if (result === false) return;
        if (opts.input && typeof result === 'string') setHidden(form, opts.input, result);
        form.dataset.confirmed = '1';
        try {
            form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
        } catch (err) {
            form.requestSubmit();
        }
    });
}, true);

// ── Links and plain buttons with [data-confirm]
document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-confirm]');
    if (!el || el.tagName === 'FORM') return;
    if (isSubmitControl(el) && el.form) return; // handled by the submit listener
    if (el.dataset.confirmed === '1') {
        delete el.dataset.confirmed;
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();

    const opts = optionsFrom(el);
    confirmDialog(opts).then((result) => {
        if (result === false) return;
        const method = (el.dataset.confirmMethod || '').toUpperCase();
        const action = el.dataset.confirmAction || (el.tagName === 'A' ? el.getAttribute('href') : null);
        const extra = opts.input && typeof result === 'string' ? { [opts.input]: result } : {};

        if (action && method && method !== 'GET') {
            submitGenerated(action, method, extra);
        } else if (el.tagName === 'A' && action) {
            if (el.target && el.target !== '_self') window.open(el.href, el.target);
            else window.location.assign(el.href);
        } else if (action) {
            window.location.assign(action);
        } else {
            el.dataset.confirmed = '1';
            el.click();
        }
    });
}, true);

window.confirmDialog = confirmDialog;
