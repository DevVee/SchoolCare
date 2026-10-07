/**
 * Phones and tablets: small behaviours that make the app feel native (styles in layout/_mobile.scss).
 *
 *   1. Tab rows with more than three choices (.nav-segmented, .nav-underline, .nav-tabs, .nav-pills)
 *      get a single dropdown that phones show instead of the row. Picking an option clicks the
 *      real tab, so links navigate and Bootstrap / page-script tabs switch as before.
 *      Opt out with data-no-tab-select on the nav.
 *   2. Text boxes grow with what is typed (below lg; never shorter than their rows). Opt out with
 *      data-no-grow.
 */
const BELOW_LG = window.matchMedia('(max-width: 991.98px)');

// ─── 1. Many tabs → one dropdown on phones ──────────────────────────────────
const TAB_ROWS = '.nav-segmented, .nav-underline, .nav-tabs, .nav-pills';

function tabLabel(link) {
    const copy = link.cloneNode(true);
    copy.querySelectorAll('.visually-hidden, .c-icon, .bi').forEach((n) => n.remove());
    const count = copy.querySelector('.nav-count');
    const countText = count ? count.textContent.trim() : '';
    count?.remove();
    const text = copy.textContent.replace(/\s+/g, ' ').trim();
    return countText ? `${text} (${countText})` : text;
}

function tabSelect(nav) {
    if (nav.dataset.tabSelect || nav.closest('[data-no-tab-select]') || nav.closest('.app-sidebar, .dropdown-menu')) return;
    const links = [...nav.querySelectorAll(':scope > .nav-link, :scope > .nav-item > .nav-link, :scope > li > .nav-link')];
    if (links.length <= 3) return;
    nav.dataset.tabSelect = '1';

    const select = document.createElement('select');
    select.className = 'form-select c-tabs-select';
    select.dataset.noSearch = '';   // the phone's own picker, not ui/select-search
    select.setAttribute('aria-label', nav.getAttribute('aria-label') || nav.closest('nav')?.getAttribute('aria-label') || 'Sections');
    links.forEach((link, i) => {
        const opt = document.createElement('option');
        opt.value = String(i);
        opt.textContent = tabLabel(link);
        if (link.matches('.disabled, :disabled')) opt.disabled = true;
        select.appendChild(opt);
    });
    const sync = () => {
        const i = links.findIndex((l) => l.classList.contains('active') || l.getAttribute('aria-current') === 'page' || l.getAttribute('aria-selected') === 'true');
        select.value = String(Math.max(0, i));
    };
    sync();
    select.addEventListener('change', () => links[Number(select.value)]?.click());
    // Tabs switched by page scripts or Bootstrap: keep the dropdown in step.
    nav.addEventListener('click', () => requestAnimationFrame(sync));
    nav.addEventListener('shown.bs.tab', sync);

    nav.classList.add('has-tabs-select');
    nav.before(select);
}

// ─── 2. Text boxes grow as you type ─────────────────────────────────────────
function fit(t) {
    if (!BELOW_LG.matches || t.hasAttribute('data-no-grow') || !t.isConnected) return;
    if (!t.offsetHeight) return;   // hidden (closed modal, collapsed panel)
    if (!t.dataset.growMin) t.dataset.growMin = String(t.offsetHeight);
    t.style.height = 'auto';
    const next = Math.max(Number(t.dataset.growMin), t.scrollHeight + t.offsetHeight - t.clientHeight);
    t.style.height = `${next}px`;
}

document.addEventListener('input', (e) => {
    if (e.target instanceof HTMLTextAreaElement) fit(e.target);
});

function init() {
    document.querySelectorAll(TAB_ROWS).forEach(tabSelect);
    document.querySelectorAll('textarea').forEach((t) => { if (t.value) fit(t); });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
// Pre-filled text boxes inside modals have no height until the modal opens.
document.addEventListener('shown.bs.modal', (e) => e.target.querySelectorAll('textarea').forEach((t) => t.value && fit(t)));
