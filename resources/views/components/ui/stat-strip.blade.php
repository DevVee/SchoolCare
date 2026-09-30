{{--
    x-ui.stat-strip: array shortcut for x-ui.stat-cards + x-ui.stat-card (same look; kept so
    existing pages upgraded automatically).

    <x-ui.stat-strip :items="[
        ['label' => 'Visits today', 'value' => 24, 'module' => 'logbook', 'href' => route('patient-logs.index')],
        ['label' => 'In clinic now', 'value' => 3, 'tone' => 'teal', 'icon' => 'door-open'],
        ['label' => 'Pending appointments', 'value' => 5, 'module' => 'appointments', 'hint' => '2 for today'],
        ['label' => 'Low stock', 'value' => 6, 'tone' => 'warning', 'icon' => 'exclamation-triangle', 'hint' => 'Below reorder level'],
    ]" />

    item keys: label, value, href, hint (bottom line text), module (tone + default icon),
               tone (colour name or module key; wins over module for colour), icon.
    Default tone when nothing is given: brand.
--}}
@props([
    'items' => [],
    'cols' => null,
])
@php $list = array_values(array_filter((array) $items)); @endphp
@if (count($list))
    <x-ui.stat-cards :cols="$cols ?? min(count($list), 5)" {{ $attributes }}>
        @foreach ($list as $item)
            <x-ui.stat-card
                :label="$item['label'] ?? ''"
                :value="$item['value'] ?? 0"
                :tone="$item['tone'] ?? ($item['module'] ?? 'brand')"
                :icon="$item['icon'] ?? (isset($item['module']) ? config('ui.module_meta.'.(config('ui.module_aliases.'.$item['module']) ?? $item['module']).'.icon') : 'bar-chart')"
                :href="$item['href'] ?? null"
                :sub="$item['hint'] ?? null" />
        @endforeach
    </x-ui.stat-cards>
@endif
