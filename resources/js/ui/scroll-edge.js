/**
 * Scroll-edge divider for the translucent topbar: adds `.is-scrolled` to
 * `.app-topbar` once the page scrolls, so the divider only shows when content
 * actually passes under the bar. No-op on pages without `.app-topbar`.
 */
const bar = document.querySelector('.app-topbar');

if (bar) {
    let ticking = false;
    const update = () => {
        bar.classList.toggle('is-scrolled', window.scrollY > 2);
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
