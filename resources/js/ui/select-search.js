// ═════════════════════════════════════════════════════════════════════════════
// Searchable dropdowns: long <select class="form-select"> lists become a
// type-to-filter field (owner: "all dropdowns with search combined").
//
// Progressive enhancement, no markup changes. The original <select> stays in the
// DOM, visually hidden, and remains the source of truth:
//   • the form submits it; `required`, server errors (.is-invalid, the
//     .invalid-feedback after it, aria-describedby) and old() values keep working;
//   • picking an option selects it on the <select> and fires `input` + `change`
//     there, so page scripts and filter auto-submit run exactly as before;
//   • page scripts may rebuild the options (innerHTML, appendChild, option.hidden,
//     option.disabled, textContent), set select.value / selectedIndex, toggle
//     `disabled` or `is-invalid`, or reset the form: the field follows (a
//     MutationObserver plus a value setter on the element). Anything else can call
//     select.dispatchEvent(new Event('options-changed')) or window.selectSearch.refresh(select).
//   • a required, empty select that blocks submission reports the browser's own
//     message on the visible search box instead of the hidden <select>.
//
// Which selects: select.form-select with more than 6 choices (the empty
// placeholder option does not count), or any select.form-select[data-search].
// Skipped: [data-no-search] (on the select or an ancestor), multiple / size > 1,
// anything inside [data-combobox] (x-ui.combobox / x-ui.patient-picker already
// search). Short selects are watched and upgraded once a script fills them
// (address picker, dependent lists).
//
// Keyboard (ARIA 1.2 combobox with list autocomplete): type to filter (case and
// accent insensitive; every word must appear somewhere in the label), Up / Down
// move, Enter picks, Escape closes the list without closing a surrounding modal or
// filter panel, Tab leaves. Page Up / Page Down jump 8 rows; Home / End jump to
// the ends while nothing is typed. Space or Alt + Down opens the list.
//
// The list is a fixed panel appended to the nearest modal / offcanvas (or <body>)
// while open, so overflow: hidden never clips it. Styles:
// resources/scss/components/_select-search.scss
//
// window.selectSearch: enhance(select), refresh(select), destroy(select), mountAll(scope)
// ═════════════════════════════════════════════════════════════════════════════

const MIN_CHOICES = 1;       // owner: "all dropdowns with search", so every list with a real choice is upgraded
const RENDER_LIMIT = 300;    // rows drawn at once while filtering very long lists
const PAGE_STEP = 8;         // Page Up / Page Down
const GAP = 6;               // px between the field and the list
const EDGE = 8;              // px kept free at the viewport edge
const MIN_ROOM = 120;        // px: below this the list flips to the roomier side

const SELECT_VALUE = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
const SELECT_INDEX = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');

// Utility classes that place the control in its layout; copied to the wrapper.
const LAYOUT_CLASS = /^(w-|mw-|m[tbsexy]?-|flex-|col($|-)|order-|align-self-|d-)/;

const instances = new WeakMap();   // <select> -> instance
const wrappers = new WeakSet();    // live wrappers (a cloned copy is dead markup)
const watching = new WeakMap();    // short <select> -> MutationObserver waiting for options
let uid = 0;

// Case and accent insensitive text: "Ñino Álvarez" -> "nino alvarez".
function fold(text) {
    return String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
}

function make(tag, className, attrs) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (attrs) Object.entries(attrs).forEach(([k, v]) => node.setAttribute(k, v));
    return node;
}

function choiceCount(select) {
    let n = 0;
    for (const option of select.options) {
        if (option.value !== '' && !option.hidden) n++;
    }
    return n;
}

function eligible(select) {
    return select instanceof HTMLSelectElement
        && select.classList.contains('form-select')
        && !select.multiple
        && !(select.size > 1)
        && !select.hasAttribute('data-no-search')
        && !select.closest('[data-combobox]')
        && (select.hasAttribute('data-search') || !select.closest('[data-no-search]'));
}

function wanted(select) {
    return select.hasAttribute('data-search') || choiceCount(select) >= MIN_CHOICES;
}

// ─────────────────────────────────────────────────────────────────────────────
// One enhanced select
// ─────────────────────────────────────────────────────────────────────────────
function enhance(select) {
    if (instances.has(select)) return instances.get(select);

    const base = `select-search-${++uid}`;
    const listId = `${base}-list`;

    // ── Field: [wrapper > control > input + clear] + live status ────────────
    const wrap = make('div', 'select-search', { 'data-select-search': '' });
    const control = make('div', 'select-search-control');
    const input = make('input', 'select-search-input', {
        id: `${base}-input`,
        role: 'combobox',
        'aria-autocomplete': 'list',
        'aria-expanded': 'false',
        autocomplete: 'off',
        autocapitalize: 'off',
        autocorrect: 'off',
        spellcheck: 'false',
    });
    const clearBtn = make('button', 'select-search-clear', { type: 'button', tabindex: '-1', 'aria-label': 'Clear selection' });
    clearBtn.innerHTML = '<i class="bi bi-x-lg c-icon" aria-hidden="true"></i>';
    clearBtn.hidden = true;
    const status = make('span', 'visually-hidden', { role: 'status', 'aria-live': 'polite' });
    control.append(input, clearBtn);
    wrap.append(control, status);

    // ── List panel (in the DOM only while open) ─────────────────────────────
    const menu = make('div', 'select-search-menu');
    const list = make('div', 'select-search-list', { id: listId, role: 'listbox' });
    const message = make('p', 'select-search-message');
    message.hidden = true;
    menu.append(list, message);

    // ── Hide the native select (still focusable for labels, still validated) ─
    select.dataset.selectSearchTabindex = select.getAttribute('tabindex') ?? '';
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.classList.add('select-search-native');
    select.after(wrap);
    wrappers.add(wrap);

    let items = [];        // listable options: { option, label, key, group, disabled, empty }
    let shown = [];        // items drawn in the list, in order
    let rows = [];         // their elements
    let matchCount = 0;
    let active = -1;
    let isOpen = false;
    let searching = false; // focused: the box holds the search text, the choice shows as its placeholder
    let queued = false;
    let copied = [];
    let frame = 0;
    let announceTimer = 0;

    const current = () => (select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null);
    const chosen = () => {
        const option = current();
        return option && option.value !== '' ? option : null;
    };
    const emptyOption = () => select.querySelector('option[value=""]');
    const host = () => select.closest('.modal, .offcanvas, dialog') || document.body;

    function readItems() {
        const out = [];
        const add = (option, group, groupDisabled) => {
            if (option.hidden || option.style.display === 'none') return;
            const empty = option.value === '';
            const disabled = option.disabled || groupDisabled;
            // A required select's empty option is its prompt, not a choice.
            if (empty && (select.required || disabled)) return;
            const label = option.label;
            out.push({ option, label, key: fold(label), group, disabled, empty });
        };
        for (const child of select.children) {
            if (child.tagName === 'OPTGROUP') {
                if (child.hidden) continue;
                for (const option of child.children) {
                    if (option.tagName === 'OPTION') add(option, child, child.disabled);
                }
            } else if (child.tagName === 'OPTION') {
                add(child, null, false);
            }
        }
        return out;
    }

    // ── State from the select ───────────────────────────────────────────────
    function syncLayout() {
        const next = Array.from(select.classList).filter((c) => LAYOUT_CLASS.test(c));
        copied.forEach((c) => { if (!next.includes(c)) wrap.classList.remove(c); });
        next.forEach((c) => wrap.classList.add(c));
        copied = next;
        wrap.hidden = select.hidden;
        wrap.style.display = select.style.display === 'none' ? 'none' : '';
        wrap.style.width = select.style.width;
        wrap.style.minWidth = select.style.minWidth;
        wrap.style.maxWidth = select.style.maxWidth;
    }

    function syncAria() {
        const labels = Array.from(select.labels || []);
        if (labels.length) {
            labels.forEach((label, i) => { if (!label.id) label.id = `${base}-label${i || ''}`; });
            const ids = labels.map((label) => label.id).join(' ');
            input.setAttribute('aria-labelledby', ids);
            list.setAttribute('aria-labelledby', ids);
            input.removeAttribute('aria-label');
            list.removeAttribute('aria-label');
        } else if (select.hasAttribute('aria-labelledby')) {
            input.setAttribute('aria-labelledby', select.getAttribute('aria-labelledby'));
            list.setAttribute('aria-labelledby', select.getAttribute('aria-labelledby'));
        } else {
            const name = select.getAttribute('aria-label') || select.title || emptyOption()?.label || '';
            input.removeAttribute('aria-labelledby');
            list.removeAttribute('aria-labelledby');
            if (name) {
                input.setAttribute('aria-label', name);
                list.setAttribute('aria-label', name);
            }
        }
        ['aria-describedby', 'aria-invalid'].forEach((attr) => {
            if (select.hasAttribute(attr)) input.setAttribute(attr, select.getAttribute(attr));
            else input.removeAttribute(attr);
        });
        if (select.required) input.setAttribute('aria-required', 'true');
        else input.removeAttribute('aria-required');
        // Same form as the select, so a validation message lands on this box.
        if (select.hasAttribute('form')) input.setAttribute('form', select.getAttribute('form'));
        else input.removeAttribute('form');
    }

    function paint() {
        const option = chosen();
        const text = option ? option.label : '';
        const prompt = emptyOption()?.label || select.getAttribute('data-placeholder') || '';
        wrap.classList.toggle('has-value', !!option);
        if (searching) {
            input.placeholder = text || prompt;
        } else {
            input.value = text;
            input.placeholder = text ? '' : prompt;
        }
        const empty = emptyOption();
        clearBtn.hidden = !option || input.disabled || select.required || !empty || empty.disabled || empty.hidden;
    }

    function refresh() {
        queued = false;
        if (!select.isConnected) return;
        syncLayout();
        syncAria();

        const disabled = select.disabled || select.matches(':disabled');
        input.disabled = disabled;
        wrap.classList.toggle('is-disabled', disabled);
        wrap.classList.toggle('is-invalid', select.classList.contains('is-invalid'));
        wrap.classList.toggle('select-search-sm', select.classList.contains('form-select-sm'));
        wrap.classList.toggle('select-search-lg', select.classList.contains('form-select-lg'));
        syncValidity();

        // Intrinsic width close to a native select's (widest option), for auto-width layouts.
        let longest = 4;
        for (const option of select.options) longest = Math.max(longest, option.label.length);
        input.size = Math.min(longest, 30);

        paint();

        if (isOpen && disabled) close();
        else if (isOpen) {
            items = readItems();
            render(true);
            place();
        }
    }

    function schedule() {
        if (queued) return;
        queued = true;
        queueMicrotask(refresh);
    }

    // The search box carries the select's validity: when the hidden select blocks
    // submission, the browser reports (focus + message) on the visible box instead.
    function syncValidity() {
        const bad = !input.disabled && select.willValidate && !select.validity.valid;
        input.setCustomValidity(bad ? (select.validationMessage || 'Please select an item in the list.') : '');
    }

    // Value changes: validity follows at once (a script may submit right after), the rest in a microtask.
    function changed() {
        syncValidity();
        schedule();
    }

    function enterSearch() {
        if (searching) return;
        searching = true;
        input.value = '';
        paint();
    }

    function leaveSearch() {
        searching = false;
        paint();
    }

    // ── List ────────────────────────────────────────────────────────────────
    function render(keep = false) {
        const terms = fold(input.value).split(' ').filter(Boolean);
        const previous = keep && active >= 0 ? shown[active]?.option : null;
        const selected = current();
        const matches = terms.length ? items.filter((it) => terms.every((t) => it.key.includes(t))) : items;
        matchCount = matches.length;

        let limit = RENDER_LIMIT;
        if (!terms.length) {
            const at = matches.findIndex((it) => it.option === selected);
            if (at >= limit) limit = at + 50;
        }
        shown = matches.slice(0, limit);

        const fragment = document.createDocumentFragment();
        let group = null;
        let box = fragment;
        rows = shown.map((it, i) => {
            if (it.group !== group) {
                group = it.group;
                if (group) {
                    const heading = make('div', 'select-search-group-label', { id: `${listId}-g${i}`, role: 'presentation' });
                    heading.textContent = group.label;
                    box = make('div', 'select-search-group', { role: 'group', 'aria-labelledby': heading.id });
                    box.append(heading);
                    fragment.append(box);
                } else {
                    box = fragment;
                }
            }
            const row = make('div', 'select-search-option', { id: `${listId}-o${i}`, role: 'option', 'aria-selected': 'false' });
            row.dataset.index = String(i);
            if (it.disabled) {
                row.classList.add('is-disabled');
                row.setAttribute('aria-disabled', 'true');
            }
            if (it.empty) row.classList.add('is-empty');
            const text = make('span', 'select-search-option-label');
            text.textContent = it.label || '\u00a0';   // keeps an empty label's row height
            row.append(text);
            if (it.option === selected) {
                row.classList.add('is-selected');
                row.append(make('i', 'bi bi-check2 select-search-check', { 'aria-hidden': 'true' }));
            }
            box.append(row);
            return row;
        });
        list.replaceChildren(fragment);

        let note = '';
        if (!items.length) note = 'No options';
        else if (!matchCount) note = 'No matches';
        else if (matchCount > shown.length) note = `Showing ${shown.length} of ${matchCount}. Keep typing to narrow the list.`;
        message.textContent = note;
        message.hidden = !note;

        let next = previous ? shown.findIndex((it) => it.option === previous && !it.disabled) : -1;
        if (next < 0 && !terms.length && selected) next = shown.findIndex((it) => it.option === selected && !it.disabled);
        if (next < 0) next = firstEnabled(0, 1);
        active = -1;
        if (!keep) menu.scrollTop = 0;
        setActive(next, 'nearest');
    }

    function firstEnabled(from, dir) {
        for (let i = from; i >= 0 && i < shown.length; i += dir) {
            if (!shown[i].disabled) return i;
        }
        return -1;
    }

    function reveal(row, mode) {
        const top = row.offsetTop;
        const bottom = top + row.offsetHeight;
        if (mode === 'center') {
            menu.scrollTop = Math.max(0, top - (menu.clientHeight - row.offsetHeight) / 2);
        } else if (top < menu.scrollTop) {
            menu.scrollTop = top - 6;
        } else if (bottom > menu.scrollTop + menu.clientHeight) {
            menu.scrollTop = bottom - menu.clientHeight + 6;
        }
    }

    function setActive(index, scroll) {
        if (active >= 0 && rows[active]) {
            rows[active].classList.remove('is-active');
            rows[active].setAttribute('aria-selected', 'false');
        }
        active = index >= 0 && index < rows.length ? index : -1;
        if (active < 0) {
            input.removeAttribute('aria-activedescendant');
            return;
        }
        const row = rows[active];
        row.classList.add('is-active');
        row.setAttribute('aria-selected', 'true');
        input.setAttribute('aria-activedescendant', row.id);
        if (scroll) reveal(row, scroll);
    }

    function step(delta) {
        const dir = delta > 0 ? 1 : -1;
        let target = active;
        let i = active < 0 ? (dir > 0 ? -1 : rows.length) : active;
        for (let left = Math.abs(delta); left > 0; left--) {
            let j = i + dir;
            while (j >= 0 && j < rows.length && shown[j].disabled) j += dir;
            if (j < 0 || j >= rows.length) break;
            i = j;
            target = j;
        }
        if (target !== active && target >= 0) setActive(target, 'nearest');
    }

    // ── Placement: fixed panel under (or above) the field, same width ───────
    function place() {
        if (!isOpen) return;
        const rect = control.getBoundingClientRect();
        const vv = window.visualViewport;
        const viewTop = vv ? vv.offsetTop : 0;
        const viewBottom = vv ? vv.offsetTop + vv.height : window.innerHeight;
        if ((!rect.width && !rect.height) || rect.bottom < viewTop || rect.top > viewBottom) {
            close();
            return;
        }
        menu.style.left = `${rect.left}px`;
        menu.style.width = `${rect.width}px`;
        menu.style.maxHeight = '';
        const natural = menu.offsetHeight;   // capped by the CSS max-height
        const below = viewBottom - rect.bottom - GAP - EDGE;
        const above = rect.top - viewTop - GAP - EDGE;
        const up = natural > below && below < MIN_ROOM * 2 && above > below;
        const room = Math.max(up ? above : below, MIN_ROOM);
        const height = Math.min(natural, room);
        if (height < natural) menu.style.maxHeight = `${height}px`;
        menu.style.top = `${up ? rect.top - GAP - height : rect.bottom + GAP}px`;
        menu.classList.toggle('is-above', up);
    }

    function onViewport(e) {
        if (e && e.type === 'scroll' && e.target instanceof Node && menu.contains(e.target)) return;
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(place);
    }

    function onOutside(e) {
        const target = e.target;
        if (target instanceof Node && (wrap.contains(target) || menu.contains(target))) return;
        close();
    }

    function listen(on) {
        const vv = window.visualViewport;
        if (on) {
            document.addEventListener('scroll', onViewport, { capture: true, passive: true });
            window.addEventListener('resize', onViewport, { passive: true });
            vv?.addEventListener('resize', onViewport, { passive: true });
            vv?.addEventListener('scroll', onViewport, { passive: true });
            document.addEventListener('pointerdown', onOutside, true);
        } else {
            document.removeEventListener('scroll', onViewport, { capture: true });
            window.removeEventListener('resize', onViewport);
            vv?.removeEventListener('resize', onViewport);
            vv?.removeEventListener('scroll', onViewport);
            document.removeEventListener('pointerdown', onOutside, true);
            cancelAnimationFrame(frame);
        }
    }

    // ── Open / close / pick ─────────────────────────────────────────────────
    function open() {
        if (isOpen || input.disabled) return;
        enterSearch();
        isOpen = true;
        items = readItems();
        wrap.classList.add('is-open');
        input.setAttribute('aria-expanded', 'true');
        input.setAttribute('aria-controls', listId);
        host().appendChild(menu);
        render();
        place();
        if (active >= 0 && rows[active]) reveal(rows[active], 'center');
        listen(true);
    }

    function close() {
        if (!isOpen) return;
        isOpen = false;
        listen(false);
        clearTimeout(announceTimer);
        menu.remove();
        list.replaceChildren();
        items = [];
        shown = [];
        rows = [];
        active = -1;
        wrap.classList.remove('is-open');
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-controls');
        input.removeAttribute('aria-activedescendant');
        if (searching) input.value = '';
        if (document.activeElement !== input) leaveSearch();
        else paint();
    }

    function announce(text) {
        status.textContent = '';
        if (text) window.setTimeout(() => { status.textContent = text; }, 60);
    }

    function announceCount() {
        clearTimeout(announceTimer);
        announceTimer = window.setTimeout(() => {
            if (!isOpen || !input.value.trim()) return;
            announce(matchCount === 0 ? 'No matches' : (matchCount === 1 ? '1 match' : `${matchCount} matches`));
        }, 400);
    }

    function choose(option) {
        if (!option || option.disabled || !select.contains(option)) return;
        const before = select.selectedIndex;
        option.selected = true;
        close();
        refresh();
        announce(option.value === '' ? 'Selection cleared.' : `${option.label} selected.`);
        if (select.selectedIndex !== before) {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // ── Events: search box ──────────────────────────────────────────────────
    input.addEventListener('focus', () => {
        wrap.classList.add('is-focused');
        if (!isOpen) refresh();   // safety net: pick up any change made without an event
        enterSearch();
    });

    input.addEventListener('blur', () => {
        wrap.classList.remove('is-focused');
        close();
        leaveSearch();
    });

    input.addEventListener('input', (e) => {
        // The <select> reports changes; the search text is not form data (and must
        // not trigger x-ui.filters' type-to-search auto-submit).
        e.stopPropagation();
        if (!searching) {
            searching = true;
            paint();
        }
        if (!isOpen) open();
        else render();
        announceCount();
    });
    input.addEventListener('change', (e) => e.stopPropagation());

    input.addEventListener('keydown', (e) => {
        if (e.isComposing || input.disabled) return;
        switch (e.key) {
            case 'ArrowDown':
            case 'ArrowUp':
                e.preventDefault();
                if (!isOpen) {
                    if (!(e.altKey && e.key === 'ArrowUp')) open();
                } else if (e.altKey) {
                    if (e.key === 'ArrowUp') close();
                } else {
                    step(e.key === 'ArrowDown' ? 1 : -1);
                }
                break;
            case 'PageDown':
            case 'PageUp':
                if (!isOpen) return;
                e.preventDefault();
                step(e.key === 'PageDown' ? PAGE_STEP : -PAGE_STEP);
                break;
            case 'Home':
            case 'End':
                if (!isOpen || input.value) return;
                e.preventDefault();
                setActive(e.key === 'Home' ? firstEnabled(0, 1) : firstEnabled(shown.length - 1, -1), 'nearest');
                break;
            case 'Enter':
                // Never submit the form from the search box.
                e.preventDefault();
                if (!isOpen) open();
                else if (active >= 0) choose(shown[active].option);
                break;
            case ' ':
                if (!input.value) {
                    e.preventDefault();
                    open();
                }
                break;
            case 'Escape':
                if (isOpen) {
                    e.preventDefault();
                    e.stopPropagation();   // keep a surrounding modal or filter panel open
                    close();
                }
                break;
            case 'Tab':
                close();
                break;
            default:
                break;
        }
    });

    // ── Events: pointer ─────────────────────────────────────────────────────
    control.addEventListener('mousedown', (e) => {
        if (e.button !== 0 || input.disabled) return;
        if (clearBtn.contains(e.target)) {
            e.preventDefault();   // keep focus where it is
            return;
        }
        if (e.target !== input) {
            // Padding or chevron: focus the box and toggle the list.
            e.preventDefault();
            input.focus();
            if (isOpen) close();
            else open();
            return;
        }
        if (!isOpen) open();   // a click inside the open box only moves the caret
    });

    clearBtn.addEventListener('click', () => {
        choose(emptyOption());
        input.focus();
    });

    menu.addEventListener('mousedown', (e) => e.preventDefault());   // keep focus in the box
    menu.addEventListener('click', (e) => {
        // Not an "outside" click for Bootstrap dropdowns (the filter panel stays open).
        e.stopPropagation();
        const row = e.target instanceof Element ? e.target.closest('.select-search-option') : null;
        if (!row) return;
        const it = shown[Number(row.dataset.index)];
        if (it && !it.disabled) choose(it.option);
    });
    menu.addEventListener('mousemove', (e) => {
        const row = e.target instanceof Element ? e.target.closest('.select-search-option') : null;
        if (!row || row.classList.contains('is-disabled')) return;
        const i = Number(row.dataset.index);
        if (i !== active) setActive(i, null);
    });

    // ── Events: the native select ───────────────────────────────────────────
    const onSelectFocus = () => { if (!input.disabled) input.focus({ preventScroll: true }); };
    const onSelectInvalid = (e) => {
        if (input.disabled) return;
        // The hidden select stays quiet; the search box (also invalid, see
        // syncValidity) is the control the browser focuses and reports on.
        e.preventDefault();
        syncValidity();
    };
    select.addEventListener('focus', onSelectFocus);
    select.addEventListener('invalid', onSelectInvalid);
    select.addEventListener('change', changed);
    select.addEventListener('input', changed);
    select.addEventListener('options-changed', schedule);
    select.addEventListener('options:change', schedule);

    // Scripts that assign select.value / selectedIndex fire no event: hook the setters.
    [['value', SELECT_VALUE], ['selectedIndex', SELECT_INDEX]].forEach(([prop, desc]) => {
        if (!desc || !desc.get || !desc.set) return;
        Object.defineProperty(select, prop, {
            configurable: true,
            enumerable: desc.enumerable,
            get() { return desc.get.call(this); },
            set(v) { desc.set.call(this, v); changed(); },
        });
    });

    const observer = new MutationObserver(schedule);
    observer.observe(select, {
        childList: true,
        subtree: true,
        characterData: true,
        attributes: true,
        attributeFilter: [
            'disabled', 'hidden', 'label', 'value', 'selected', 'class', 'style', 'required', 'form',
            'title', 'aria-invalid', 'aria-describedby', 'aria-label', 'aria-labelledby', 'data-placeholder',
        ],
    });

    function destroy() {
        close();
        observer.disconnect();
        clearTimeout(announceTimer);
        delete select.value;
        delete select.selectedIndex;
        select.removeEventListener('focus', onSelectFocus);
        select.removeEventListener('invalid', onSelectInvalid);
        select.removeEventListener('change', changed);
        select.removeEventListener('input', changed);
        select.removeEventListener('options-changed', schedule);
        select.removeEventListener('options:change', schedule);
        const focused = document.activeElement === input;
        wrap.remove();
        restoreNative(select);
        instances.delete(select);
        if (focused && select.isConnected) select.focus({ preventScroll: true });
    }

    const api = { select, wrap, input, refresh, open, close, destroy };
    instances.set(select, api);
    refresh();
    if (document.activeElement === select) input.focus({ preventScroll: true });
    return api;
}

function restoreNative(select) {
    const tabindex = select.dataset.selectSearchTabindex;
    delete select.dataset.selectSearchTabindex;
    if (tabindex) select.setAttribute('tabindex', tabindex);
    else select.removeAttribute('tabindex');
    select.removeAttribute('aria-hidden');
    select.classList.remove('select-search-native');
}

// ─────────────────────────────────────────────────────────────────────────────
// Discovery: current markup, markup added later, short lists that grow
// ─────────────────────────────────────────────────────────────────────────────
function unwatch(select) {
    const observer = watching.get(select);
    if (observer) {
        observer.disconnect();
        watching.delete(select);
    }
}

function consider(select) {
    if (instances.has(select) || !select.isConnected || !eligible(select)) return;
    if (wanted(select)) {
        unwatch(select);
        enhance(select);
        return;
    }
    if (watching.has(select)) return;
    const observer = new MutationObserver(() => {
        if (!select.isConnected || !eligible(select) || !wanted(select)) return;
        unwatch(select);
        enhance(select);
    });
    observer.observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'data-search'] });
    watching.set(select, observer);
}

function selectsIn(node) {
    if (node.tagName === 'SELECT') return [node];
    return node.getElementsByTagName ? Array.from(node.getElementsByTagName('select')) : [];
}

function adopt(node) {
    // Cloned markup (cloneNode / innerHTML copies) carries a dead wrapper and a
    // hidden select: drop the copy and enhance the select again.
    const copies = node.matches('[data-select-search]') ? [node] : node.querySelectorAll('[data-select-search]');
    copies.forEach((copy) => { if (!wrappers.has(copy)) copy.remove(); });
    selectsIn(node).forEach((select) => {
        const instance = instances.get(select);
        if (instance) {
            if (select.nextElementSibling !== instance.wrap) select.after(instance.wrap);
            instance.refresh();
            return;
        }
        if (select.classList.contains('select-search-native')) restoreNative(select);
        consider(select);
    });
}

function release(node) {
    selectsIn(node).forEach((select) => {
        if (select.isConnected) return;   // moved, not removed
        unwatch(select);
        instances.get(select)?.destroy();
    });
}

function mountAll(scope = document) {
    scope.querySelectorAll('select').forEach(consider);
}

function start() {
    mountAll();
    new MutationObserver((records) => {
        for (const record of records) {
            if (record.target instanceof Element && record.target.closest('.select-search-menu')) continue;
            record.addedNodes.forEach((node) => { if (node.nodeType === 1) adopt(node); });
            record.removedNodes.forEach((node) => { if (node.nodeType === 1) release(node); });
        }
    }).observe(document.body, { childList: true, subtree: true });

    // Just before a submit button validates its form, catch up on any change a
    // script made without an event (option.selected = true on an existing option).
    document.addEventListener('click', (e) => {
        const button = e.target instanceof Element ? e.target.closest('button, input[type="submit"], input[type="image"]') : null;
        if (!button || !button.form || (button.type !== 'submit' && button.type !== 'image')) return;
        Array.from(button.form.elements).forEach((el) => instances.get(el)?.refresh());
    }, true);

    // form.reset() restores the selects without events.
    document.addEventListener('reset', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        window.setTimeout(() => {
            Array.from(form.elements).forEach((el) => instances.get(el)?.refresh());
        }, 0);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}

window.selectSearch = {
    enhance: (select) => (eligible(select) ? enhance(select) : null),
    refresh: (select) => instances.get(select)?.refresh(),
    destroy: (select) => instances.get(select)?.destroy(),
    mountAll,
};
