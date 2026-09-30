{{--
    x-ui.filters: GET filter toolbar. Search + 1-2 inline primary filters; every other
    filter goes in the "Filters" dropdown panel (with an active count). Active filters
    show as removable chips with "Clear all". Selects apply on change.

    <x-ui.filters :action="route('patients.index')" search-placeholder="Name, patient no. or contact"
        :labels="['date_from' => 'From', 'date_to' => 'To', 'category' => 'Category', 'sex' => 'Sex', 'status' => 'Status']"
        :options="['category' => $categoryLabels, 'status' => ['1' => 'Active', '0' => 'Inactive']]">
        <x-slot:inline>
            <x-ui.date-range from-name="date_from" to-name="date_to" />
        </x-slot:inline>
        <x-ui.select name="category" label="Category" size="sm" :options="$categoryLabels" placeholder="All categories" />
        <x-ui.select name="status" label="Status" size="sm" :options="['1' => 'Active', '0' => 'Inactive']" placeholder="Any status" />
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" :href="route('patients.export')">Export</x-ui.button>
        </x-slot:actions>
    </x-ui.filters>

    labels:   param => chip label for EVERY filter field (inline and panel). Drives the
              "Filters" count, the chips and "Clear all".
    options:  param => [value => text] so chips show friendly values (optional).
    keep:     extra query params carried as hidden inputs (default sort, dir, per_page, tab).
    Slots:    default = panel fields · inline = 1-2 primary filters (x-ui.date-range, or bare controls
              with aria-label) · pills = quick filter pills · actions / stats = right side, after Filters
    Layout:   one row on desktop (search grows, inline filters ~160px, Filters + actions right-aligned);
              phones: search full width, then inline filters side by side, then Filters.
--}}
@props([
    'action' => null,
    'search' => true,
    'searchName' => 'search',
    'searchPlaceholder' => 'Search',
    'labels' => [],
    'options' => [],
    'keep' => ['sort', 'dir', 'per_page', 'tab'],
    'resetUrl' => null,
    'autosubmit' => true,
    'panelTitle' => 'Filters',
    'id' => null,
])
@php
    $formAction = $action ?? url()->current();
    $panelId = $id ?? 'filters-'.substr(md5($formAction.$searchName), 0, 8);
    $managed = array_merge([$searchName, 'page'], array_keys($labels));
    $active = [];
    foreach ($labels as $param => $chipLabel) {
        $val = request($param);
        if ($val === null || $val === '' || $val === []) {
            continue;
        }
        foreach ((array) $val as $single) {
            if ($single === null || $single === '') {
                continue;
            }
            $display = $options[$param][$single] ?? $single;
            $remaining = is_array($val) ? array_values(array_diff($val, [$single])) : null;
            $active[] = [
                'param' => $param,
                'label' => $chipLabel,
                'value' => is_scalar($display) ? (string) $display : (string) $single,
                'url' => request()->fullUrlWithQuery([$param => $remaining ?: null, 'page' => null]),
            ];
        }
    }
    $searchValue = request($searchName);
    $searchActive = is_string($searchValue) && $searchValue !== '';
    $hasPanel = trim($slot) !== '';
    $inlineParams = [];
    if (isset($inline)) {
        preg_match_all('/name="([^"\[]+)/', (string) $inline, $m);
        $inlineParams = $m[1] ?? [];
    }
    $panelCount = count(array_filter($active, fn ($chip) => ! in_array($chip['param'], $inlineParams, true)));
    $reset = $resetUrl ?? request()->fullUrlWithoutQuery($managed);
    $hidden = [];
    foreach ((array) $keep as $param) {
        if (in_array($param, $managed, true)) {
            continue;
        }
        $v = request($param);
        if (is_scalar($v) && $v !== '') {
            $hidden[$param] = $v;
        }
    }
@endphp
<form method="GET" action="{{ $formAction }}" role="search" data-filter-form @if ($autosubmit) data-autosubmit @endif
      {{ $attributes->class(['card', 'filter-bar']) }}>
    @foreach ($hidden as $param => $v)
        <input type="hidden" name="{{ $param }}" value="{{ $v }}">
    @endforeach
    <div class="filter-bar-main">
        @if ($search)
            <x-ui.search-input :name="$searchName" :placeholder="$searchPlaceholder" />
        @endif
        @isset($inline)
            <div class="filter-inline">{{ $inline }}</div>
        @endisset
        @isset($pills)
            <div class="filter-pills">{{ $pills }}</div>
        @endisset
        @if ($hasPanel || isset($stats) || isset($actions))
            <div class="filter-bar-end">
                @isset($stats)
                    <div class="filter-stats">{{ $stats }}</div>
                @endisset
                @if ($hasPanel)
                    <div class="dropdown filter-bar-toggle">
                        <button type="button" @class(['btn', 'btn-secondary', 'is-active' => $panelCount > 0])
                                data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-offset="0,6" aria-expanded="false"
                                aria-controls="{{ $panelId }}">
                            <x-ui.icon name="sliders" />Filters
                            @if ($panelCount)<span class="filter-count"><span class="visually-hidden">, </span>{{ $panelCount }}<span class="visually-hidden"> active</span></span>@endif
                        </button>
                        <div class="dropdown-menu dropdown-menu-end dropdown-panel filter-panel" id="{{ $panelId }}">
                            <div class="panel-header"><p class="panel-title">{{ $panelTitle }}</p></div>
                            <div class="filter-panel-body">{{ $slot }}</div>
                            <div class="panel-footer">
                                <a href="{{ $reset }}" class="filter-clear">Clear all</a>
                                <x-ui.button type="submit" size="sm">Apply</x-ui.button>
                            </div>
                        </div>
                    </div>
                @endif
                @isset($actions)
                    {{ $actions }}
                @endisset
            </div>
        @endif
        @if ($search)
            <button type="submit" class="visually-hidden">Search</button>
        @endif
    </div>
    @if (count($active) || $searchActive)
        <div class="filter-chips">
            <span class="filter-chips-label">Filtered by</span>
            @if ($searchActive)
                <a href="{{ request()->fullUrlWithoutQuery([$searchName, 'page']) }}" class="filter-chip" aria-label="Remove search filter">
                    <span class="filter-chip-key">Search:</span> {{ \Illuminate\Support\Str::limit($searchValue, 30) }}
                    <span class="filter-chip-x"><x-ui.icon name="x-lg" /></span>
                </a>
            @endif
            @foreach ($active as $chip)
                <a href="{{ $chip['url'] }}" class="filter-chip" aria-label="Remove {{ $chip['label'] }} filter">
                    <span class="filter-chip-key">{{ $chip['label'] }}:</span> {{ $chip['value'] }}
                    <span class="filter-chip-x"><x-ui.icon name="x-lg" /></span>
                </a>
            @endforeach
            <a href="{{ $reset }}" class="filter-clear ms-auto">Clear all</a>
        </div>
    @endif
</form>
