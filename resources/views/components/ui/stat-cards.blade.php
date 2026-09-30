{{--
    x-ui.stat-cards: responsive equal-height row of x-ui.stat-card
    (1 column on small phones, 2 on phones/tablets, `cols` per row on desktop, max 4 at lg).

    <x-ui.stat-cards cols="5">
        <x-ui.stat-card label="Visits today" :value="$today" tone="logbook" />
        ...
    </x-ui.stat-cards>

    cols: cards per row on xl screens (default 4; use 5 for five cards). lg shows at most 4.
--}}
@props([
    'cols' => 4,
])
@php $n = max(1, min(6, (int) $cols)); @endphp
<div {{ $attributes->class('stat-cards')->style(['--stat-cols: '.$n, '--stat-cols-lg: '.min($n, 4)]) }}>
    {{ $slot }}
</div>
