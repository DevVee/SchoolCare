// ═════════════════════════════════════════════════════════════════════════════
// Public website (landing/*). Loaded after app.js (Bootstrap is on window).
//   1. Header scroll edge: [data-lp-header] gets .is-scrolled once content
//      scrolls under it (the divider only shows then).
//   2. Current section: the header link of the section in view gets .is-active.
//   3. Mobile menu: tapping a link in the bottom sheet closes it first.
//   4. Headline: the words of [data-lp-words] rise out of a mask, one after
//      another. <html class="lp-js"> (set in the head) keeps the headline
//      unseen until this runs; a CSS safety keyframe shows it if it never does.
//   5. Scroll reveal: .lp-reveal blocks BELOW the fold fade and rise into place
//      as they scroll in, staggered, once. Nothing above the fold is hidden;
//      under reduced motion they only fade.
//   6. Spotlight: a soft brand light follows the pointer across .lp-spot cards.
//   7. Steps: the line joining the numbers fills as the section scrolls past.
// Only transform and opacity animate.
// ═════════════════════════════════════════════════════════════════════════════

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// 1. Scroll edge ----------------------------------------------------------------
const header = document.querySelector('[data-lp-header]');
if (header) {
    let ticking = false;
    // The header sticks once the utility bar above it has scrolled away.
    const utility = document.querySelector('.lp-utility');
    const update = () => {
        header.classList.toggle('is-scrolled', window.scrollY > (utility ? utility.offsetHeight : 0) + 2);
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

// 4. Headline words rise out of a mask ---------------------------------------------------
document.querySelectorAll('[data-lp-words]').forEach((title) => {
    const text = title.textContent.trim();
    if (!reduceMotion && text !== '') {
        const words = text.split(/\s+/);
        title.textContent = '';
        words.forEach((word, i) => {
            const mask = document.createElement('span');
            mask.className = 'lp-word';
            const inner = document.createElement('span');
            inner.style.setProperty('--i', String(i));
            inner.textContent = word;
            mask.appendChild(inner);
            title.appendChild(mask);
            if (i < words.length - 1) title.appendChild(document.createTextNode(' '));
        });
    }
    title.classList.add('is-split');
});

// 5. Scroll reveal ---------------------------------------------------------------------------
const blocks = Array.from(document.querySelectorAll('.lp-reveal'));
if (blocks.length && 'IntersectionObserver' in window && !location.hash) {
    const fold = window.innerHeight;
    const done = (el) => {
        el.classList.remove('is-revealing');
        el.style.removeProperty('--lp-delay');
    };
    const reveal = new IntersectionObserver((entries) => {
        // Blocks that arrive together (a row of cards) follow each other in reading order.
        const arriving = entries
            .filter((e) => e.isIntersecting)
            .map((e) => e.target)
            .sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1));
        arriving.forEach((el, i) => {
            reveal.unobserve(el);
            const delay = Math.min(i, 5) * 80;
            el.style.setProperty('--lp-delay', `${delay}ms`);
            el.classList.add('is-revealing');
            requestAnimationFrame(() => el.classList.remove('is-pending'));
            // Hand the card back its own hover transitions once it has landed.
            window.setTimeout(() => done(el), 1000 + delay);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    blocks.forEach((el) => {
        if (el.getBoundingClientRect().top < fold) return; // already on screen: never hide it
        el.classList.add('is-pending');
        reveal.observe(el);
    });
}

// 6. Pointer spotlight on link cards ---------------------------------------------------------
if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    document.querySelectorAll('.lp-spot').forEach((card) => {
        card.addEventListener('pointermove', (e) => {
            const r = card.getBoundingClientRect();
            card.style.setProperty('--spot-x', `${e.clientX - r.left}px`);
            card.style.setProperty('--spot-y', `${e.clientY - r.top}px`);
        }, { passive: true });
    });
}

// 7. Steps line fills as the section scrolls past ------------------------------------------
const steps = document.querySelector('[data-lp-steps].has-line');
if (steps) {
    if (reduceMotion) {
        steps.style.setProperty('--lp-fill', '1');
    } else {
        let ticking = false;
        const fill = () => {
            // 0 when the list's top reaches 85% of the viewport, 1 when its bottom reaches 55%.
            const r = steps.getBoundingClientRect();
            const vh = window.innerHeight;
            const p = (vh * 0.85 - r.top) / (vh * 0.3 + r.height);
            steps.style.setProperty('--lp-fill', Math.min(1, Math.max(0, p)).toFixed(3));
            ticking = false;
        };
        window.addEventListener('scroll', () => {
            if (!ticking) {
                ticking = true;
                requestAnimationFrame(fill);
            }
        }, { passive: true });
        window.addEventListener('resize', fill, { passive: true });
        fill();
    }
}
