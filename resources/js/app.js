import * as bootstrap from 'bootstrap';
import axios from 'axios';

// Expose Bootstrap globally: app.js below and inline page scripts call
// `bootstrap.Modal`, `bootstrap.Tooltip`, … (the ESM import creates no global).
window.bootstrap = bootstrap;

// ─── UI kit behaviour (see resources/views/components/ui/README.md) ─────────
import './ui/toast';     // window.toast(message, type) + session flash toasts
import './ui/confirm';   // [data-confirm] → global confirm modal; window.confirmDialog()
import './ui/filters';   // form[data-autosubmit] filter toolbars
import './ui/scroll-edge'; // .app-topbar divider appears once the page scrolls
import './ui/charts';    // [data-chart] → ApexCharts (loaded on demand); window.charts
import './ui/clock';     // [data-live-clock] (x-ui.hero), pauses when the tab is hidden
import './ui/shell';     // sidebar collapse rail, topbar search, greeting
import './ui/session';   // CSRF refresh + session keep-alive (signed-in layout only)
import './ui/password-toggle'; // [data-password-toggle] show / hide password
import './ui/list-editor'; // [data-list-editor] friendly editor for one-per-line settings
import './ui/combobox';  // [data-combobox] type-to-search pickers (x-ui.patient-picker)
import './ui/life';      // reveal on scroll, count up, pointer spotlight, word rise
import './ui/brief';     // dashboard: Coco's brief of what is happening today

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// ─── CSRF Token ───────────────────────────────────────────────────────────────
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

// ─── Global: Auto-dismiss alerts ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss success alerts after 4 seconds
    const autoDismissAlerts = document.querySelectorAll('.alert-auto-dismiss');
    autoDismissAlerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 4000);
    });

    // Sidebar drawer / collapse: ./ui/shell (Bootstrap offcanvas-lg).

    // [data-confirm] is handled by ./ui/confirm (branded modal instead of confirm()).

    // Initialize Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Initialize Bootstrap popovers
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
        new bootstrap.Popover(el);
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// Global double-submission guard
// ---------------------------------------------------------------------------
// On submit of any non-GET form, disables every submit button in that form and
// shows a spinner on the one that was clicked, so a double click cannot
// dispense stock twice, send two SMS messages, etc.
//   • Opt out per form with  <form data-no-guard>.
//   • Skips forms whose submit was already cancelled (confirm() / AJAX handlers
//     that call preventDefault) and forms that open in a new tab/window.
//   • Buttons are disabled on the next tick, after the browser has already
//     serialised the form, so the clicked button's name/value is still sent;
//     the form is also flagged so any further submit is cancelled.
//   • Buttons are restored on `pageshow` (back/forward cache) and after a
//     safety timeout in case the response never navigates (e.g. a download).
// Self-contained: no dependencies other than Bootstrap's spinner CSS classes.
// ═══════════════════════════════════════════════════════════════════════════
(function () {
    const GUARD_ATTR = 'data-submitting';
    const SAFETY_MS = 15000;

    function buttonsOf(form) {
        const inside = Array.from(form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]'));
        const outside = form.id
            ? Array.from(document.querySelectorAll(`[form="${CSS.escape(form.id)}"][type="submit"]`))
            : [];
        return inside.concat(outside);
    }

    function release(form) {
        if (!form.hasAttribute(GUARD_ATTR)) return;
        form.removeAttribute(GUARD_ATTR);
        buttonsOf(form).forEach((btn) => {
            if (btn.dataset.guardHtml !== undefined) {
                btn.innerHTML = btn.dataset.guardHtml;
                delete btn.dataset.guardHtml;
            }
            if (btn.dataset.guardDisabled === '0') btn.disabled = false;
            delete btn.dataset.guardDisabled;
        });
    }

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (e.defaultPrevented) return;
        if (form.hasAttribute('data-no-guard')) return;
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
        const target = (form.getAttribute('target') || '').toLowerCase();
        if (target && target !== '_self') return;

        // Block the second submit of the same form outright.
        if (form.hasAttribute(GUARD_ATTR)) {
            e.preventDefault();
            return;
        }

        const submitter = e.submitter || null;

        // Lock on the next tick: by then the browser has serialised the form
        // (so the clicked button's name/value is still sent) and any later
        // listener that cancelled the submit (AJAX forms) has run.
        setTimeout(function () {
            if (e.defaultPrevented) return;
            form.setAttribute(GUARD_ATTR, '1');
            buttonsOf(form).forEach((btn) => {
                btn.dataset.guardDisabled = btn.disabled ? '1' : '0';
                if (btn === submitter && btn.tagName === 'BUTTON') {
                    btn.dataset.guardHtml = btn.innerHTML;
                    btn.insertAdjacentHTML('afterbegin',
                        '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>');
                }
                btn.disabled = true;
            });
            setTimeout(() => release(form), SAFETY_MS);
        }, 0);
    });

    // Restore forms when the page is shown from the back/forward cache.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll(`form[${GUARD_ATTR}]`).forEach(release);
    });
})();
