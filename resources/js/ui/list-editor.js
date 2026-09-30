// ═════════════════════════════════════════════════════════════════════════════
// List editor: a friendly editor for "one item per line" settings.
//
//   <div data-list-editor data-mode="list|options" data-noun="choice" data-label="Visit reasons">
//       <textarea name="visit_reasons">Headache\nFever</textarea>
//   </div>
//
// The textarea keeps the stored format and is what the form submits:
//   list    : one item per line
//   options : "value | Label" per line. Staff only see and edit the label; new items get a
//             value made from the label (like Str::slug($label, '_')); existing values never change.
// Without JavaScript the plain textarea stays visible and still works.
//
// Category lists (academic levels per patient category):
//   <div data-category-lists data-categories='{"college":"College",...}'>
//       <textarea name="academic_levels_by_category">{json}</textarea>
//   </div>
//
// window.listEditor.mount(host, options) is available for custom editors.
// ═════════════════════════════════════════════════════════════════════════════

function slug(text) {
    return String(text)
        .normalize('NFKD').replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '') || 'item';
}

function el(tag, attrs, children) {
    const node = document.createElement(tag);
    Object.entries(attrs || {}).forEach(([k, v]) => {
        if (v === null || v === undefined || v === false) return;
        if (k === 'class') node.className = v;
        else if (k === 'text') node.textContent = v;
        else if (k === 'hidden') node.hidden = !!v;
        else node.setAttribute(k, v === true ? '' : String(v));
    });
    (children || []).forEach((c) => c && node.appendChild(c));
    return node;
}

function iconButton(icon, label, extraClass) {
    return el('button', { type: 'button', class: 'btn btn-ghost btn-sm btn-icon ' + (extraClass || ''), 'aria-label': label, title: label }, [
        el('i', { class: 'bi bi-' + icon, 'aria-hidden': 'true' }),
    ]);
}

let uid = 0;

/**
 * Mount a list editor UI in `host`.
 * options: { items: [{value, label}], mode: 'list'|'options', noun, label, onChange(items) }
 * Returns { set(items), get() }.
 */
function mount(host, options) {
    const mode = options.mode === 'options' ? 'options' : 'list';
    const noun = options.noun || 'item';
    const nouns = options.nouns || noun + 's';
    const title = options.label || 'List';
    const id = 'le-' + (++uid);
    let items = (options.items || []).map((i) => ({ value: i.value ?? null, label: String(i.label ?? '') }));
    let removed = null;

    host.classList.add('list-editor-ui');
    host.innerHTML = '';

    const list = el('ol', { class: 'list-editor-items', 'aria-label': title });
    const newInput = el('input', {
        type: 'text', class: 'form-control', id: id + '-new', maxlength: 150,
        placeholder: 'Add a ' + noun, 'aria-label': 'New ' + noun + ' for ' + title, autocomplete: 'off',
    });
    const addBtn = el('button', { type: 'button', class: 'btn btn-secondary' }, [
        el('i', { class: 'bi bi-plus-lg', 'aria-hidden': 'true' }), document.createTextNode(' Add'),
    ]);
    const message = el('p', { class: 'list-editor-message', role: 'status', 'aria-live': 'polite' });
    const count = el('span', { class: 'list-editor-count' });
    const undoBtn = el('button', { type: 'button', class: 'btn btn-link btn-sm list-editor-undo', hidden: true });

    host.append(
        list,
        el('div', { class: 'list-editor-add' }, [newInput, addBtn]),
        el('div', { class: 'list-editor-foot' }, [count, undoBtn]),
        message,
    );

    function say(text, isError) {
        message.textContent = text || '';
        message.classList.toggle('is-error', !!isError);
    }

    function isDuplicate(label, exceptIndex) {
        const key = label.trim().toLowerCase();
        return items.some((it, i) => i !== exceptIndex && it.label.trim().toLowerCase() === key);
    }

    function uniqueValue(label) {
        const base = slug(label);
        const used = new Set(items.map((i) => i.value));
        let v = base;
        let n = 2;
        while (used.has(v)) v = base + '_' + (n++);
        return v;
    }

    function changed() {
        count.textContent = items.length + ' ' + (items.length === 1 ? noun : nouns);
        if (options.onChange) options.onChange(items.map((i) => ({ ...i })));
    }

    function render(focus) {
        list.innerHTML = '';
        if (!items.length) {
            list.appendChild(el('li', { class: 'list-editor-empty', text: 'No ' + nouns + ' yet.' }));
        }
        items.forEach((item, index) => {
            const input = el('input', {
                type: 'text', class: 'form-control form-control-sm', value: item.label, maxlength: 150,
                'aria-label': title + ', ' + noun + ' ' + (index + 1) + ' of ' + items.length,
            });
            input.addEventListener('change', () => {
                const next = input.value.replace(/\|/g, '/').trim();
                if (!next) {
                    input.value = item.label;
                    say('A ' + noun + ' cannot be empty. Use Remove to delete it.', true);
                    return;
                }
                if (isDuplicate(next, index)) {
                    input.value = item.label;
                    say('"' + next + '" is already in the list.', true);
                    return;
                }
                item.label = next;
                input.value = next;
                say('');
                changed();
            });
            input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); input.blur(); } });

            const up = iconButton('arrow-up', 'Move ' + item.label + ' up');
            const down = iconButton('arrow-down', 'Move ' + item.label + ' down');
            const remove = iconButton('x-lg', 'Remove ' + item.label, 'list-editor-remove');
            up.disabled = index === 0;
            down.disabled = index === items.length - 1;
            up.addEventListener('click', () => move(index, -1, 'up'));
            down.addEventListener('click', () => move(index, 1, 'down'));
            remove.addEventListener('click', () => removeAt(index));

            list.appendChild(el('li', { class: 'list-editor-item', 'data-index': index }, [
                el('span', { class: 'list-editor-num', 'aria-hidden': 'true', text: String(index + 1) }),
                input,
                el('span', { class: 'list-editor-actions' }, [up, down, remove]),
            ]));
        });
        if (focus) focus();
    }

    function move(index, delta, which) {
        const to = index + delta;
        if (to < 0 || to >= items.length) return;
        const [it] = items.splice(index, 1);
        items.splice(to, 0, it);
        render(() => {
            const row = list.querySelector('[data-index="' + to + '"]');
            const btn = row && row.querySelectorAll('.list-editor-actions button')[which === 'up' ? 0 : 1];
            (btn && !btn.disabled ? btn : row && row.querySelector('input'))?.focus();
        });
        changed();
    }

    function removeAt(index) {
        const [it] = items.splice(index, 1);
        removed = { item: it, index };
        undoBtn.textContent = 'Undo: put back "' + it.label + '"';
        undoBtn.hidden = false;
        say('Removed "' + it.label + '".');
        render(() => {
            const row = list.querySelector('[data-index="' + Math.min(index, items.length - 1) + '"]');
            (row ? row.querySelector('input') : newInput).focus();
        });
        changed();
    }

    undoBtn.addEventListener('click', () => {
        if (!removed) return;
        if (isDuplicate(removed.item.label, -1)) {
            say('"' + removed.item.label + '" is already in the list.', true);
        } else {
            items.splice(Math.min(removed.index, items.length), 0, removed.item);
            say('Put back "' + removed.item.label + '".');
            changed();
        }
        removed = null;
        undoBtn.hidden = true;
        render();
    });

    function add() {
        const label = newInput.value.replace(/\|/g, '/').trim();
        if (!label) {
            say('Type a ' + noun + ' first.', true);
            newInput.focus();
            return;
        }
        if (isDuplicate(label, -1)) {
            say('"' + label + '" is already in the list.', true);
            newInput.select();
            return;
        }
        items.push({ value: mode === 'options' ? uniqueValue(label) : null, label });
        newInput.value = '';
        say('Added "' + label + '".');
        render();
        changed();
        newInput.focus();
    }
    addBtn.addEventListener('click', add);
    newInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); add(); } });

    render();
    count.textContent = items.length + ' ' + (items.length === 1 ? noun : nouns);

    return {
        get: () => items.map((i) => ({ ...i })),
        // Add whatever is typed in the "Add" box (used when the form is saved without pressing Add).
        flush() {
            const label = newInput.value.replace(/\|/g, '/').trim();
            if (label && !isDuplicate(label, -1)) {
                items.push({ value: mode === 'options' ? uniqueValue(label) : null, label });
                newInput.value = '';
                render();
                changed();
            }
        },
        set(next) {
            items = (next || []).map((i) => ({ value: i.value ?? null, label: String(i.label ?? '') }));
            removed = null;
            undoBtn.hidden = true;
            say('');
            render();
            count.textContent = items.length + ' ' + (items.length === 1 ? noun : nouns);
        },
    };
}

function parseText(text, mode) {
    return String(text || '').split(/\r\n|\r|\n/).map((l) => l.trim()).filter(Boolean).map((line) => {
        if (mode === 'options') {
            const i = line.indexOf('|');
            if (i !== -1) {
                const value = line.slice(0, i).trim();
                const label = line.slice(i + 1).trim() || value;
                return { value: value || slug(label), label };
            }
            return { value: slug(line), label: line };
        }
        return { value: null, label: line };
    });
}

function serialize(items, mode) {
    return items.map((i) => (mode === 'options' ? i.value + ' | ' + i.label : i.label)).join('\n');
}

function initTextarea(root) {
    const source = root.querySelector('textarea');
    if (!source) return;
    const mode = root.dataset.mode === 'options' ? 'options' : 'list';
    const host = el('div');
    root.appendChild(host);
    source.hidden = true;
    source.setAttribute('aria-hidden', 'true');
    source.tabIndex = -1;
    const api = mount(host, {
        mode,
        noun: root.dataset.noun,
        nouns: root.dataset.nouns,
        label: root.dataset.label,
        items: parseText(source.value, mode),
        onChange: (items) => { source.value = serialize(items, mode); },
    });
    source.form?.addEventListener('submit', () => api.flush());
}

function initCategoryLists(root) {
    const source = root.querySelector('textarea');
    if (!source) return;
    let categories = {};
    try { categories = JSON.parse(root.dataset.categories || '{}'); } catch (e) { categories = {}; }
    let data = {};
    try { data = JSON.parse(source.value || '{}'); } catch (e) { data = null; }
    if (!data || typeof data !== 'object' || Array.isArray(data)) {
        // Unreadable value: leave the plain text box so nothing is lost.
        return;
    }
    const fields = [
        ['levels', 'Year levels / grades', 'year level'],
        ['sections', 'Sections', 'section'],
        ['programs', 'Programs / strands', 'program'],
    ];
    // Categories stored but no longer offered stay selectable.
    Object.keys(data).forEach((k) => { if (!(k in categories)) categories[k] = k; });

    const selectId = 'cat-lists-' + (++uid);
    const select = el('select', { class: 'form-select', id: selectId });
    Object.entries(categories).forEach(([value, label]) => select.appendChild(el('option', { value, text: label })));
    const wrap = el('div', { class: 'category-lists' }, [
        el('div', { class: 'category-lists-pick' }, [
            el('label', { class: 'form-label', for: selectId, text: 'Patient category' }),
            select,
        ]),
    ]);
    const editors = {};
    const grid = el('div', { class: 'row g-3' });
    fields.forEach(([key, label, noun]) => {
        const col = el('div', { class: 'col-12 col-xl-4' });
        const heading = el('p', { class: 'category-lists-title', text: label });
        const note = el('p', { class: 'category-lists-note' });
        const naId = selectId + '-' + key + '-na';
        const na = el('input', { type: 'checkbox', class: 'form-check-input', id: naId });
        const naWrap = el('div', { class: 'form-check c-check category-lists-na' }, [
            na, el('label', { class: 'form-check-label', for: naId, text: 'Not used for this category' }),
        ]);
        const host = el('div');
        col.append(heading, note, naWrap, host);
        grid.appendChild(col);
        const store = (list) => {
            const cat = select.value;
            data[cat] = data[cat] || {};
            if (list === null) delete data[cat][key];
            else data[cat][key] = list;
            if (!Object.keys(data[cat]).length) delete data[cat];
            write();
            notes();
        };
        editors[key] = {
            note, na, host,
            api: mount(host, {
                mode: 'list', noun, label, items: [],
                onChange: (items) => store(items.length ? items.map((i) => i.label) : null),
            }),
        };
        na.addEventListener('change', () => {
            editors[key].api.set([]);
            host.hidden = na.checked;
            store(na.checked ? [] : null);
        });
    });
    wrap.appendChild(el('p', { class: 'category-lists-help', text: 'Leave a list empty to use the general list above for this category.' }));
    wrap.appendChild(grid);

    function write() { source.value = JSON.stringify(data, null, 2); }
    function notes() {
        const cat = data[select.value] || {};
        fields.forEach(([key]) => {
            const has = Array.isArray(cat[key]);
            editors[key].note.textContent = has
                ? (cat[key].length ? 'Only these choices for this category.' : 'Does not apply to this category.')
                : 'Using the general list.';
        });
    }
    function load() {
        const cat = data[select.value] || {};
        fields.forEach(([key]) => {
            const list = Array.isArray(cat[key]) ? cat[key] : [];
            const notUsed = Array.isArray(cat[key]) && list.length === 0;
            editors[key].na.checked = notUsed;
            editors[key].host.hidden = notUsed;
            editors[key].api.set(list.map((label) => ({ value: null, label: String(label) })));
        });
        notes();
    }
    select.addEventListener('change', load);
    source.form?.addEventListener('submit', () => fields.forEach(([key]) => editors[key].api.flush()));

    source.hidden = true;
    source.setAttribute('aria-hidden', 'true');
    source.tabIndex = -1;
    root.appendChild(wrap);
    load();
}

function init(scope) {
    const base = scope || document;
    base.querySelectorAll('[data-list-editor]:not([data-le-ready])').forEach((root) => {
        root.setAttribute('data-le-ready', '');
        initTextarea(root);
    });
    base.querySelectorAll('[data-category-lists]:not([data-le-ready])').forEach((root) => {
        root.setAttribute('data-le-ready', '');
        initCategoryLists(root);
    });
}

window.listEditor = { init, mount, parseText, serialize };

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init());
} else {
    init();
}
