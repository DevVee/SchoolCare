{{--
    x-ui.action-menu: THE row-actions pattern. One ghost "more" button per row that opens a
    menu of text items with small muted icons. Order: View, Edit, situational items, divider,
    destructive items last (danger text, global confirm dialog). Never a row of icon buttons.

    <x-ui.td actions>
        <x-ui.action-menu :for="$user->name">
            <x-ui.action-menu.item :href="route('admin.users.show', $user)" icon="eye">View</x-ui.action-menu.item>
            <x-ui.action-menu.item :href="route('admin.users.edit', $user)" icon="pencil">Edit</x-ui.action-menu.item>
            <x-ui.action-menu.item :action="route('admin.users.toggle-active', $user)" method="PATCH" icon="person-dash"
                confirm="They will be signed out and cannot sign in until reactivated." confirm-title="Deactivate {{ $user->name }}?"
                confirm-button="Deactivate">Deactivate</x-ui.action-menu.item>
            <x-ui.action-menu.divider />
            <x-ui.action-menu.item :action="route('admin.users.destroy', $user)" method="DELETE" icon="trash" danger
                confirm="This permanently removes the account." confirm-title="Delete {{ $user->name }}?"
                confirm-button="Delete user">Delete</x-ui.action-menu.item>
        </x-ui.action-menu>
    </x-ui.td>

    for:   the row's name, used in the button label "Actions for {name}"
    label: full custom aria-label (overrides `for`)
--}}
@props([
    'for' => null,
    'label' => null,
    'align' => 'end',
    'icon' => 'three-dots',
])
@php
    $aria = $label ?? ($for ? 'Actions for '.$for : 'Actions');
@endphp
<div {{ $attributes->class(['dropdown', 'action-menu']) }}>
    <button type="button" class="btn-more" data-bs-toggle="dropdown" data-bs-offset="0,4"
            data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-haspopup="menu"
            aria-label="{{ $aria }}" title="Actions">
        <x-ui.icon :name="$icon" />
    </button>
    <div @class(['dropdown-menu', 'dropdown-menu-end' => $align === 'end'])>
        {{ $slot }}
    </div>
</div>
