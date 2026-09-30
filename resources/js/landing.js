// ═════════════════════════════════════════════════════════════════════════════
// Public website (landing/*). Loaded after app.js (Bootstrap is on window).
//   1. Header scroll edge: [data-lp-header] gets .is-scrolled once the page moves.
//   2. Current section: the header link of the section in view gets .is-active.
//   3. Mobile menu: tapping a link in the bottom sheet closes it first.
//   4. Scroll reveal: .lp-reveal blocks BELOW the fold fade in 12px, once.
//      Nothing is hidden without JS, above the fold, or under reduced motion.
// ═════════════════════════════════════════════════════════════════════════════

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// 1. Scroll edge ----------------------------------------------------------------
const header = document.querySelector('[data-lp-header]');
if (header) {
    let ticking = false;
    const update = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 40);
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

// 2. Current section in the header nav --------------------------------------------
const navLinks = Array.from(document.querySelectorAll('[data-lp-nav] a[href^="#"]'));
if (navLinks.length && 'IntersectionObserver' in window) {
    const byId = new Map(navLinks.map((a) => [a.getAttribute('href').slice(1), a]));
    const visible = new Map();
    const setActive = () => {
        let best = null;
        visible.forEach((ratio, id) => {
            if (best === null || ratio > visible.get(best)) best = id;
        });
        navLinks.forEach((a) => {
            const on = best !== null && byId.get(best) === a;
            a.classList.toggle('is-active', on);
            if (on) a.setAttribute('aria-current', 'location');
            else a.removeAttribute('aria-current');
        });
    };
    const spy = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) visible.set(e.target.id, e.intersectionRatio);
            else visible.delete(e.target.id);
        });
        setActive();
    }, { rootMargin: '-72px 0px -45% 0px', threshold: [0, 0.25, 0.5, 1] });
    byId.forEach((_, id) => {
        const section = document.getElementById(id);
        if (section) spy.observe(section);
    });
}

// 3. Bottom-sheet menu: close, then follow the in-page link -----------------------------
const sheet = document.getElementById('lpMenu');
if (sheet) {
    sheet.addEventListener('click', (e) => {
        const link = e.target.closest('a[href^="#"]');
        if (!link || !window.bootstrap) return;
        const target = document.querySelector(link.getAttribute('href'));
        if (!target) return;
        e.preventDefault();
        sheet.addEventListener('hidden.bs.offcanvas', () => {
            target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
            history.replaceState(null, '', link.getAttribute('href'));
        }, { once: true });
        window.bootstrap.Offcanvas.getOrCreateInstance(sheet).hide();
    });
}

// 4. Scroll reveal -------------------------------------------------------------------------
const blocks = Array.from(document.querySelectorAll('.lp-reveal'));
if (blocks.length && !reduceMotion && 'IntersectionObserver' in window && !location.hash) {
    const fold = window.innerHeight;
    const reveal = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (!e.isIntersecting) return;
            const el = e.target;
            el.classList.add('is-revealed');
            requestAnimationFrame(() => el.classList.remove('is-pending'));
            reveal.unobserve(el);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    blocks.forEach((el) => {
        if (el.getBoundingClientRect().top < fold) return; // already on screen: never hide it
        el.classList.add('is-pending');
        reveal.observe(el);
    });
}
