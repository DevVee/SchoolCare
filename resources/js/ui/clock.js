/**
 * Live clock: [data-live-clock] (x-ui.hero) shows "Saturday, September 26, 2026 · 1:12:00 PM",
 * updating every second in the element's data-timezone. Pauses while the tab is hidden.
 */
const els = Array.from(document.querySelectorAll('[data-live-clock]'));

if (els.length) {
    const formatters = new Map();
    const fmt = (tz) => {
        if (!formatters.has(tz)) {
            let date;
            let time;
            try {
                date = new Intl.DateTimeFormat('en-US', { timeZone: tz || undefined, weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
                time = new Intl.DateTimeFormat('en-US', { timeZone: tz || undefined, hour: 'numeric', minute: '2-digit', second: '2-digit' });
            } catch {
                date = new Intl.DateTimeFormat('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
                time = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' });
            }
            formatters.set(tz, { date, time });
        }
        return formatters.get(tz);
    };

    const tick = () => {
        const now = new Date();
        els.forEach((el) => {
            const { date, time } = fmt(el.dataset.timezone || '');
            const target = el.querySelector('time') || el;
            target.textContent = `${date.format(now)} · ${time.format(now)}`;
            if (target.tagName === 'TIME') target.setAttribute('datetime', now.toISOString());
        });
    };

    let timer = null;
    const start = () => {
        if (timer) return;
        tick();
        timer = setInterval(tick, 1000);
    };
    const stop = () => {
        clearInterval(timer);
        timer = null;
    };

    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
    if (!document.hidden) start();
}
