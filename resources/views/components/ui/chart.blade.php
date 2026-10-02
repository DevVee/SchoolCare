{{--
    x-ui.chart: ApexCharts chart with the shared theme + an accessible data table.
    resources/js/ui/charts.js initialises it (ApexCharts is bundled, loaded on demand).

    <x-ui.chart type="bar" title="Visits per day" subtitle="Last 7 days"
        :series="[['name' => 'Visits', 'data' => [12, 18, 9, 14, 20, 6, 3]]]"
        :categories="['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" />

    <x-ui.chart type="line" :series="[['name' => 'Visits', 'data' => $visits], ['name' => 'Consultations', 'data' => $consults]]"
        :categories="$months" y-format="integer" height="320" />

    <x-ui.chart type="donut" title="Visit outcome" :series="[42, 12, 5]" :labels="['Returned to class', 'Sent home', 'Referred']" />
    <x-ui.chart type="donut" :series="['Returned to class' => 42, 'Sent home' => 12]" />      (labels from keys)

    type:     line | area | bar | horizontal-bar | stacked-bar | donut
    series:   [['name' => ..., 'data' => [...]], ...]; a flat list of numbers is one unnamed series.
              donut: flat numbers (+ labels) or label => value.
    yFormat:  integer | decimal | percent | currency (currency code via `currency`, default PHP)
    table:    true = "Show table" toggle; false = table stays visually hidden (screen readers only)
    empty:    message shown (as x-ui.empty-state) when every value is zero or missing
    Colours:  fixed validated order; a single series is always the brand colour; 7+ series fold into "Other".
--}}
@props([
    'type' => 'line',
    'series' => [],
    'categories' => [],
    'labels' => [],
    'title' => null,
    'subtitle' => null,
    'height' => 280,
    'table' => true,
    'empty' => 'No data for this period.',
    'yFormat' => 'integer',
    'currency' => 'PHP',
    'totalLabel' => 'Total',
    'categoryLabel' => null,   // data table heading for the categories (default: Category / Period)
    'id' => null,
])
@php
    $isDonut = $type === 'donut';
    $seriesList = $series instanceof \Illuminate\Support\Collection ? $series->all() : (array) $series;
    $cats = $categories instanceof \Illuminate\Support\Collection ? $categories->all() : (array) $categories;
    $lbls = $labels instanceof \Illuminate\Support\Collection ? $labels->all() : (array) $labels;

    if ($isDonut) {
        if ($seriesList && ! array_is_list($seriesList)) {          // label => value
            $lbls = array_map('strval', array_keys($seriesList));
            $seriesList = array_values($seriesList);
        } elseif (isset($seriesList[0]) && is_array($seriesList[0])) {   // [['name','data']] given: use first
            $seriesList = $seriesList[0]['data'] ?? [];
        }
        $values = array_map(fn ($v) => is_numeric($v) ? $v + 0 : 0, array_values($seriesList));
        $normalizedSeries = $values;
        $allValues = $values;
    } else {
        if ($seriesList && ! is_array(reset($seriesList))) {       // flat numbers: one series
            $seriesList = [['name' => $title ?? 'Value', 'data' => $seriesList]];
        }
        $normalizedSeries = array_map(fn ($s) => [
            'name' => (string) ($s['name'] ?? 'Series'),
            'data' => array_map(fn ($v) => is_numeric($v) ? $v + 0 : null, array_values(
                $s['data'] instanceof \Illuminate\Support\Collection ? $s['data']->all() : (array) ($s['data'] ?? [])
            )),
        ], array_values($seriesList));
        $allValues = array_merge([], ...array_map(fn ($s) => $s['data'], $normalizedSeries ?: [['data' => []]]));
    }
    $hasData = collect($allValues)->filter(fn ($v) => $v !== null && (float) $v != 0.0)->isNotEmpty();

    $config = array_filter([
        'type' => $type,
        'series' => $normalizedSeries,
        'categories' => $isDonut ? null : array_map('strval', array_values($cats)),
        'labels' => $isDonut ? array_map('strval', array_values($lbls)) : null,
        'height' => (int) $height,
        'yFormat' => $yFormat,
        'currency' => $currency,
        'totalLabel' => $isDonut ? $totalLabel : null,
        'title' => $title,
    ], fn ($v) => $v !== null);

    $chartId = $id ?? 'chart-'.strtolower(\Illuminate\Support\Str::random(8));
    $fmt = function ($v) use ($yFormat, $currency) {
        if ($v === null) return 'No data';
        return match ($yFormat) {
            'decimal' => number_format((float) $v, 2),
            'percent' => rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%',
            'currency' => $currency.' '.number_format((float) $v, 2),
            default => number_format((float) $v),
        };
    };
    $ariaLabel = trim(($title ?? 'Chart').($subtitle ? '. '.$subtitle : '').'. Data table available.');
@endphp
<figure {{ $attributes->class(['c-chart', 'mb-0']) }} id="{{ $chartId }}">
    @if ($title || $subtitle || ($table && $hasData))
        <figcaption class="c-chart-head">
            <div class="min-w-0">
                @if ($title)<p class="c-chart-title">{{ $title }}</p>@endif
                @if ($subtitle)<p class="c-chart-subtitle">{{ $subtitle }}</p>@endif
            </div>
            @if ($table && $hasData)
                <button type="button" class="btn btn-ghost btn-sm c-chart-toggle" data-chart-toggle aria-pressed="false" aria-controls="{{ $chartId }}-table">
                    <x-ui.icon name="table" /><span data-label>Show table</span>
                </button>
            @endif
        </figcaption>
    @endif

    @if (! $hasData)
        <x-ui.empty-state icon="bar-chart" :title="$empty" compact />
    @else
        <div class="c-chart-canvas" data-chart="{{ json_encode($config) }}" role="img" aria-label="{{ $ariaLabel }}" style="min-height: {{ (int) $height }}px"></div>

        <div @class(['c-chart-table', 'visually-hidden' => ! $table]) id="{{ $chartId }}-table">
            <div class="table-responsive">
                <table class="table table-c table-sm mb-0">
                    @if ($title)<caption class="visually-hidden">{{ $title }}</caption>@endif
                    @if ($isDonut)
                        <thead><tr><th scope="col">Category</th><th scope="col" class="text-end">Value</th></tr></thead>
                        <tbody>
                            @foreach ($normalizedSeries as $i => $v)
                                <tr><th scope="row" class="fw-normal">{{ $config['labels'][$i] ?? 'Item '.($i + 1) }}</th><td class="cell-numeric">{{ $fmt($v) }}</td></tr>
                            @endforeach
                        </tbody>
                    @else
                        <thead>
                            <tr>
                                <th scope="col">{{ $categoryLabel ?? ($type === 'horizontal-bar' ? 'Category' : 'Period') }}</th>
                                @foreach ($normalizedSeries as $s)<th scope="col" class="text-end">{{ $s['name'] }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($config['categories'] ?? [] as $ci => $cat)
                                <tr>
                                    <th scope="row" class="fw-normal">{{ $cat }}</th>
                                    @foreach ($normalizedSeries as $s)<td class="cell-numeric">{{ $fmt($s['data'][$ci] ?? null) }}</td>@endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endif
                </table>
            </div>
        </div>
    @endif
</figure>
