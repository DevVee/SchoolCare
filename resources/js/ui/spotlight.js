/**
 * Spotlight: one box to find a patient, jump to a page, or ask the assistant
 * (layouts/partials/topbar.blade.php, styles in components/_spotlight.scss).
 * Opens with the topbar search button, Ctrl/Cmd+K anywhere, or "/" when not typing.
 *   - Pages: the sidebar's links, so it only offers what this user may open.
 *   - Patients: GET patients.lookup (same endpoint as the Log a visit picker).
 *   - Ask: hands the text to the assistant page (?q=), which sends it.
 * Up/Down move, Enter opens, Esc or a click outside closes. Text is set with
 * textContent only, never HTML.
 */
const dlg = document.querySelector('[data-spotlight]');

function isTyping(el) {
    return el instanceof HTMLElement && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));
}

function icon(name) {
    const i = document.createElement('i');
    i.className = `bi ${name} c-icon`;
    i.setAttribute('aria-hidden', 'true');
    return i;
}

if (dlg && typeof dlg.showModal === 'function') {
    const input = dlg.querySelector('[data-spotlight-input]');
    const list = dlg.querySelector('[data-spotlight-list]');
    const cfg = dlg.dataset;
    const aiName = cfg.aiName || 'the assistant';

    // Pages come from the sidebar the server already filtered by permission.
    const pages = Array.from(document.querySelectorAll('#appSidebar a.sidebar-link')).map((a) => {
        const glyph = a.querySelector('.sidebar-link-icon .bi, .sidebar-link-icon i');
        const match = glyph ? glyph.className.match(/bi-[\w-]+/) : null;
        return {
            label: (a.querySelector('.sidebar-link-label') || a).textContent.trim(),
            href: a.href,
            icon: match ? match[0] : 'bi-arrow-right',
        };
    }).filter((p, i, all) => p.label && all.findIndex((q) => q.href === p.href) === i);

    let items = [];
    let active = 0;
    let patients = null; // null: not searched, [] no match
    let more = false;
    let loading = false;
    let timer = 0;
    let controller = null;

    function section(title) {
        const h = document.createElement('p');
        h.className = 'spotlight-section';
        h.textContent = title;
        list.appendChild(h);
    }

    function note(text) {
        const p = document.createElement('p');
        p.className = 'spotlight-note';
        p.textContent = text;
        list.appendChild(p);
    }

    function row(item) {
        const a = document.createElement('a');
        a.className = 'spotlight-item';
        a.href = item.href;
        a.id = `spotlight-opt-${items.length}`;
        a.setAttribute('role', 'option');
        a.dataset.index = String(items.length);
        a.appendChild(icon(item.icon));
        const text = document.createElement('span');
        text.className = 'spotlight-text';
        const label = document.createElement('span');
        label.className = 'spotlight-label';
        label.textContent = item.label;
        text.appendChild(label);
        if (item.detail) {
            const detail = document.createElement('span');
            detail.className = 'spotlight-detail';
            detail.textContent = item.detail;
            text.appendChild(detail);
        }
        a.appendChild(text);
        const enter = document.createElement('span');
        enter.className = 'spotlight-enter';
        enter.textContent = 'Enter';
        a.appendChild(enter);
        list.appendChild(a);
        items.push(item);
    }

    function render() {
        const q = input.value.trim();
        const needle = q.toLowerCase();
        list.textContent = '';
        items = [];

        if (q.length >= 2 && cfg.lookupUrl) {
            section('Patients');
            if (loading && patients === null) {
                note('Searching patients');
            } else if (patients && patients.length) {
                patients.forEach((p) => row({
                    label: p.label,
                    detail: [p.detail, p.meta].filter(Boolean).join(' · '),
                    href: cfg.patientUrl.replace('__ID__', encodeURIComponent(p.id)),
                    icon: 'bi-person',
                }));
                if (more && cfg.patientsUrl) {
                    row({ label: `See all patients matching "${q}"`, href: `${cfg.patientsUrl}?search=${encodeURIComponent(q)}`, icon: 'bi-people' });
                }
            } else if (patients) {
                note(`No patients match "${q}".`);
            }
        }

        const matches = (needle ? pages.filter((p) => p.label.toLowerCase().includes(needle)) : pages).slice(0, needle ? 6 : 7);
        if (matches.length) {
            section('Go to');
            matches.forEach((p) => row({ label: p.label, href: p.href, icon: p.icon }));
        }

        if (cfg.askUrl) {
            section(aiName);
            row(q
                ? { label: `Ask ${aiName}: "${q}"`, href: `${cfg.askUrl}?q=${encodeURIComponent(q)}`, icon: 'bi-stars' }
                : { label: `Ask ${aiName} anything`, href: cfg.askUrl, icon: 'bi-stars' });
        }

        if (!items.length) note('Nothing found.');
        setActive(Math.min(active, Math.max(0, items.length - 1)));
    }

    function setActive(i) {
        active = i;
        list.querySelectorAll('.spotlight-item').forEach((el) => {
            const on = Number(el.dataset.index) === i;
            el.classList.toggle('is-active', on);
            el.setAttribute('aria-selected', on ? 'true' : 'false');
            if (on) el.scrollIntoView({ block: 'nearest' });
        });
        if (items.length) input.setAttribute('aria-activedescendant', `spotlight-opt-${i}`);
        else input.removeAttribute('aria-activedescendant');
    }

    async function lookup(q) {
        if (controller) controller.abort();
        controller = new AbortController();
        loading = true;
        try {
            const res = await fetch(`${cfg.lookupUrl}?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            if (input.value.trim() !== q) return; // typed on since
            patients = (data.results || []).slice(0, 6);
            more = !!data.more || (data.results || []).length > 6;
        } catch (e) {
            if (e.name === 'AbortError') return;
            patients = [];
        } finally {
            loading = false;
        }
        render();
    }

    function open() {
        if (dlg.open) return;
        input.value = '';
        patients = null;
        active = 0;
        render();
        dlg.showModal();
        input.focus();
    }

    function close() {
        if (dlg.open) dlg.close();
    }

    input.addEventListener('input', () => {
        const q = input.value.trim();
        patients = null;
        active = 0;
        clearTimeout(timer);
        if (q.length >= 2 && cfg.lookupUrl) {
            loading = true;
            timer = setTimeout(() => lookup(q), 150);
        } else {
            loading = false;
        }
        render();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' && items.length) {
            e.preventDefault();
            setActive((active + 1) % items.length);
        } else if (e.key === 'ArrowUp' && items.length) {
            e.preventDefault();
            setActive((active - 1 + items.length) % items.length);
        } else if (e.key === 'Enter' && items[active]) {
            e.preventDefault();
            window.location.href = items[active].href;
        }
    });

    list.addEventListener('mousemove', (e) => {
        const el = e.target.closest('.spotlight-item');
        if (el && Number(el.dataset.index) !== active) setActive(Number(el.dataset.index));
    });

    // A click on the backdrop (the dialog itself, outside the panel) closes it.
    dlg.addEventListener('click', (e) => { if (e.target === dlg) close(); });

    document.querySelectorAll('[data-spotlight-open]').forEach((b) => b.addEventListener('click', open));

    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if (dlg.open) close(); else open();
            return;
        }
        if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && !dlg.open && !isTyping(e.target)
            && !document.querySelector('.modal.show, .offcanvas.show, .offcanvas-lg.show')) {
            e.preventDefault();
            open();
        }
    });
}
