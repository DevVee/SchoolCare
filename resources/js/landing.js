// ═════════════════════════════════════════════════════════════════════════════
// Public website (landing/*): product page "/", clinic page "/clinic", privacy.
//   1. Header scroll edge: [data-lp-header] gets .is-scrolled once content
//      scrolls under it (the divider only shows then).
//   2. Clinic page: the section capsule in view gets .is-active.
//   3. Scroll reveal: .lp-reveal blocks BELOW the fold fade and rise into place
//      as they scroll in, staggered, once. Nothing above the fold is hidden;
//      under reduced motion they only fade.
//   4. Mini screens: .lp-live gets .is-live when it arrives, so its rows slide
//      in and [data-lp-count] numbers count up once. Skipped under reduced
//      motion; without this script they simply show their final state.
// ═════════════════════════════════════════════════════════════════════════════

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const canObserve = 'IntersectionObserver' in window;

// 1. Scroll edge ----------------------------------------------------------------
const header = document.querySelector('[data-lp-header]');
if (header) {
    let ticking = false;
    const update = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 2);
        ticking = false;
    };
    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });
    update();
}

// 2. Current section on the clinic page -------------------------------------------
const navLinks = Array.from(document.querySelectorAll('[data-lp-nav] a[href^="#"]'));
if (navLinks.length && canObserve) {
    const byId = new Map(navLinks.map((a) => [a.getAttribute('href').slice(1), a]));
    const visible = new Map();
    const spy = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) visible.set(e.target.id, e.intersectionRatio);
            else visible.delete(e.target.id);
        });
        let best = null;
        visible.forEach((ratio, id) => {
            if (best === null || ratio > visible.get(best)) best = id;
        });
        navLinks.forEach((a) => {
            const on = best !== null && byId.get(best) === a;
            a.classList.toggle('is-active', on);
            if (on) {
                a.setAttribute('aria-current', 'location');
                // Keep the active capsule in view in the scrollable row
                const row = a.parentElement;
                if (row && row.scrollWidth > row.clientWidth) {
                    row.scrollTo({ left: a.offsetLeft - 16, behavior: reduceMotion ? 'auto' : 'smooth' });
                }
            } else {
                a.removeAttribute('aria-current');
            }
        });
    }, { rootMargin: '-120px 0px -45% 0px', threshold: [0, 0.25, 0.5, 1] });
    byId.forEach((_, id) => {
        const section = document.getElementById(id);
        if (section) spy.observe(section);
    });
}

// 4 (used by 3). Bring a mini screen to life: rows animate in, numbers count up.
const countUp = (el) => {
    const to = parseInt(el.dataset.lpCount, 10);
    if (!to) return;
    const start = performance.now();
    const tick = (now) => {
        const p = Math.min(1, (now - start) / 900);
        el.textContent = String(Math.round(to * (1 - (1 - p) ** 3)));
        if (p < 1) requestAnimationFrame(tick);
    };
    el.textContent = '0';
    requestAnimationFrame(tick);
};
const bringToLife = (el) => {
    if (reduceMotion || el.classList.contains('is-live')) return;
    el.classList.add('is-live');
    el.querySelectorAll('[data-lp-count]').forEach(countUp);
};

// 3. Scroll reveal ---------------------------------------------------------------------------
const blocks = Array.from(document.querySelectorAll('.lp-reveal'));
const fold = window.innerHeight;
if (blocks.length && canObserve && !location.hash) {
    const reveal = new IntersectionObserver((entries) => {
        // Blocks that arrive together (a row of cards) follow each other in reading order.
        entries
            .filter((e) => e.isIntersecting)
            .map((e) => e.target)
            .sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1))
            .forEach((el, i) => {
                reveal.unobserve(el);
                const delay = Math.min(i, 5) * 80;
                el.style.setProperty('--lp-delay', `${delay}ms`);
                el.classList.add('is-revealing');
                requestAnimationFrame(() => el.classList.remove('is-pending'));
                if (el.classList.contains('lp-live')) window.setTimeout(() => bringToLife(el), delay);
                // Hand the card back its own hover transition once it has landed.
                window.setTimeout(() => {
                    el.classList.remove('is-revealing');
                    el.style.removeProperty('--lp-delay');
                }, 900 + delay);
            });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    blocks.forEach((el) => {
        if (el.getBoundingClientRect().top < fold) return; // already on screen: never hide it
        el.classList.add('is-pending');
        reveal.observe(el);
    });
}

// Mini screens that are not waiting for a reveal come alive when they are in view.
document.querySelectorAll('.lp-live').forEach((el) => {
    if (el.classList.contains('is-pending')) return;
    if (!canObserve) return;
    const io = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) {
            io.disconnect();
            bringToLife(el);
        }
    }, { threshold: 0.2 });
    io.observe(el);
});
