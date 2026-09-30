{{--
    x-ui.action-menu.item: one text item in x-ui.action-menu (or any Bootstrap dropdown).
    <x-ui.action-menu.item :href="route('patients.show', $p)" icon="eye">View</x-ui.action-menu.item>
    <x-ui.action-menu.item :action="route('patients.destroy', $p)" method="DELETE" icon="trash" danger
        confirm="Visit history is kept." confirm-title="Delete {{ $p->full_name }}?" confirm-button="Delete">Delete</x-ui.action-menu.item>
    <x-ui.action-menu.item icon="printer" onclick="window.print()">Print</x-ui.action-menu.item>

    href:    link item
    action:  form item (POST + @csrf; method PATCH|PUT|DELETE adds @method)
    confirm: message for the global confirm dialog (+ confirm-title, confirm-button)
    danger:  red text; place destructive items last, after an x-ui.action-menu.divider
--}}
@props([
    'href' => null,
    'action' => null,
    'method' => 'POST',
    'icon' => null,
    'danger' => false,
    'confirm' => null,
    'confirmTitle' => null,
    'confirmButton' => null,
    'disabled' => false,
    'target' => null,
    'title' => null,           // tooltip (e.g. why an item is disabled)
])
<x-ui.dropdown-item :href="$href" :action="$action" :method="$method" :icon="$icon" :tone="$danger ? 'danger' : null"
    :confirm="$confirm" :confirm-title="$confirmTitle" :confirm-button="$confirmButton" :disabled="$disabled" :target="$target"
    {{ $attributes->merge(array_filter(['title' => $title])) }}>{{ $slot }}</x-ui.dropdown-item>
