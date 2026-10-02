/**
 * Charts: ApexCharts with the shared theme (ui_principles.md section 6).
 * ---------------------------------------------------------------------------
 * Every element with [data-chart] is initialised automatically. The attribute
 * holds a compact JSON config (x-ui.chart writes it for you):
 *
 *   {
 *     "type": "line|area|bar|horizontal-bar|stacked-bar|donut",
 *     "series": [{ "name": "Visits", "data": [4, 7, 3] }]    (donut: [12, 5, 3])
 *     "categories": ["Mon", "Tue", "Wed"],                   (donut: "labels")
 *     "height": 280,
 *     "yFormat": "integer|decimal|percent|currency",
 *     "currency": "PHP",
 *     "title": "Visits this week"                            (used for aria-label)
 *   }
 *
 * ApexCharts is loaded on demand (separate chunk) only on pages that have a chart.
 * API: window.charts.init(root?), window.charts.render(el, config), window.charts.destroy(el)
 */

// One colour theme: series are steps of the brand blue, alternating dark and light
// so neighbours stay distinct (fixed, never cycled; a 7th+ series folds into "Other").
// Read at runtime from --brand-* so a brand colour set in Settings recolours charts.
const PALETTE_STEPS = ['600', '800', '400', '900', '300', '200'];
const PALETTE = ['#2563EB', '#1E40AF', '#60A5FA', '#1E3A8A', '#93C5FD', '#BFDBFE'];
const MAX_SERIES = PALETTE.length;
const INK_MUTED = '#64748B';
const GRID = '#EEF2F7';
const FONT = 'Figtree, Inter, system-ui, -apple-system, "Segoe UI", sans-serif';

const instances = new WeakMap();
let apexPromise = null;

function loadApex() {
    apexPromise ??= import('./apex').then((m) => m.default || m);
    return apexPromise;
}

function reducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

function palette() {
    const css = getComputedStyle(document.documentElement);
    return PALETTE_STEPS.map((step, i) => {
        const v = css.getPropertyValue(`--brand-${step}`).trim();
        return /^#[0-9a-f]{6}$/i.test(v) ? v : PALETTE[i];
    });
}

function formatter(kind, currency = 'PHP') {
    const int = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });
    const dec = new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 });
    const money = (() => {
        try { return new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 0 }); }
        catch { return int; }
    })();
    return (val) => {
        if (val === null || val === undefined || Number.isNaN(Number(val))) return '';
        const n = Number(val);
        switch (kind) {
            case 'decimal': return dec.format(n);
            case 'percent': return `${dec.format(n)}%`;
            case 'currency': return money.format(n);
            default: return int.format(n);
        }
    };
}

/** Fold series beyond the palette into a single "Other" series (point-wise sum). */
function foldSeries(series) {
    if (!Array.isArray(series) || series.length <= MAX_SERIES) return series;
    const keep = series.slice(0, MAX_SERIES - 1);
    const rest = series.slice(MAX_SERIES - 1);
    const len = Math.max(...rest.map((s) => (s.data || []).length));
    const other = { name: 'Other', data: Array.from({ length: len }, (_, i) => rest.reduce((sum, s) => sum + (Number(s.data?.[i]) || 0), 0)) };
    return [...keep, other];
}

/** Donut: keep the 5 largest slices, fold the rest into "Other". */
function foldDonut(values, labels) {
    if (values.length <= MAX_SERIES) return { values, labels };
    const pairs = values.map((v, i) => [Number(v) || 0, labels[i] ?? `Item ${i + 1}`]).sort((a, b) => b[0] - a[0]);
    const top = pairs.slice(0, MAX_SERIES - 1);
    const otherTotal = pairs.slice(MAX_SERIES - 1).reduce((s, p) => s + p[0], 0);
    return { values: [...top.map((p) => p[0]), otherTotal], labels: [...top.map((p) => p[1]), 'Other'] };
}

/**
 * A category label for under a column: long free text ("Migraine With Aura,
 * Right-sided, Since Morning") keeps the part before the first comma and wraps
 * onto at most two short lines, the last one ending in "…" when cut. Short
 * labels are left alone. The tooltip and the data table keep the full text.
 */
function columnLabel(text, width = 14) {
    let label = String(text ?? '').trim();
    if (label.length <= width) return label;
    const head = label.split(',')[0].trim();
    if (head.length >= 3) label = head;
    const lines = [];
    let line = '';
    for (const word of label.split(/\s+/)) {
        const next = line ? `${line} ${word}` : word;
        if (next.length <= width || !line) {
            line = next;
        } else {
            lines.push(line);
            line = word;
        }
    }
    if (line) lines.push(line);
    if (lines.length <= 2) return lines.length === 1 ? lines[0] : lines;
    const second = lines[1].length > width - 1 ? lines[1].slice(0, width - 1) : lines[1];
    return [lines[0], `${second}…`];
}

/** Builds full ApexCharts options from the compact config. */
export function buildOptions(cfg) {
    const type = cfg.type || 'line';
    const fmt = formatter(cfg.yFormat, cfg.currency);
    const colors = palette();
    const animate = !reducedMotion();
    const isDonut = type === 'donut';
    const isBar = type === 'bar' || type === 'horizontal-bar' || type === 'stacked-bar';
    const horizontal = type === 'horizontal-bar';

    const base = {
        chart: {
            type: isDonut ? 'donut' : (isBar ? 'bar' : type),
            height: cfg.height || 280,
            fontFamily: FONT,
            foreColor: INK_MUTED,
            parentHeightOffset: 0,
            toolbar: { show: false },
            zoom: { enabled: false },
            selection: { enabled: false },
            stacked: type === 'stacked-bar',
            redrawOnParentResize: true,
            animations: {
                enabled: animate,
                speed: 300,
                animateGradually: { enabled: false },
                dynamicAnimation: { enabled: animate, speed: 300 },
            },
        },
        colors,
        dataLabels: { enabled: false },
        legend: {
            show: isDonut || (Array.isArray(cfg.series) && cfg.series.length > 1),
            position: 'bottom',
            horizontalAlign: 'left',
            fontFamily: FONT,
            fontSize: '13px',
            labels: { colors: '#475569' },
            markers: { size: 6, shape: 'square', strokeWidth: 0 },
            itemMargin: { horizontal: 12, vertical: 4 },
        },
        tooltip: {
            theme: 'light',
            shared: !isBar && !isDonut,
            intersect: false,
            followCursor: false,
            style: { fontFamily: FONT, fontSize: '13px' },
            y: { formatter: fmt },
            marker: { show: true },
        },
        states: {
            hover: { filter: { type: 'none' } },
            active: { filter: { type: 'none' } },
        },
        noData: { text: 'No data yet', style: { color: INK_MUTED, fontFamily: FONT, fontSize: '13px' } },
        responsive: [{
            breakpoint: 576,
            options: { legend: { fontSize: '12px', itemMargin: { horizontal: 8, vertical: 2 } } },
        }],
    };

    if (isDonut) {
        const folded = foldDonut((cfg.series || []).map(Number), cfg.labels || []);
        const total = folded.values.reduce((s, v) => s + v, 0);
        return {
            ...base,
            series: folded.values,
            labels: folded.labels,
            stroke: { width: 2, colors: ['#FFFFFF'] },
            plotOptions: {
                pie: {
                    expandOnClick: false,
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            name: { show: true, fontFamily: FONT, fontSize: '13px', color: INK_MUTED, offsetY: 20 },
                            value: { show: true, fontFamily: FONT, fontSize: '26px', fontWeight: 700, color: '#0F172A', offsetY: -12, formatter: (v) => fmt(v) },
                            total: { show: true, showAlways: true, label: cfg.totalLabel || 'Total', fontFamily: FONT, fontSize: '13px', color: INK_MUTED, formatter: () => fmt(total) },
                        },
                    },
                },
            },
        };
    }

    const series = foldSeries((cfg.series || []).map((s) => ({ name: s.name, data: (s.data || []).map((v) => (v === null ? null : Number(v))) })));
    const axisLabelStyle = { colors: INK_MUTED, fontSize: '12px', fontFamily: FONT };
    const categoryAxis = {
        categories: cfg.categories || [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        tooltip: { enabled: false },
        crosshairs: { show: !isBar, stroke: { color: '#CBD5E1', width: 1, dashArray: 0 } },
        labels: { style: axisLabelStyle, hideOverlappingLabels: true, trim: true, rotate: 0 },
    };
    const valueAxisLabels = { style: axisLabelStyle, formatter: fmt };

    const options = {
        ...base,
        series,
        stroke: isBar
            ? { show: true, width: 2, colors: ['transparent'] }   // 2px gap between bars / segments
            : { width: 2, curve: 'smooth', lineCap: 'round' },
        markers: { size: 0, strokeWidth: 2, strokeColors: '#FFFFFF', hover: { size: 5 } },
        fill: type === 'area'
            ? { type: 'solid', opacity: 0.12 }
            : { type: 'solid', opacity: 1 },
        grid: {
            borderColor: GRID,
            strokeDashArray: 0,
            xaxis: { lines: { show: horizontal } },
            yaxis: { lines: { show: !horizontal } },
            padding: { left: 4, right: 8, top: 0, bottom: 0 },
        },
        plotOptions: {
            bar: {
                horizontal,
                borderRadius: 4,
                borderRadiusApplication: 'end',
                borderRadiusWhenStacked: 'last',
                columnWidth: '55%',
                barHeight: '60%',
            },
        },
    };

    if (horizontal) {
        // Apex swaps axes for horizontal bars: values live on xaxis.
        options.xaxis = { ...categoryAxis, labels: { ...valueAxisLabels } };
        options.yaxis = { labels: { style: axisLabelStyle, maxWidth: 180 } };
    } else if (type === 'bar') {
        // Columns styled like the line charts (same grid and axes), rounded tops,
        // short two-line labels underneath and the full label in the tooltip.
        const full = cfg.categories || [];
        options.xaxis = {
            ...categoryAxis,
            categories: full.map((c) => columnLabel(c)),
            labels: { ...categoryAxis.labels, trim: false, hideOverlappingLabels: false },
        };
        options.yaxis = { labels: valueAxisLabels, min: 0, forceNiceScale: true, tickAmount: 4 };
        options.plotOptions.bar = { ...options.plotOptions.bar, borderRadius: 6, columnWidth: full.length > 8 ? '60%' : '42%' };
        options.tooltip = { ...options.tooltip, x: { formatter: (val, o) => full[o?.dataPointIndex] ?? val } };
    } else {
        options.xaxis = categoryAxis;
        options.yaxis = { labels: valueAxisLabels, min: isBar || type === 'area' ? 0 : undefined, forceNiceScale: true, tickAmount: 4 };
    }
    return options;
}

function parse(el) {
    try {
        return JSON.parse(el.getAttribute('data-chart') || '{}');
    } catch (e) {
        console.error('Invalid data-chart JSON', el, e);
        return null;
    }
}

export async function render(el, config = null) {
    const cfg = config || parse(el);
    if (!cfg) return null;
    const ApexCharts = await loadApex();
    destroy(el);
    const chart = new ApexCharts(el, buildOptions(cfg));
    instances.set(el, chart);
    el.setAttribute('data-chart-ready', '1');
    await chart.render();
    return chart;
}

export function destroy(el) {
    const existing = instances.get(el);
    if (existing) {
        existing.destroy();
        instances.delete(el);
    }
    el.removeAttribute('data-chart-ready');
}

export function init(root = document) {
    const els = root.querySelectorAll('[data-chart]:not([data-chart-ready])');
    if (!els.length) return Promise.resolve([]);
    return Promise.all(Array.from(els).map((el) => render(el)));
}

// Data-table toggle (x-ui.chart :table): swaps chart <-> table view
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-chart-toggle]');
    if (!btn) return;
    const wrap = btn.closest('.c-chart');
    if (!wrap) return;
    const showTable = btn.getAttribute('aria-pressed') !== 'true';
    btn.setAttribute('aria-pressed', showTable ? 'true' : 'false');
    btn.querySelector('[data-label]').textContent = showTable ? 'Show chart' : 'Show table';
    wrap.classList.toggle('is-table', showTable);
});

window.charts = { init, render, destroy, buildOptions };

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init());
} else {
    init();
}
