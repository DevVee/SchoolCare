{{--
    x-ui.badge: status pill with a dot.
    <x-ui.badge color="success">Active</x-ui.badge>
    <x-ui.badge :color="$appointment->status_badge">{{ $appointment->status_label }}</x-ui.badge>   (Bootstrap names work)
    <x-ui.badge color="danger" variant="solid" :dot="false" icon="exclamation-triangle">Out of stock</x-ui.badge>

    color:   brand|success|warning|danger|info|neutral|orange|teal|cobi,
             colours rose|amber|cyan|indigo|sky|green|emerald|violet|purple|slate,
             or a module key (logbook, patients, medicines, ...; aliases accepted)
             or Bootstrap names primary|secondary|light|dark|success|warning|danger|info
             (also accepts "bg-success" / "text-bg-success")
    variant: soft (default) | solid | outline
    size:    sm | md | lg
--}}
@props([
    'color' => null,
    'tone' => null,        // alias of color
    'variant' => 'soft',
    'dot' => true,
    'icon' => null,
    'size' => 'md',
])
@php
    $raw = strtolower(trim((string) ($color ?? $tone ?? 'neutral')));
    $raw = preg_replace('/^(text-bg-|bg-|text-|pill-)/', '', $raw);
    $raw = preg_replace('/-subtle$/', '', $raw);
    $colourNames = ['rose', 'amber', 'cyan', 'indigo', 'sky', 'green', 'emerald', 'violet', 'purple', 'slate', 'teal'];
    $moduleKeys = array_keys((array) config('ui.module_meta', []));
    if (in_array($raw, config('ui.tones', []), true) || in_array($raw, $colourNames, true) || in_array($raw, $moduleKeys, true)) {
        $toneName = $raw;
    } elseif ($alias = config('ui.module_aliases.'.$raw)) {
        $toneName = $alias;
    } else {
        $toneName = config('ui.bs_tone.'.$raw, 'neutral');
        if (! in_array($toneName, config('ui.tones', []), true)) {
            $toneName = 'neutral';
        }
    }
@endphp
<span {{ $attributes->class([
    'pill',
    'pill-'.$toneName,
    'pill-solid' => $variant === 'solid',
    'pill-outline' => $variant === 'outline',
    'pill-nodot' => ! $dot || $icon,
    'pill-sm' => $size === 'sm',
    'pill-lg' => $size === 'lg',
]) }}>@if ($icon)<x-ui.icon :name="$icon" />@endif{{ $slot }}</span>
