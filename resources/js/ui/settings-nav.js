/**
 * Settings panel beside the rail (layouts/partials/settings-nav.blade.php):
 * the search box filters the settings areas as you type. Matches the name,
 * the section and the description; Enter opens the first match, Esc clears.
 * Sections with no match hide; "All settings" hides while searching.
 */
const panel = document.querySelector('[data-settings-panel]');
const input = panel?.querySelector('[data-settings-search]');

if (panel && input) {
    const groups = Array.from(panel.querySelectorAll('[data-settings-group]'));
    const home = panel.querySelector('[data-settings-home]');
    const empty = panel.querySelector('[data-settings-empty]');

    const filter = () => {
        const words = input.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
        let shown = 0;
        groups.forEach((group) => {
            let inGroup = 0;
            group.querySelectorAll('[data-settings-item]').forEach((item) => {
                const text = item.getAttribute('data-search') || '';
                const match = words.every((w) => text.includes(w));
                item.hidden = !match;
                if (match) inGroup++;
            });
            group.hidden = inGroup === 0;
            shown += inGroup;
        });
        if (home) home.hidden = words.length > 0;
        if (empty) empty.hidden = shown > 0;
    };

    input.addEventListener('input', filter);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const first = panel.querySelector('[data-settings-item]:not([hidden])');
            if (first && input.value.trim() !== '') first.click();
        } else if (e.key === 'Escape' && input.value !== '') {
            e.preventDefault();
            input.value = '';
            filter();
        }
    });
    // Back/forward cache can restore a typed query: apply it.
    window.addEventListener('pageshow', filter);
    filter();
}
