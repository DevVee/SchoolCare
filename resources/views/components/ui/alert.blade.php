{{--
    x-ui.alert: inline, persistent message (use toasts for transient feedback).
    <x-ui.alert variant="warning" title="Low stock">4 medicines are below their reorder level.</x-ui.alert>
    <x-ui.alert variant="danger" title="Please fix the errors below" dismissible>
        <ul><li>...</li></ul>
    </x-ui.alert>
    <x-ui.alert variant="info" accent>
        SMS credits are running low.
        <x-slot:actions><x-ui.button size="sm" variant="secondary" :href="route('admin.settings.index')">Open settings</x-ui.button></x-slot:actions>
    </x-ui.alert>

    variant: success | danger (error) | warning | info | brand (primary) | neutral (secondary)
--}}
@props([
    'variant' => 'info',
    'tone' => null,          // alias of variant
    'title' => null,
    'icon' => null,          // default icon per variant; pass false to hide
    'dismissible' => false,
    'accent' => false,       // 4px coloured left border
])
@php
    $raw = strtolower((string) ($tone ?? $variant));
    $raw = $raw === 'error' ? 'danger' : $raw;
    $toneName = config('ui.bs_tone.'.$raw, $raw);
    $defaultIcons = [
        'success' => 'check-circle-fill', 'danger' => 'exclamation-octagon-fill', 'warning' => 'exclamation-triangle-fill',
        'info' => 'info-circle-fill', 'brand' => 'info-circle-fill', 'neutral' => 'info-circle',
    ];
    $iconName = $icon === false ? null : ($icon ?? ($defaultIcons[$toneName] ?? 'info-circle-fill'));
@endphp
<div {{ $attributes->class(['alert-c', 'tone-'.$toneName, 'alert-accent' => $accent, 'fade show' => $dismissible]) }}
     role="{{ in_array($toneName, ['danger', 'warning'], true) ? 'alert' : 'status' }}">
    @if ($iconName)<x-ui.icon :name="$iconName" class="alert-icon" />@endif
    <div class="alert-content">
        @if ($title)<p class="alert-title">{{ $title }}</p>@endif
        <div class="alert-body">{{ $slot }}</div>
        @isset($actions)<div class="alert-actions">{{ $actions }}</div>@endisset
    </div>
    @if ($dismissible)
        <button type="button" class="btn-close-c" data-bs-dismiss="alert" aria-label="Dismiss"><x-ui.icon name="x-lg" /></button>
    @endif
</div>
