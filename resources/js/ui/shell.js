/**
 * App shell behaviour (layouts/app.blade.php):
 *   - desktop sidebar collapse to a 72px icon rail, remembered in localStorage
 *     (the <head> no-flash script applies it before first paint);
 *   - tooltips on rail items while collapsed;
 *   - (search lives in ./spotlight: Ctrl K or "/");
 *   - time-of-day greeting for #dashGreeting (dashboard hero).
 * Drawer (<lg) behaviour, Esc and focus trapping come from Bootstrap's
 * .offcanvas-lg on #appSidebar.
 */
import { Tooltip } from 'bootstrap';

const STORAGE_KEY = 'schoolcare.sidebar';
const root = document.documentElement;
const desktop = window.matchMedia('(min-width: 992px)');

// ─── Sidebar collapse ────────────────────────────────────────────────────────
const sidebar = document.getElementById('appSidebar');
const toggles = document.querySelectorAll('[data-sidebar-toggle]');
let tips = [];

function isCollapsed() {
    return root.classList.contains('sidebar-collapsed');
}

function syncToggles() {
    const collapsed = isCollapsed();
    toggles.forEach((btn) => {
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        btn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        btn.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    });
}

function syncTips() {
    const on = isCollapsed() && desktop.matches;
    tips.forEach((t) => {
        if (on) {
            t.enable();
        } else {
            t.hide();
            t.disable();
        }
    });
}

if (sidebar) {
    tips = Array.from(sidebar.querySelectorAll('[data-rail-tip]')).map((el) => {
        const tip = new Tooltip(el, {
            title: el.getAttribute('data-rail-tip'),
            placement: 'right',
            trigger: 'hover focus',
            container: 'body',
            customClass: 'rail-tooltip',
        });
        return tip;
    });

    toggles.forEach((btn) => {
        btn.addEventListener('click', () => {
            const collapsed = !isCollapsed();
            root.classList.toggle('sidebar-collapsed', collapsed);
            try {
                localStorage.setItem(STORAGE_KEY, collapsed ? 'collapsed' : 'expanded');
            } catch (e) {
                /* private mode: state just is not remembered */
            }
            syncToggles();
            syncTips();
        });
    });

    desktop.addEventListener('change', syncTips);
    syncToggles();
    syncTips();
}

// ─── Greeting (dashboard) ────────────────────────────────────────────────────
const greeting = document.getElementById('dashGreeting');
if (greeting) {
    const h = new Date().getHours();
    greeting.textContent = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
}
