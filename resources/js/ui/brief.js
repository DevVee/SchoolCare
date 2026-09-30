/**
 * Coco's brief on the dashboard ([data-brief], dashboard/partials/brief.blade.php).
 * Fetches route('dashboard.brief') after the page shows, so the dashboard never
 * waits on the AI, then ticks the lines in one after another (ServiceCo brief).
 * Response: { lines: [{ text, href }], source: 'ai'|'rules', generated_at }.
 */
const box = document.querySelector('[data-brief]');

function icon(name) {
    const i = document.createElement('i');
    i.className = `bi bi-${name} c-icon`;
    i.setAttribute('aria-hidden', 'true');
    return i;
}

function render(list, lines) {
    list.textContent = '';
    lines.forEach((line, index) => {
        const li = document.createElement('li');
        const item = document.createElement(line.href ? 'a' : 'div');
        item.className = 'c-brief-line is-ticking';
        item.style.setProperty('--i', String(index));
        if (line.href) item.href = line.href;
        const text = document.createElement('span');
        text.textContent = line.text;
        item.appendChild(text);
        if (line.href) item.appendChild(icon('chevron-right'));
        li.appendChild(item);
        list.appendChild(li);
    });
}

if (box) {
    const list = box.querySelector('[data-brief-lines]');
    const meta = box.querySelector('[data-brief-meta]');

    fetch(box.dataset.briefUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
        .then((data) => {
            const lines = Array.isArray(data.lines) ? data.lines.filter((l) => l && l.text) : [];
            if (!lines.length) throw new Error('empty');
            render(list, lines);
            const at = data.generated_at ? new Date(data.generated_at) : new Date();
            const time = at.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            meta.textContent = `Updated ${time}`;
            if (data.source === 'ai') {
                const live = document.createElement('span');
                live.className = 'c-live';
                live.setAttribute('aria-hidden', 'true');
                meta.prepend(live);
            }
        })
        .catch(() => {
            list.textContent = '';
            meta.textContent = 'The brief is not available right now.';
        })
        .finally(() => list.setAttribute('aria-busy', 'false'));
}
