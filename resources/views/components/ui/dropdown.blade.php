{{--
    x-ui.dropdown: Bootstrap dropdown with a default button trigger or a custom one.
    <x-ui.dropdown label="Export" icon="download">
        <x-ui.dropdown-item :href="route('reports.export', 'pdf')" icon="filetype-pdf">PDF</x-ui.dropdown-item>
        <x-ui.dropdown-item :href="route('reports.export', 'xlsx')" icon="filetype-xlsx">Excel</x-ui.dropdown-item>
    </x-ui.dropdown>

    <x-ui.dropdown align="end" width="panel">
        <x-slot:trigger>
            <button type="button" class="user-trigger" data-bs-toggle="dropdown" aria-expanded="false">...</button>
        </x-slot:trigger>
        ...
    </x-ui.dropdown>
--}}
@props([
    'label' => null,
    'icon' => null,
    'variant' => 'secondary',  // trigger button variant
    'size' => 'md',            // trigger button size
    'align' => 'end',          // start|end
    'width' => 'menu',         // menu|panel|panel-wide
    'caret' => true,
    'offset' => null,          // "x,y" (default 0,6; panels 0,10)
    'fixed' => false,          // Popper strategy fixed (escapes overflow:hidden parents)
])
@php
    $menuClasses = [
        'dropdown-menu',
        'dropdown-menu-end' => $align === 'end',
        'dropdown-panel' => str_starts_with($width, 'panel'),
        'dropdown-panel-wide' => $width === 'panel-wide',
    ];
    $offsetValue = $offset ?? (str_starts_with($width, 'panel') ? '0,10' : '0,6');
@endphp
<div {{ $attributes->class(['dropdown']) }}>
    @isset($trigger)
        {{ $trigger }}
    @else
        <button type="button" @class(['btn', 'btn-'.$variant, 'btn-sm' => $size === 'sm', 'dropdown-toggle' => $caret])
                data-bs-toggle="dropdown" data-bs-offset="{{ $offsetValue }}" aria-expanded="false"
                @if ($fixed) data-bs-popper-config='{"strategy":"fixed"}' @endif>
            @if ($icon)<x-ui.icon :name="$icon" />@endif{{ $label }}
        </button>
    @endisset
    <div @class($menuClasses)>
        {{ $slot }}
    </div>
</div>
