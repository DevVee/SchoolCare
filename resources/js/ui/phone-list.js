/**
 * Phones (< 576px): every table becomes a list, like ServiceCo's PhoneList.
 * ---------------------------------------------------------------------------
 * Each row is one line in a rounded white card: a bold title, one quiet line
 * under it, the key value or status pill on the right, and a chevron. A tap
 * opens one shared bottom sheet with every column as label / value lines
 * ("SheetFacts") and the row's actions as full-width buttons. The table itself
 * stays in the page (hidden on phones) and remains the source of truth: sheet
 * buttons click the real links, buttons and form buttons, so confirm dialogs,
 * CSRF forms and the double-submit guard all keep working.
 *
 * Built only while the phone media query matches; desktop DOM is untouched.
 *
 * Hints (all optional; put them on <th> / <x-ui.th>):
 *   data-phone-title   this column is the row title (default: td.cell-identity, else the first column);
 *                      inside a title cell it marks the element whose text is the title
 *   data-phone-sub     this column goes in the quiet line (repeatable; default: the title cell's
 *                      .identity-sub / .cell-sub, else the first one or two plain columns)
 *   data-phone-right   this column shows on the right (default: the first status pill, else a numeric column)
 *   data-phone-skip    leave this column out of the list and the sheet
 *   data-phone-actions this column holds the row's buttons (default: td.cell-actions, an x-ui.action-menu,
 *                      or an unlabelled last column of buttons)
 *   data-phone-link    (on a <tr> or <td>: an URL) the row's own page; default: tr[data-href] or the
 *                      title cell's first link
 * On the <table> or an ancestor:
 *   data-no-cards      keep the table (true matrices); it scrolls sideways inside its card
 *   data-phone-inline  show the details inside each row instead of a sheet (tables in modals do this)
 * Tables whose cells hold form fields (inputs, selects) stack as label / value cards instead.
 *
 * window.phoneList.refresh() rebuilds after a page script adds a whole new table.
 */
import { Modal } from 'bootstrap';

const PHONE = window.matchMedia('(max-width: 575.98px)');
const OPT_OUT = '[data-no-cards], .month-cal, .md-table, .spotlight, .coco-thread';
const FIELD = 'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select, textarea';
const NOISE = '.avatar, .visually-hidden, .sort-icon, script, template, .dropdown-menu, [data-bs-toggle="dropdown"]';

const built = new Map();   // table -> { list, observer }
let sheet = null;

// ─── Small helpers ──────────────────────────────────────────────────────────

const squash = (s) => (s || '').replace(/\s+/g, ' ').trim();

/** Visible text of a node: avatars, screen-reader-only bits and menus left out. */
function textOf(node) {
    if (!node) return '';
    const copy = node.cloneNode(true);
    copy.querySelectorAll(NOISE).forEach((n) => n.remove());
    return squash(copy.textContent);
}

/** A copy of a cell's content for the sheet (ids dropped, so nothing is duplicated). */
function contentOf(cell) {
    const frag = document.createDocumentFragment();
    cell.childNodes.forEach((n) => frag.appendChild(n.cloneNode(true)));
    frag.querySelectorAll?.('[id]').forEach((n) => n.removeAttribute('id'));
    frag.querySelectorAll?.('.dropdown, [data-bs-toggle="dropdown"]').forEach((n) => n.remove());
    frag.querySelectorAll?.('.cell-truncate').forEach((n) => n.classList.remove('cell-truncate'));
    return frag;
}

const icon = (name, cls = '') => {
    const i = document.createElement('i');
    i.className = `bi bi-${name} c-icon ${cls}`.trim();
    i.setAttribute('aria-hidden', 'true');
    return i;
};

const has = (el, sel) => Boolean(el && (el.matches(sel) || el.querySelector(sel)));

// ─── Reading a table ────────────────────────────────────────────────────────

/** Header labels, one per column (colspans expanded). Null when the header is a matrix. */
function readColumns(table) {
    const head = table.tHead;
    if (!head || head.rows.length !== 1) return null;
    const cols = [];
    for (const th of head.rows[0].cells) {
        let label = th.dataset.phoneLabel || textOf(th) || squash(th.textContent);
        for (let i = 0; i < Math.max(1, th.colSpan); i++) cols.push({ th, label });
    }
    return cols;
}

/** A body row's cells by column index (colspans expanded). */
function cellsOf(tr) {
    const out = [];
    for (const td of tr.cells) {
        for (let i = 0; i < Math.max(1, td.colSpan); i++) out.push(td);
    }
    return out;
}

const isActionsCell = (td) => Boolean(td) && (td.classList.contains('cell-actions') || td.hasAttribute('data-phone-actions')
    || has(td, '.action-menu') || (!textOf(td) && has(td, 'a[href], button')));
const isCheckCell = (td) => Boolean(td) && (td.classList.contains('cell-check') || (has(td, 'input[type="checkbox"]') && !textOf(td)));

/** Decide which column is the title, the quiet line and the right value. */
function plan(table, cols, rows) {
    const sample = rows[0] ? cellsOf(rows[0]) : [];
    const roles = cols.map((c, i) => {
        const td = sample[i];
        if (c.th.hasAttribute('data-phone-skip') || isCheckCell(td) || has(c.th, 'input[type="checkbox"]')) return 'skip';
        if (c.th.hasAttribute('data-phone-actions') || (td && isActionsCell(td))) return 'actions';
        // An unlabelled last column of buttons ("Edit", "Delete") is the row's actions too.
        if (!textOf(c.th) && td && has(td, 'a[href], button, form') && i === cols.length - 1) return 'actions';
        return 'data';
    });
    const dataIdx = roles.map((r, i) => (r === 'data' ? i : -1)).filter((i) => i >= 0);

    let title = cols.findIndex((c) => c.th.hasAttribute('data-phone-title'));
    if (title < 0) title = sample.findIndex((td, i) => roles[i] === 'data' && td.classList.contains('cell-identity'));
    if (title < 0) title = dataIdx[0] ?? -1;

    let right = cols.findIndex((c) => c.th.hasAttribute('data-phone-right'));
    if (right < 0) {
        right = dataIdx.find((i) => i !== title && sample[i] && has(sample[i], '.pill, .badge')) ?? -1;
    }
    if (right < 0) {
        right = [...dataIdx].reverse().find((i) => i !== title && sample[i]
            && (sample[i].classList.contains('cell-numeric') || cols[i].th.classList.contains('text-end'))) ?? -1;
    }

    let subs = cols.map((c, i) => (c.th.hasAttribute('data-phone-sub') ? i : -1)).filter((i) => i >= 0);
    if (!subs.length) {
        // Prefer columns the desktop table always shows (no priority-* class), then the rest.
        const plain = dataIdx.filter((i) => i !== title && i !== right);
        const always = plain.filter((i) => !/\bpriority-/.test(cols[i].th.className));
        subs = (always.length ? always : plain).slice(0, 2);
    }

    return { roles, title, right, subs };
}

/**
 * The row's own page: data-phone-link, tr[data-href], the row menu's "View" item (the record
 * itself; a title link may point elsewhere, e.g. an appointment's patient), or the title's link.
 */
function rowLink(tr, titleCell, actions) {
    const own = tr.dataset.phoneLink || tr.dataset.href || tr.querySelector('td[data-phone-link]')?.dataset.phoneLink;
    if (own) return own;
    const view = actions.find((a) => a.href && !a.el.target && (a.icon === 'eye' || /^(view|open)\b/i.test(a.label)));
    if (view) return view.href;
    const a = titleCell?.querySelector('a[href]:not([href^="#"]):not([href^="javascript"]):not([target="_blank"])');
    return a ? a.getAttribute('href') : null;
}

/** Clickable things in the actions cell(s), in order. */
function actionsOf(cells) {
    const out = [];
    cells.forEach((td) => td.querySelectorAll('a[href], button').forEach((el) => {
        if (el.matches('[data-bs-toggle="dropdown"], .dropdown-toggle')) return;
        if (el.closest('[hidden]')) return;
        const label = squash(el.textContent) || el.getAttribute('aria-label') || el.getAttribute('title') || '';
        if (!label) return;
        out.push({
            el,
            label,
            icon: el.querySelector('.bi, .c-icon')?.className.match(/bi-([\w-]+)/)?.[1] || null,
            href: el.tagName === 'A' ? el.getAttribute('href') : null,
            danger: el.matches('.dropdown-item-danger, .text-danger, .btn-danger, .btn-outline-danger, [class*="tone-danger"]'),
            disabled: el.matches(':disabled, .disabled, [aria-disabled="true"]'),
        });
    }));
    return out;
}

// ─── The shared sheet ───────────────────────────────────────────────────────

function ensureSheet() {
    if (sheet) return sheet;
    const el = document.createElement('div');
    el.className = 'modal fade phone-sheet';
    el.id = 'phoneSheet';
    el.tabIndex = -1;
    el.setAttribute('aria-hidden', 'true');
    el.setAttribute('aria-labelledby', 'phoneSheetTitle');
    el.innerHTML = `
      <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <div class="min-w-0">
              <h2 class="modal-title" id="phoneSheetTitle"></h2>
              <p class="modal-subtitle" data-sheet-sub></p>
            </div>
            <button type="button" class="btn-close-c" data-bs-dismiss="modal" aria-label="Close"><i class="bi bi-x-lg c-icon" aria-hidden="true"></i></button>
          </div>
          <div class="modal-body">
            <dl class="sheet-facts" data-sheet-facts></dl>
            <div class="sheet-actions" data-sheet-actions></div>
          </div>
        </div>
      </div>`;
    document.body.appendChild(el);
    sheet = { el, modal: Modal.getOrCreateInstance(el), after: null };
    el.addEventListener('hidden.bs.modal', () => {
        const run = sheet.after;
        sheet.after = null;
        if (run) run();
    });
    return sheet;
}

function factsList(facts) {
    const dl = document.createElement('dl');
    dl.className = 'sheet-facts';
    facts.forEach(({ label, cell }) => {
        const row = document.createElement('div');
        const dt = document.createElement('dt');
        const dd = document.createElement('dd');
        dt.textContent = label;
        dd.appendChild(contentOf(cell));
        row.append(dt, dd);
        dl.appendChild(row);
    });
    return dl;
}

/** A sheet button that runs the real control in the (hidden) table. */
function actionButton(action, primary) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `btn ${primary ? 'btn-primary' : 'btn-secondary'} sheet-action${action.danger ? ' sheet-action-danger' : ''}`;
    if (action.icon) btn.appendChild(icon(action.icon));
    btn.append(document.createTextNode(action.label));
    btn.disabled = action.disabled;
    btn.addEventListener('click', () => {
        const el = action.el;
        if (!el) {   // "Open": the row's own page
            window.location.assign(action.href);
            return;
        }
        // Plain links and new-tab links: follow straight away (keeps the user gesture for popups).
        const plainLink = el.tagName === 'A' && action.href && !action.href.startsWith('#')
            && !el.hasAttribute('data-confirm') && !el.hasAttribute('data-bs-toggle') && !el.hasAttribute('onclick');
        if (plainLink) {
            el.click();
            return;
        }
        // Everything else (forms, confirm dialogs, other modals): close the sheet first.
        sheet.after = () => el.click();
        sheet.modal.hide();
    });
    return btn;
}

function openSheet(row) {
    const s = ensureSheet();
    s.el.querySelector('#phoneSheetTitle').textContent = row.title || 'Details';
    const sub = s.el.querySelector('[data-sheet-sub]');
    sub.textContent = row.sub || '';
    sub.hidden = !row.sub;

    const facts = factsList(row.facts);
    facts.setAttribute('data-sheet-facts', '');
    facts.hidden = !row.facts.length;
    s.el.querySelector('[data-sheet-facts]').replaceWith(facts);

    const actions = s.el.querySelector('[data-sheet-actions]');
    actions.replaceChildren();
    let list = row.actions;
    if (row.link) {
        // The row's page first, as the main button ("View" if the menu already has it).
        const same = list.find((a) => a.href === row.link);
        list = same ? [same, ...list.filter((a) => a !== same)] : [{ el: null, label: 'Open', icon: 'arrow-right', href: row.link }, ...list];
    }
    list.forEach((a, i) => actions.appendChild(actionButton(a, i === 0 && Boolean(row.link))));
    actions.hidden = !list.length;
    s.modal.show();
}

// ─── Building the list ──────────────────────────────────────────────────────

function buildRow(tr, cols, p, inline) {
    const cells = cellsOf(tr);
    const titleCell = cells[p.title];
    const rowTitle = titleCell?.querySelector('[data-phone-title]') || titleCell;
    const title = textOf(rowTitle?.querySelector('.identity-title, .cell-title') || rowTitle) || '-';

    let sub = '';
    const ownSub = titleCell?.querySelector('.identity-sub, .cell-sub');
    const useOwnSub = Boolean(ownSub) && !cols.some((c) => c.th.hasAttribute('data-phone-sub'));
    if (useOwnSub) {
        sub = textOf(ownSub);
    } else {
        // A name + second line contributes just the name; a bare number gets its column name ("Visits 114")
        sub = p.subs.map((i) => {
            const t = textOf(cells[i]?.querySelector('.identity-title, .cell-title') || cells[i]);
            return t && /^[\d.,%\s+-]+$/.test(t) && cols[i].label ? `${cols[i].label} ${t}` : t;
        }).filter((t) => t && t !== '-').join(' · ');
    }

    let right = null;
    const rc = cells[p.right];
    if (rc) {
        const pill = rc.querySelector('.pill, .badge');
        right = pill ? pill.cloneNode(true) : (textOf(rc) && textOf(rc) !== '-' ? document.createTextNode(textOf(rc)) : null);
    }

    const facts = [];
    cols.forEach((c, i) => {
        if (i === p.title || p.roles[i] !== 'data' || !cells[i]) return;
        if (cols.slice(0, i).some((prev, j) => cells[j] === cells[i])) return;   // colspan duplicate
        const t = textOf(cells[i]);
        if (!t && !has(cells[i], 'img, .pill, .badge, .c-icon')) return;
        facts.push({ label: c.label, cell: cells[i] });
    });
    const actionCells = cells.filter((td, i) => p.roles[i] === 'actions' && td);
    const actions = actionsOf([...new Set(actionCells)]);
    const link = rowLink(tr, titleCell, actions);

    const li = document.createElement('li');
    // Everything already on the row (nothing more to see, nothing to do): no sheet.
    const shown = new Set([rc, ...(useOwnSub ? [] : p.subs.map((i) => cells[i]))]);
    const shownAll = facts.every((f) => shown.has(f.cell));
    const tappable = !inline && Boolean(actions.length || link || !shownAll);
    const directLink = link && !actions.length && shownAll;

    const body = document.createElement(tappable ? (directLink ? 'a' : 'button') : 'div');
    body.className = 'phone-row';
    if (directLink) body.href = link;
    else if (tappable) body.type = 'button';

    const main = document.createElement('span');
    main.className = 'phone-row-main';
    const t = document.createElement('span');
    t.className = 'phone-row-title';
    t.textContent = title;
    main.appendChild(t);
    if (sub) {
        const s = document.createElement('span');
        s.className = 'phone-row-sub';
        s.textContent = sub;
        main.appendChild(s);
    }
    body.appendChild(main);
    if (right) {
        const r = document.createElement('span');
        r.className = 'phone-row-right';
        r.appendChild(right);
        body.appendChild(r);
    }
    if (tappable) body.appendChild(icon('chevron-right', 'phone-row-chev'));
    if (tr.classList.contains('is-muted') || tr.classList.contains('text-muted') || tr.classList.contains('row-muted')) li.classList.add('is-muted');
    li.appendChild(body);

    if (inline && facts.length) li.appendChild(factsList(facts));
    if (tappable && !directLink) {
        body.addEventListener('click', () => openSheet({ title, sub, facts, actions, link }));
    }
    return li;
}

function build(table) {
    const cols = readColumns(table);
    if (!cols || !table.tBodies.length) return;
    const allRows = [...table.tBodies].flatMap((b) => [...b.rows]);
    const emptyRow = allRows.find((tr) => tr.classList.contains('table-empty-row') || tr.querySelector('.table-empty-cell'));
    const rows = allRows.filter((tr) => tr !== emptyRow && !tr.hidden && !tr.classList.contains('d-none') && tr.style.display !== 'none');
    const wrapper = table.closest('.table-responsive, .table-shell') || table;

    // Grids of checkboxes (permission matrices) keep their columns and scroll inside their card.
    const checkCols = new Set(rows.flatMap((tr) => cellsOf(tr).map((td, i) => (td.querySelector('input[type="checkbox"]') && i > 0 ? i : -1)).filter((i) => i >= 0)));
    if (checkCols.size > 1) return;

    // Editable tables: keep them as tables, stacked into label / value cards.
    if (rows.some((tr) => has(tr, FIELD) && !isActionsCell(tr.querySelector(FIELD)?.closest('td')))) {
        table.classList.add('table-phone-stack');
        allRows.forEach((tr) => cellsOf(tr).forEach((td, i) => {
            if (!td.dataset.label && cols[i]?.label) td.dataset.label = cols[i].label;
        }));
        wrapper.classList.add('has-phone-stack');
        built.set(table, { list: null, observer: null });
        return;
    }

    const inline = Boolean(table.closest('[data-phone-inline], .modal'));
    const p = plan(table, cols, rows);
    const wrap = document.createElement('div');
    wrap.className = 'phone-list-wrap';
    if (wrapper.closest('.card, .modal-body')) wrap.classList.add('is-flush');

    if (!rows.length && emptyRow) {
        const empty = document.createElement('div');
        empty.className = 'phone-list-empty';
        empty.appendChild(contentOf(emptyRow.querySelector('td') || emptyRow));
        wrap.appendChild(empty);
    } else {
        const ul = document.createElement('ul');
        ul.className = 'phone-list';
        const caption = table.caption ? squash(table.caption.textContent) : '';
        if (caption) ul.setAttribute('aria-label', caption);
        rows.forEach((tr) => {
            const cells = [...tr.cells];
            // A row spanning the whole table is a group heading
            if (cells.length === 1 && cells[0].colSpan >= cols.length) {
                const li = document.createElement('li');
                li.className = 'phone-list-heading';
                li.textContent = textOf(cells[0]);
                ul.appendChild(li);
                return;
            }
            ul.appendChild(buildRow(tr, cols, p, inline));
        });
        wrap.appendChild(ul);
    }

    wrapper.classList.add('has-phone-list');
    wrapper.after(wrap);

    // Page scripts that filter rows, sort or fill the body: rebuild to match.
    const observer = new MutationObserver(() => schedule(table));
    [...table.tBodies].forEach((b) => observer.observe(b, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'class', 'style'] }));
    built.set(table, { list: wrap, observer });
}

function teardown(table) {
    const entry = built.get(table);
    if (!entry) return;
    entry.observer?.disconnect();
    entry.list?.remove();
    const wrapper = table.closest('.table-responsive, .table-shell') || table;
    wrapper.classList.remove('has-phone-list', 'has-phone-stack');
    built.delete(table);
}

const pending = new Set();
function schedule(table) {
    if (!pending.size) {
        requestAnimationFrame(() => {
            pending.forEach((t) => { teardown(t); if (PHONE.matches && t.isConnected) build(t); });
            pending.clear();
        });
    }
    pending.add(table);
}

function tables() {
    return [...document.querySelectorAll('.app-main table, .modal table')]
        .filter((t) => !t.closest(OPT_OUT) && !t.closest('.phone-sheet'));
}

function refresh() {
    built.forEach((_, t) => teardown(t));
    if (!PHONE.matches) return;
    tables().forEach(build);
}

window.phoneList = { refresh };

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refresh);
} else {
    refresh();
}
PHONE.addEventListener('change', refresh);
