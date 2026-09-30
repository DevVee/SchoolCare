/**
 * Lean ApexCharts build: the bare core plus only the chart types and features
 * x-ui.chart uses (line, area, bar, donut, legend, keyboard navigation).
 * Imported lazily by ./charts.js so pages without charts never download it.
 */
import ApexCharts from 'apexcharts/core';
import 'apexcharts/line';
import 'apexcharts/area';
import 'apexcharts/bar';
import 'apexcharts/donut';
import 'apexcharts/features/legend';
import 'apexcharts/features/keyboard';

export default ApexCharts;
