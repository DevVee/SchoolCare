{{--
    x-ui.dropdown-item: link, button, or one-click form item inside x-ui.dropdown / x-ui.action-menu.
    <x-ui.dropdown-item :href="route('patients.show', $p)" icon="eye">View</x-ui.dropdown-item>
    <x-ui.dropdown-item :href="route('patients.edit', $p)" icon="pencil">Edit</x-ui.dropdown-item>
    <x-ui.dropdown-item :action="route('patients.destroy', $p)" method="DELETE" icon="trash" tone="danger"
        confirm="This removes the patient record." confirm-title="Delete {{ $p->full_name }}?" confirm-button="Delete">Delete</x-ui.dropdown-item>
    <x-ui.dropdown-item icon="printer" onclick="window.print()">Print</x-ui.dropdown-item>

    tone: danger (red destructive item; put it last, after a divider)
    For table rows prefer the x-ui.action-menu.item alias (same props, `danger` boolean).
--}}
@props([
    'href' => null,
    'action' => null,          // form action URL (POST form with @csrf)
    'method' => 'POST',
    'icon' => null,
    'tone' => null,            // danger
    'label' => null,           // text (alternative to the slot)
    'meta' => null,            // right-aligned hint (shortcut, count)
    'active' => false,
    'disabled' => false,
    'confirm' => null,         // confirmation message (uses the global confirm dialog)
    'confirmTitle' => null,
    'confirmButton' => null,
    'confirmVariant' => null,
    'target' => null,
])
@php
    $verb = strtoupper($method);
    $classes = [
        'dropdown-item',
        'dropdown-item-danger' => $tone === 'danger',
        'active' => $active,
        'disabled' => $disabled,
    ];
    $confirmAttrs = $confirm !== null ? array_filter([
        'data-confirm' => $confirm,
        'data-confirm-title' => $confirmTitle,
        'data-confirm-button' => $confirmButton,
        'data-confirm-variant' => $confirmVariant ?? ($tone === 'danger' || $verb === 'DELETE' ? 'danger' : null),
    ], fn ($v) => $v !== null) : [];
@endphp
@if ($action)
    <form method="{{ $verb === 'GET' ? 'GET' : 'POST' }}" action="{{ $action }}" {{ new \Illuminate\View\ComponentAttributeBag($confirmAttrs) }}>
        @if ($verb !== 'GET') @csrf @endif
        @if (! in_array($verb, ['GET', 'POST'], true)) @method($verb) @endif
        <button type="submit" {{ $attributes->class($classes) }} @disabled($disabled)>
            @if ($icon)<x-ui.icon :name="$icon" />@endif<span class="dropdown-item-label">{{ $label ?? $slot }}</span>@if ($meta)<span class="dropdown-item-meta">{{ $meta }}</span>@endif
        </button>
    </form>
@elseif ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes)->merge($confirmAttrs) }} @if ($target) target="{{ $target }}" @endif
       @if ($active) aria-current="page" @endif @if ($disabled) aria-disabled="true" tabindex="-1" @endif>
        @if ($icon)<x-ui.icon :name="$icon" />@endif<span class="dropdown-item-label">{{ $label ?? $slot }}</span>@if ($meta)<span class="dropdown-item-meta">{{ $meta }}</span>@endif
    </a>
@else
    <button type="button" {{ $attributes->class($classes)->merge($confirmAttrs) }} @disabled($disabled)>
        @if ($icon)<x-ui.icon :name="$icon" />@endif<span class="dropdown-item-label">{{ $label ?? $slot }}</span>@if ($meta)<span class="dropdown-item-meta">{{ $meta }}</span>@endif
    </button>
@endif
