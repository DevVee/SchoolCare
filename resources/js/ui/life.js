/**
 * Life: the motion layer (styles in resources/scss/components/_life.scss).
 *   - Reveal: cards and stat tiles fade and rise into place as they scroll in,
 *     staggered, once. The hidden state is gated by <html class="js-reveal">
 *     (set in the layout <head>), so without this script nothing stays hidden.
 *   - Count up: stat tile numbers count from 0 as their tile arrives.
 *   - Spotlight: a soft light follows the pointer across link cards.
 *   - Words: [data-words] headlines rise word by word out of a mask.
 * Reduced motion: reveals are short cross-fades, numbers and words just appear.
 */
const root = document.documentElement;
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const EASE = 'cubic-bezier(.22, 1, .36, 1)';

// Keep in sync with the reveal selector in _life.scss.
const REVEAL = '.app-content :is(.stat-tile, .card:not(.card .card, .modal .card))';

// ─── Count up ────────────────────────────────────────────────────────────────
function countUp(el) {
    const text = el.textContent.trim();
    if (!/^\d{1,3}(,\d{3})*$|^\d+$/.test(text)) return; // whole numbers only
    const target = Number(text.replace(/,/g, ''));
    if (target < 2 || reduce) return;
    const fmt = new Intl.NumberFormat('en-US');
    const duration = Math.min(1200, 500 + target * 4);
    const start = performance.now();
    const tick = (now) => {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - t, 3); // ease-out cubic: fast start, gentle landing
        el.textContent = fmt.format(Math.round(target * eased));
        if (t < 1) requestAnimationFrame(tick);
        else el.textContent = text;
    };
    el.textContent = '0';
    requestAnimationFrame(tick);
}

// ─── Reveal ──────────────────────────────────────────────────────────────────
function reveal(el, index) {
    el.classList.add('is-in');
    const delay = Math.min(index, 6) * 60;
    const frames = reduce
        ? [{ opacity: 0 }, { opacity: 1 }]
        : [{ opacity: 0, transform: 'translateY(14px)' }, { opacity: 1, transform: 'none' }];
    // fill: backwards holds the first frame during the stagger delay and leaves no
    // trace once done, so hover lifts and presses keep their own transitions.
    el.animate(frames, { duration: reduce ? 200 : 700, delay, easing: EASE, fill: 'backwards' });
    const value = el.classList.contains('stat-tile') ? el.querySelector('.stat-value') : null;
    if (value) setTimeout(() => countUp(value), delay + 80);
}

if (root.classList.contains('js-reveal')) {
    const items = Array.from(document.querySelectorAll(REVEAL));
    if (!('IntersectionObserver' in window) || !items.length) {
        items.forEach((el) => el.classList.add('is-in'));
    } else {
        const io = new IntersectionObserver((entries) => {
            const arriving = entries.filter((e) => e.isIntersecting).map((e) => e.target);
            // Stagger in reading order: top to bottom, then left to right
            arriving.sort((a, b) => {
                const ra = a.getBoundingClientRect();
                const rb = b.getBoundingClientRect();
                return Math.round(ra.top - rb.top) || ra.left - rb.left;
            });
            arriving.forEach((el, i) => {
                io.unobserve(el);
                reveal(el, i);
            });
        }, { rootMargin: '0px 0px -6% 0px', threshold: 0.01 });
        items.forEach((el) => io.observe(el));
    }

    // Cards added later by scripts (AJAX panels, editors) show at once: the
    // observer runs before the next paint, so they never flash or wait hidden.
    const content = document.querySelector('.app-content');
    if (content && 'MutationObserver' in window) {
        new MutationObserver((records) => {
            records.forEach((r) => r.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;
                if (node.matches(REVEAL)) node.classList.add('is-in');
                node.querySelectorAll(REVEAL).forEach((el) => el.classList.add('is-in'));
            }));
        }).observe(content, { childList: true, subtree: true });
    }
}

// ─── Spotlight ───────────────────────────────────────────────────────────────
// One delegated listener; the light position is two custom properties.
if (window.matchMedia('(hover: hover)').matches) {
    let frame = 0;
    let last = null;
    document.addEventListener('pointermove', (e) => {
        last = e;
        if (frame) return;
        frame = requestAnimationFrame(() => {
            frame = 0;
            const host = last.target instanceof Element
                ? last.target.closest('a.stat-tile, .card-interactive, .c-spot')
                : null;
            if (!host) return;
            const r = host.getBoundingClientRect();
            host.style.setProperty('--spot-x', `${last.clientX - r.left}px`);
            host.style.setProperty('--spot-y', `${last.clientY - r.top}px`);
        });
    }, { passive: true });
}

// ─── Words rising out of a mask ──────────────────────────────────────────────
if (!reduce) {
    document.querySelectorAll('[data-words]').forEach((el) => {
        if (el.children.length) return; // plain text headlines only
        const words = el.textContent.trim().split(/\s+/);
        el.setAttribute('aria-label', words.join(' '));
        el.textContent = '';
        words.forEach((word, i) => {
            const mask = document.createElement('span');
            mask.className = 'c-word';
            mask.setAttribute('aria-hidden', 'true');
            const inner = document.createElement('span');
            inner.textContent = word;
            inner.style.setProperty('--i', String(i));
            mask.appendChild(inner);
            el.appendChild(mask);
            if (i < words.length - 1) el.appendChild(document.createTextNode(' '));
        });
    });
}
