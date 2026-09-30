@extends('layouts.app')

@section('title', 'Roles and permissions')

@php
    $modules = \App\Support\PermissionCatalog::modules();
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Roles and permissions"
        description="A role is a set of permissions. Give each user one role to control what they can open and change."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Roles and permissions' => null]">
        @can('manage-roles')
            <x-slot:actions>
                <x-ui.button :href="route('admin.roles.create')" icon="plus-lg">New role</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <x-ui.card flush>
        <x-ui.table caption="Roles" responsive="stack">
            <x-slot:head>
                <x-ui.th>Role</x-ui.th>
                <x-ui.th align="end">Users</x-ui.th>
                <x-ui.th priority="md">Access</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>

            @foreach ($roles as $role)
                @php
                    $isSuper = $role->name === $superAdminRole;
                    $isSystem = $isSuper || in_array($role->name, $systemRoles, true);
                    $roleLabel = \Illuminate\Support\Str::headline($role->name);
                    $names = $role->permissions->pluck('name')->flip();
                    $areas = collect($modules)->filter(function ($m) use ($names) {
                        foreach (array_merge(array_values($m['actions'] ?? []), array_keys($m['other'] ?? [])) as $p) {
                            if ($names->has($p)) {
                                return true;
                            }
                        }
                        return false;
                    })->pluck('label');
                    $permCount = $role->permissions->count();
                @endphp
                <tr>
                    <x-ui.td identity>
                        <div class="identity">
                            <span class="role-icon" aria-hidden="true"><x-ui.icon :name="$role->icon ?: 'person'" /></span>
                            <div class="identity-text">
                                <a href="{{ route('admin.roles.edit', $role) }}" class="identity-title">{{ $roleLabel }}</a>
                                <span class="identity-sub">{{ $isSystem ? 'Built-in role' : 'Custom role' }}</span>
                            </div>
                        </div>
                    </x-ui.td>
                    <x-ui.td label="Users" numeric>{{ number_format($role->users_count) }}</x-ui.td>
                    <x-ui.td priority="md" label="Access">
                        @if ($isSuper)
                            <x-ui.badge color="brand" :dot="false" icon="shield-check">Full access</x-ui.badge>
                        @elseif ($permCount === 0)
                            <span class="text-muted">No permissions</span>
                        @else
                            <span class="cell-truncate" title="{{ $areas->implode(', ') }}">
                                {{ $permCount }} {{ \Illuminate\Support\Str::plural('permission', $permCount) }} in {{ $areas->count() }} {{ \Illuminate\Support\Str::plural('area', $areas->count()) }}:
                                <span class="text-muted">{{ $areas->implode(', ') }}</span>
                            </span>
                        @endif
                    </x-ui.td>
                    <x-ui.td actions>
                        <x-ui.action-menu :for="$roleLabel">
                            <x-ui.action-menu.item :href="route('admin.roles.edit', $role)" icon="pencil">{{ $isSuper ? 'Change icon' : 'Edit permissions' }}</x-ui.action-menu.item>
                            @unless ($isSystem)
                                <x-ui.action-menu.divider />
                                @if ($role->users_count > 0)
                                    <x-ui.action-menu.item icon="trash" disabled
                                        :title="'Move its '.$role->users_count.' '.\Illuminate\Support\Str::plural('user', $role->users_count).' to another role first'">Delete (move users first)</x-ui.action-menu.item>
                                @else
                                    <x-ui.action-menu.item :action="route('admin.roles.destroy', $role)" method="DELETE" icon="trash" danger
                                        confirm="Nobody has this role. It will be removed permanently."
                                        :confirm-title="'Delete the '.$roleLabel.' role?'" confirm-button="Delete role">Delete</x-ui.action-menu.item>
                                @endif
                            @endunless
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                <x-ui.empty-state icon="shield" title="No roles yet" description="Create a role, then assign it to users." compact />
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>

    <p class="text-muted fs-sm mb-0">Built-in roles cannot be deleted. The {{ \Illuminate\Support\Str::headline($superAdminRole) }} role always has full access so nobody gets locked out.</p>
</div>
@endsection
