// ═════════════════════════════════════════════════════════════════════════════
// Combobox: type-to-search picker (x-ui.combobox, x-ui.patient-picker).
//
// Markup: resources/views/components/ui/combobox.blade.php. The [data-combobox]
// wrapper holds
//   input[type=hidden][data-combobox-value]   what the form submits (the chosen id)
//   input[role=combobox]                      the search box (no name, never submitted)
//   [data-combobox-selection]                 the chosen item plus a clear button
//   [data-combobox-menu] > [role=listbox]     results from GET data-source?q=...
//
// ARIA combobox (list autocomplete, first result highlighted): Up / Down move the
// highlight (aria-activedescendant), Enter picks, Escape closes the list (a second
// Escape clears the text), Tab leaves. Requests go out 150ms after typing stops;
// stale ones are aborted and answers are cached per query.
//
// Events on the wrapper: `combobox:change` with detail.item (null when cleared).
// The hidden input also fires a bubbling `change`. The current item is mirrored as
// JSON in data-combobox-item so inline page scripts can read it before this runs.
//
// window.combobox.mount(el) wires markup added later (modals, cloned rows).
// ═════════════════════════════════════════════════════════════════════════════

const DEBOUNCE_MS = 150;
const SPINNER_DELAY_MS = 250;
const CACHE_MAX = 60;

function span(className, text) {
    const node = document.createElement('span');
    node.className = className;
    node.textContent = text;
    return node;
}

function mount(root) {
    if (!(root instanceof HTMLElement) || root.dataset.comboboxReady) return;

    const hidden    = root.querySelector('[data-combobox-value]');
    const field     = root.querySelector('.combobox-field');
    const input     = root.querySelector('[role="combobox"]');
    const selection = root.querySelector('[data-combobox-selection]');
    const selLabel  = root.querySelector('[data-combobox-selection-label]');
    const selDetail = root.querySelector('[data-combobox-selection-detail]');
    const selMeta   = root.querySelector('[data-combobox-selection-meta]');
    const clearBtn  = root.querySelector('[data-combobox-clear]');
    const menu      = root.querySelector('[data-combobox-menu]');
    const listbox   = root.querySelector('[role="listbox"]');
    const message   = root.querySelector('[data-combobox-message]');
    const status    = root.querySelector('[data-combobox-status]');
    if (!hidden || !field || !input || !selection || !menu || !listbox || !message) return;

    root.dataset.comboboxReady = '1';

    const source   = root.dataset.source || '';
    const minChars = Math.max(1, parseInt(root.dataset.minChars || '2', 10) || 2);
    const nouns    = root.dataset.nouns || 'results';
    const hint     = root.dataset.hint || `Type at least ${minChars} characters to search.`;

    const cache = new Map();   // lower-cased query -> { results, more }
    let items = [];            // results currently listed
    let active = -1;           // highlighted option index
    let shownQuery = null;     // query whose results are listed
    let debounce = 0;
    let spinner = 0;
    let request = null;        // AbortController of the request in flight
    let seq = 0;

    // ── Menu ────────────────────────────────────────────────────────────────
    function place() {
        // Open upwards only when there is clearly more room above.
        const rect = input.getBoundingClientRect();
        const below = window.innerHeight - rect.bottom;
        root.classList.toggle('opens-up', below < 260 && rect.top > below);
    }

    function openMenu() {
        if (!menu.hidden) return;
        place();
        menu.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        root.classList.add('is-open');
    }

    function closeMenu() {
        if (menu.hidden) return;
        menu.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        root.classList.remove('is-open');
        setActive(-1);
    }

    function showMessage(text) {
        message.textContent = text || '';
        message.hidden = !text;
    }

    function announce(text) {
        if (!status) return;
        status.textContent = '';
        if (text) window.setTimeout(() => { status.textContent = text; }, 50);
    }

    function setActive(index, scroll = true) {
        const options = listbox.children;
        if (active >= 0 && options[active]) {
            options[active].classList.remove('is-active');
            options[active].setAttribute('aria-selected', 'false');
        }
        active = index >= 0 && index < options.length ? index : -1;
        if (active < 0) {
            input.removeAttribute('aria-activedescendant');
            return;
        }
        const option = options[active];
        option.classList.add('is-active');
        option.setAttribute('aria-selected', 'true');
        input.setAttribute('aria-activedescendant', option.id);
        if (scroll) {
            // Keep the highlighted row inside the menu without scrolling the page.
            const top = option.offsetTop;
            const bottom = top + option.offsetHeight;
            if (top < menu.scrollTop) menu.scrollTop = top - 6;
            else if (bottom > menu.scrollTop + menu.clientHeight) menu.scrollTop = bottom - menu.clientHeight + 6;
        }
    }

    function render(query, results, more) {
        items = results;
        shownQuery = query;
        active = -1;
        listbox.replaceChildren(...results.map((item, i) => {
            const li = document.createElement('li');
            li.id = `${listbox.id}-opt-${i}`;
            li.className = 'combobox-option';
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', 'false');
            li.dataset.index = String(i);
            const text = span('combobox-text', '');
            text.append(span('combobox-label', item.label || ''));
            if (item.detail) text.append(span('combobox-detail', item.detail));
            li.append(text);
            if (item.meta) li.append(span('combobox-meta tabular', item.meta));
            return li;
        }));

        if (!results.length) {
            showMessage(`No ${nouns} match '${query}'.`);
            announce(`No ${nouns} match '${query}'.`);
        } else {
            showMessage(more ? `Showing the first ${results.length}. Keep typing to narrow the list.` : '');
            const count = results.length === 1 ? '1 match' : `${results.length}${more ? ' or more' : ''} matches`;
            announce(`${count}. Use the up and down arrows to choose, Enter to pick.`);
        }
        menu.scrollTop = 0;
        openMenu();
        setActive(results.length ? 0 : -1);
    }

    function showHint() {
        items = [];
        shownQuery = null;
        listbox.replaceChildren();
        showMessage(hint);
        openMenu();
    }

    // ── Search ──────────────────────────────────────────────────────────────
    function stopLoading() {
        window.clearTimeout(spinner);
        root.classList.remove('is-loading');
    }

    function abort() {
        window.clearTimeout(debounce);
        if (request) request.abort();
        request = null;
        stopLoading();
    }

    async function search(query) {
        abort();
        const mine = ++seq;
        request = new AbortController();
        spinner = window.setTimeout(() => root.classList.add('is-loading'), SPINNER_DELAY_MS);

        const url = new URL(source, window.location.href);
        url.searchParams.set('q', query);

        try {
            const res = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: request.signal,
            });
            if (!res.ok) throw Object.assign(new Error('HTTP ' + res.status), { status: res.status });
            const data = await res.json();
            const entry = { results: Array.isArray(data.results) ? data.results : [], more: !!data.more };
            if (cache.size >= CACHE_MAX) cache.delete(cache.keys().next().value);
            cache.set(query.toLowerCase(), entry);
            if (mine !== seq || input.value.trim() !== query) return;
            render(query, entry.results, entry.more);
        } catch (err) {
            if (err.name === 'AbortError' || mine !== seq) return;
            items = [];
            shownQuery = null;
            listbox.replaceChildren();
            const text = err.status === 401 || err.status === 419
                ? 'You have been signed out. Reload the page to keep searching.'
                : 'Search is not available right now. Check the connection and try again.';
            showMessage(text);
            announce(text);
            openMenu();
        } finally {
            if (mine === seq) {
                request = null;
                stopLoading();
            }
        }
    }

    function update(immediate = false) {
        const query = input.value.trim();
        window.clearTimeout(debounce);

        if (query.length === 0) {
            abort();
            closeMenu();
            items = [];
            shownQuery = null;
            listbox.replaceChildren();
            return;
        }
        if (query.length < minChars) {
            abort();
            showHint();
            return;
        }
        if (query === shownQuery) {
            openMenu();
            if (active < 0 && items.length) setActive(0);
            return;
        }
        const cached = cache.get(query.toLowerCase());
        if (cached) {
            abort();
            render(query, cached.results, cached.more);
            return;
        }
        if (immediate) search(query);
        else debounce = window.setTimeout(() => search(query), DEBOUNCE_MS);
    }

    // ── Selection ───────────────────────────────────────────────────────────
    function emit(item) {
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
        root.dispatchEvent(new CustomEvent('combobox:change', { bubbles: true, detail: { item } }));
    }

    function pick(item) {
        if (!item) return;
        abort();
        hidden.value = String(item.id);
        root.dataset.comboboxItem = JSON.stringify(item);
        selLabel.textContent = item.label || '';
        selDetail.textContent = item.detail || '';
        selDetail.hidden = !item.detail;
        selMeta.textContent = item.meta || '';
        selMeta.hidden = !item.meta;

        closeMenu();
        input.value = '';
        field.hidden = true;
        selection.hidden = false;
        root.classList.add('has-selection');
        selection.focus({ preventScroll: true });
        announce(`${item.label} selected.`);
        emit(item);
    }

    function clear() {
        hidden.value = '';
        root.dataset.comboboxItem = '';
        selection.hidden = true;
        field.hidden = false;
        root.classList.remove('has-selection');
        items = [];
        shownQuery = null;
        listbox.replaceChildren();
        input.focus();
        announce('Selection cleared.');
        emit(null);
    }

    // ── Events ──────────────────────────────────────────────────────────────
    input.addEventListener('input', () => update());

    input.addEventListener('keydown', (e) => {
        if (e.isComposing) return;
        switch (e.key) {
            case 'ArrowDown':
            case 'ArrowUp': {
                e.preventDefault();
                if (menu.hidden || e.altKey) {
                    if (input.value.trim()) update(true);
                    else showHint();
                    return;
                }
                if (!items.length) return;
                const step = e.key === 'ArrowDown' ? 1 : -1;
                setActive(active < 0 ? 0 : Math.min(items.length - 1, Math.max(0, active + step)));
                break;
            }
            case 'Enter':
                // Never submit the form from the search box.
                e.preventDefault();
                if (!menu.hidden && active >= 0) pick(items[active]);
                break;
            case 'Escape':
                if (!menu.hidden) {
                    e.preventDefault();
                    e.stopPropagation();   // do not close a surrounding modal
                    closeMenu();
                } else if (input.value) {
                    e.preventDefault();
                    e.stopPropagation();
                    input.value = '';
                    update();
                }
                break;
            case 'Tab':
                closeMenu();
                break;
            default:
                break;
        }
    });

    // Re-open the last results when coming back to a box that still has text.
    input.addEventListener('focus', () => { if (input.value.trim()) update(); });
    input.addEventListener('click', () => { if (menu.hidden && input.value.trim()) update(); });

    // Keep focus in the input while using the menu with a mouse.
    menu.addEventListener('mousedown', (e) => e.preventDefault());

    listbox.addEventListener('mousemove', (e) => {
        const option = e.target instanceof Element ? e.target.closest('[role="option"]') : null;
        if (option && Number(option.dataset.index) !== active) setActive(Number(option.dataset.index), false);
    });

    listbox.addEventListener('click', (e) => {
        const option = e.target instanceof Element ? e.target.closest('[role="option"]') : null;
        if (option) pick(items[Number(option.dataset.index)]);
    });

    clearBtn?.addEventListener('click', clear);

    // Backspace / Delete on the chosen item clears it, like deleting the text.
    selection.addEventListener('keydown', (e) => {
        if ((e.key === 'Backspace' || e.key === 'Delete') && e.target === selection) {
            e.preventDefault();
            clear();
        }
    });

    root.addEventListener('focusout', (e) => {
        if (!(e.relatedTarget instanceof Node) || !root.contains(e.relatedTarget)) closeMenu();
    });

    document.addEventListener('pointerdown', (e) => {
        if (!menu.hidden && e.target instanceof Node && !root.contains(e.target)) closeMenu();
    });

    window.addEventListener('resize', () => { if (!menu.hidden) place(); }, { passive: true });
}

function mountAll(scope = document) {
    scope.querySelectorAll('[data-combobox]').forEach(mount);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => mountAll());
} else {
    mountAll();
}

window.combobox = { mount, mountAll };
