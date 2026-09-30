{{--
    x-ui.icon-chip: small tinted square with a tone-coloured icon (module colour at ~10%).
    <x-ui.icon-chip module="patients" />                       (icon comes from config('ui.module_meta'))
    <x-ui.icon-chip module="patient-logs" icon="journal-plus" size="lg" />   (aliases accepted)
    <x-ui.icon-chip tone="warning" icon="exclamation-triangle" size="sm" />

    module: overview (dashboard)|logbook|patients|appointments|consultations|medicines|inventory|dispensing|
            reports|sms|ai|admin (or an alias from config('ui.module_aliases'))
    tone:   brand|success|warning|danger|info|neutral|orange|teal|cobi (used when no module)
    size:   sm 28 | md 32 (default) | lg 36
    label:  accessible name; omit when the chip is decorative (next to a visible title)
--}}
@props([
    'module' => null,
    'tone' => null,
    'icon' => null,
    'size' => 'md',
    'label' => null,
])
@php
    $moduleKey = $module ? (config('ui.module_aliases.'.$module) ?? $module) : null;
    $moduleDef = $moduleKey ? config('ui.module_meta.'.$moduleKey) : null;
    if ($moduleDef !== null) {
        $toneKey = $moduleKey;
    } else {
        $toneKey = config('ui.bs_tone.'.($tone ?? 'brand'), $tone ?? 'brand');
    }
    $iconName = $icon ?? ($moduleDef['icon'] ?? 'circle');
@endphp
<span {{ $attributes->class(['icon-chip', 'icon-chip-'.$size, 'tone-chip-'.$toneKey]) }}
      @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif>
    <x-ui.icon :name="$iconName" />
</span>
