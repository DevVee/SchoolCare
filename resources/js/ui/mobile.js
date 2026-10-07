/**
 * Phones and tablets: small behaviours that make the app feel native (styles in layout/_mobile.scss).
 *
 *   1. Tab rows (.nav-segmented, .nav-underline, .nav-tabs, .nav-pills, .c-segmented) become one
 *      full-width dropdown on phones whenever they do not fit their card (measured live, below md),
 *      and always below sm when they have more than three choices (ServiceCo's Tabs). Each option
 *      shows the tab and its count ("Pending review · 3"); picking one clicks the real tab, so links
 *      navigate with their query strings and Bootstrap / page-script tabs switch as before.
 *      Opt out with data-no-tab-select on the row or an ancestor.
 *   2. Text boxes grow with what is typed (below lg; never shorter than their rows). Opt out with
 *      data-no-grow.
 */
const BELOW_LG = window.matchMedia('(max-width: 991.98px)');
const BELOW_MD = window.matchMedia('(max-width: 767.98px)');
const BELOW_SM = window.matchMedia('(max-width: 575.98px)');

// ─── 1. Tab rows that do not fit → one dropdown ─────────────────────────────
const TAB_ROWS = '.nav-segmented, .nav-underline, .nav-tabs, .nav-pills, .c-segmented';
const rows = [];   // { nav, select }

function tabItems(nav) {
    const items = nav.querySelectorAll(':scope > .nav-link, :scope > .nav-item > .nav-link, :scope > li > .nav-link, :scope > a, :scope > button');
    return [...new Set(items)];
}

function tabLabel(link) {
    const copy = link.cloneNode(true);
    copy.querySelectorAll('.visually-hidden, .c-icon, .bi').forEach((n) => n.remove());
    const count = copy.querySelector('.nav-count, .count, .badge');
    const countText = count ? count.textContent.trim() : '';
    count?.remove();
    const text = copy.textContent.replace(/\s+/g, ' ').trim();
    return countText ? `${text} · ${countText}` : text;
}

const isOn = (l) => l.classList.contains('active') || l.classList.contains('is-on')
    || l.getAttribute('aria-current') === 'page' || l.getAttribute('aria-current') === 'true'
    || l.getAttribute('aria-selected') === 'true' || l.getAttribute('aria-pressed') === 'true';

/** True when the row, at its natural width, runs past the right edge of its card (or the screen). */
function overflows(nav) {
    const box = nav.closest('.card, .filter-bar, .page-header, .c-card-header, .app-content') || document.documentElement;
    const b = box.getBoundingClientRect();
    const padRight = parseFloat(getComputedStyle(box).paddingRight) || 0;
    const right = Math.min(b.right - padRight, document.documentElement.clientWidth);
    const left = nav.getBoundingClientRect().left;
    return left + nav.scrollWidth > right + 1;
}

function layout({ nav, select, items }) {
    let collapse = false;
    if (BELOW_MD.matches && nav.isConnected) {
        nav.classList.remove('tabs-collapsed');
        collapse = (BELOW_SM.matches && items.length > 3) || (nav.offsetParent !== null && overflows(nav));
    }
    nav.classList.toggle('tabs-collapsed', collapse);
    select.hidden = !collapse;
    nav.parentElement?.classList.toggle('has-tabs-select', collapse);
}

function tabSelect(nav) {
    if (nav.dataset.tabSelect || nav.closest('[data-no-tab-select], .app-sidebar, .dropdown-menu, .app-tabbar')) return;
    const items = tabItems(nav);
    if (items.length < 2) return;
    nav.dataset.tabSelect = '1';

    const select = document.createElement('select');
    select.className = 'form-select c-tabs-select';
    select.hidden = true;
    select.dataset.noSearch = '';   // the phone's own picker, not ui/select-search
    select.setAttribute('aria-label', nav.getAttribute('aria-label') || nav.closest('nav')?.getAttribute('aria-label') || 'Sections');
    items.forEach((link, i) => {
        const opt = document.createElement('option');
        opt.value = String(i);
        opt.textContent = tabLabel(link);
        if (link.matches('.disabled, :disabled')) opt.disabled = true;
        select.appendChild(opt);
    });
    const sync = () => { select.value = String(Math.max(0, items.findIndex(isOn))); };
    sync();
    select.addEventListener('change', () => items[Number(select.value)]?.click());
    // Tabs switched by page scripts or Bootstrap: keep the dropdown in step.
    nav.addEventListener('click', () => requestAnimationFrame(sync));
    nav.addEventListener('shown.bs.tab', sync);

    nav.before(select);
    const row = { nav, select, items };
    rows.push(row);
    layout(row);
}

let queued = false;
function relayout() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => { queued = false; rows.forEach(layout); });
}
window.addEventListener('resize', relayout);
BELOW_MD.addEventListener('change', relayout);
document.fonts?.ready.then(relayout);   // widths settle once the web font is in

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
