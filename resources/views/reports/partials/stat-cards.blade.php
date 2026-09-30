{{--
    Responsive x-ui.stat-cards row of x-ui.stat-card (owner round 4: summary numbers as stat cards).
    @include('reports.partials.stat-cards', ['cards' => [
        ['label' => 'Clinic visits', 'value' => 12, 'icon' => 'journal-medical', 'tone' => 'teal', 'module' => 'logbook',
         'delta' => '+3', 'sub' => 'this week', 'href' => route('patient-logs.index')],
    ]])
    keys: label, value, icon, module (module colour, wins) or tone (colour), delta, sub
    (string or HtmlString for coloured markers), href. Null entries are skipped.
--}}
@php
    $cardList = array_values(array_filter((array) ($cards ?? [])));
    $cardCount = count($cardList);
@endphp
@if ($cardCount)
<x-ui.stat-cards :cols="min($cardCount, 6)" class="mb-4">
    @foreach ($cardList as $card)
        <x-ui.stat-card
            :label="$card['label']"
            :value="$card['value'] ?? 0"
            :icon="$card['icon'] ?? null"
            :tone="$card['module'] ?? $card['tone'] ?? 'brand'"
            :delta="$card['delta'] ?? null"
            :sub="$card['sub'] ?? null"
            :href="$card['href'] ?? null" />
    @endforeach
</x-ui.stat-cards>
@endif
