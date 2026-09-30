@extends('layouts.app')

@php
    $roleLabel = \Illuminate\Support\Str::headline($role->name);
    $currentPerms = $role->permissions->pluck('name')->toArray();
    $currentIcon = old('icon', $role->icon ?? 'person-fill');
@endphp

@section('title', 'Edit role: '.$roleLabel)

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="$roleLabel"
        :description="$isSuperAdmin ? 'Full access. All permissions, always.' : count($currentPerms).' '.\Illuminate\Support\Str::plural('permission', count($currentPerms)).' assigned.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Roles and permissions' => route('admin.roles.index'), $roleLabel => null]">
        @if ($isSystem)
            <x-ui.badge color="neutral" :dot="false" icon="lock">Built-in role</x-ui.badge>
        @endif
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">
            @error('permissions.*')One of the ticked permissions is not recognised. Reload the page and try again.@else Check the highlighted fields and try again.@enderror
        </x-ui.alert>
    @endif

    @if ($isSuperAdmin)
        <x-ui.alert variant="info" icon="shield-check" title="Full access">
            All permissions. The {{ $roleLabel }} role can always open and change everything, so nobody can lock themselves out. You can only change its icon.
        </x-ui.alert>
    @elseif ($isSystem)
        <x-ui.alert variant="neutral">This is a built-in role. Its name cannot be changed and it cannot be deleted, but you can change its icon and permissions.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="vstack gap-3">
        @csrf
        @method('PUT')

        <x-ui.card>
            <x-ui.section title="Icon" description="Helps people recognise the role in lists.">
                @include('admin.roles._icon-picker', ['currentIcon' => $currentIcon])
            </x-ui.section>
        </x-ui.card>

        @if ($isSuperAdmin)
            @include('admin.roles._matrix', ['selected' => $existingPermissions, 'readonly' => true])
        @else
            @include('admin.roles._matrix', ['selected' => old('permissions', $currentPerms)])
        @endif

        <div class="save-bar save-bar-standalone">
            <x-ui.button variant="secondary" :href="route('admin.roles.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check-lg">{{ $isSuperAdmin ? 'Save icon' : 'Save changes' }}</x-ui.button>
        </div>
    </form>
</div>
@endsection
