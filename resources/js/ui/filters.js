/**
 * Filter toolbars (x-ui.filters)
 * ---------------------------------------------------------------------------
 * form[data-autosubmit]  → changing a <select>, date input or checkbox submits the
 *                          GET form. Text/search inputs submit on Enter (native).
 *                          data-autosubmit="debounce" also submits 250ms after typing (max debounce per ui_principles).
 * Empty fields are stripped from the query string so URLs stay short.
 */
function submit(form) {
    if (form.dataset.submitting) return;
    form.dataset.submitting = '1';
    form.requestSubmit ? form.requestSubmit() : form.submit();
}

document.addEventListener('change', (e) => {
    const form = e.target.closest?.('form[data-autosubmit]');
    if (!form) return;
    const t = e.target;
    if (t.matches('select, input[type="date"], input[type="month"], input[type="checkbox"], input[type="radio"]')) {
        submit(form);
    }
});

let timer = null;
document.addEventListener('input', (e) => {
    const form = e.target.closest?.('form[data-autosubmit="debounce"]');
    if (!form || !e.target.matches('input[type="search"], input[type="text"]')) return;
    clearTimeout(timer);
    timer = setTimeout(() => submit(form), 250);
});

// Drop empty params from GET filter forms (search="" etc.)
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('form[data-filter-form]')) return;
    form.querySelectorAll('input[name], select[name]').forEach((field) => {
        if (field.type !== 'checkbox' && field.type !== 'radio' && field.value === '') {
            field.disabled = true;
            setTimeout(() => { field.disabled = false; }, 0);
        }
    });
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-autosubmit][data-submitting]').forEach((f) => delete f.dataset.submitting);
});
