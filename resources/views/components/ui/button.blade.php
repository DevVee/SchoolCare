{{--
    x-ui.button
    <x-ui.button icon="plus-lg">New patient</x-ui.button>
    <x-ui.button :href="route('patients.index')" variant="secondary" icon="arrow-left">Back</x-ui.button>
    <x-ui.button type="submit" variant="danger" size="sm" :loading="$busy">Delete</x-ui.button>
    <x-ui.button icon="pencil" icon-only label="Edit" variant="ghost" />
--}}
@props([
    'variant' => 'primary',   // primary|secondary|outline|ghost|danger|success|warning|link
    'size' => 'md',           // xs|sm|md|lg
    'href' => null,           // renders <a> when set
    'type' => 'button',
    'icon' => null,
    'iconRight' => null,
    'loading' => false,
    'disabled' => false,
    'block' => false,
    'iconOnly' => false,
    'label' => null,          // aria-label/title for icon-only buttons
])
@php
    $variantClass = match ($variant) {
        'outline' => 'btn-outline',
        'ghost' => 'btn-ghost',
        'link' => 'btn-link',
        default => 'btn-'.$variant,
    };
    $isDisabled = $disabled || $loading;
    $classes = [
        'btn',
        $variantClass,
        'btn-'.$size => in_array($size, ['xs', 'sm', 'lg'], true),
        'btn-icon' => $iconOnly,
        'w-100' => $block,
        'disabled' => $href && $isDisabled,
    ];
    $extra = array_filter([
        'aria-label' => $iconOnly ? $label : null,
        'title' => $iconOnly ? $label : null,
        'aria-busy' => $loading ? 'true' : null,
    ]);
@endphp
@if ($href)
<a href="{{ $href }}" {{ $attributes->class($classes)->merge($extra) }} @if ($isDisabled) aria-disabled="true" tabindex="-1" @endif>
@else
<button type="{{ $type }}" {{ $attributes->class($classes)->merge($extra) }} @disabled($isDisabled)>
@endif
    @if ($loading)
        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
    @elseif ($icon)
        <x-ui.icon :name="$icon" />
    @endif
    @unless ($iconOnly){{ $slot }}@endunless
    @if ($iconRight && ! $loading)
        <x-ui.icon :name="$iconRight" />
    @endif
@if ($href)
</a>
@else
</button>
@endif
