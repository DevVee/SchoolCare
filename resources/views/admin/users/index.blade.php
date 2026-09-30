@extends('layouts.app')

@section('title', 'Users')

@php
    $roleOptions = $roles->mapWithKeys(fn ($r) => [$r->name => \Illuminate\Support\Str::headline($r->name)])->all();
    $statusOptions = ['1' => 'Active', '0' => 'Inactive'];
    $total = $users->total();
    $filtered = request()->hasAny(['search', 'role', 'status']) && collect(request()->only(['search', 'role', 'status']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Users"
        :description="'People who can sign in to '.settings('app_name').'. '.number_format($total).' '.\Illuminate\Support\Str::plural('account', $total).($filtered ? ' match the filters.' : '.')"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Users' => null]">
        @can('manage-users')
            <x-slot:actions>
                <x-ui.button :href="route('admin.users.create')" icon="person-plus">Add user</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    @isset($stats)
        <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-xl-4">
            <div class="col"><x-ui.stat-card class="h-100" label="Total users" :value="number_format($stats['total'])" icon="people" tone="brand"
                :delta="'+'.$stats['new_month']" sub="added this month" /></div>
            <div class="col"><x-ui.stat-card class="h-100" label="Active" :value="number_format($stats['active'])" icon="person-check" tone="success" sub="Can sign in" /></div>
            <div class="col"><x-ui.stat-card class="h-100" label="Inactive" :value="number_format($stats['inactive'])" icon="person-dash" tone="neutral" sub="Cannot sign in" /></div>
            <div class="col"><x-ui.stat-card class="h-100" label="Administrators" :value="number_format($stats['admins'])" icon="shield-check" tone="info" sub="Full access" /></div>
        </div>
    @endisset

    <x-ui.filters :action="route('admin.users.index')" search-placeholder="Name or email"
        :labels="['role' => 'Role', 'status' => 'Status']"
        :options="['role' => $roleOptions, 'status' => $statusOptions]">
        <x-slot:inline>
            <x-ui.select name="role" size="sm" aria-label="Role" :options="$roleOptions" placeholder="All roles" :selected="request('role')" />
            <x-ui.select name="status" size="sm" aria-label="Status" :options="$statusOptions" placeholder="Any status" :selected="request('status')" />
        </x-slot:inline>
    </x-ui.filters>

    <x-ui.card flush>
        <x-ui.table :paginator="$users" noun="users" caption="Users" responsive="stack">
            <x-slot:head>
                <x-ui.th>Name</x-ui.th>
                <x-ui.th>Role</x-ui.th>
                <x-ui.th priority="md">Status</x-ui.th>
                <x-ui.th priority="lg">Last sign in</x-ui.th>
                <x-ui.th priority="xl">Added</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
            </x-slot:head>

            @foreach ($users as $user)
                @php
                    $role = $user->roles->first();
                    $isSelf = $user->id === auth()->id();
                    $targetIsAdmin = $user->hasRole(config('schoolcare.super_admin_role'));
                    $canTouch = auth()->user()->can('manage-users') && (! $targetIsAdmin || auth()->user()->isAdmin());
                @endphp
                <tr>
                    <x-ui.td identity>
                        <div class="identity">
                            <x-ui.avatar :name="$user->name" :src="$user->avatar ? $user->avatarUrl() : null" size="sm" />
                            <div class="identity-text">
                                <a href="{{ route('admin.users.show', $user) }}" class="identity-title">
                                    {{ $user->name }}@if ($isSelf)<span class="text-muted fw-normal"> (you)</span>@endif
                                </a>
                                <span class="identity-sub cell-truncate" title="{{ $user->email }}">{{ $user->email }}</span>
                            </div>
                        </div>
                    </x-ui.td>
                    <x-ui.td label="Role">
                        @if ($role)
                            <x-ui.badge color="neutral" :dot="false">{{ \Illuminate\Support\Str::headline($role->name) }}</x-ui.badge>
                        @else
                            <span class="text-muted">No role</span>
                        @endif
                    </x-ui.td>
                    <x-ui.td priority="md" label="Status">
                        <x-ui.status-badge :status="(bool) $user->is_active" type="patient" />
                    </x-ui.td>
                    <x-ui.td priority="lg" label="Last sign in" muted>
                        @if ($user->last_login_at)
                            <time datetime="{{ $user->last_login_at->toIso8601String() }}" title="{{ $user->last_login_at->format('M j, Y, g:i A') }}">{{ $user->last_login_at->diffForHumans() }}</time>
                        @else
                            Never
                        @endif
                    </x-ui.td>
                    <x-ui.td priority="xl" label="Added" muted>{{ $user->created_at->format('M j, Y') }}</x-ui.td>
                    <x-ui.td actions>
                        <x-ui.action-menu :for="$user->name">
                            <x-ui.action-menu.item :href="route('admin.users.show', $user)" icon="eye">View</x-ui.action-menu.item>
                            @if ($canTouch)
                                <x-ui.action-menu.item :href="route('admin.users.edit', $user)" icon="pencil">Edit</x-ui.action-menu.item>
                                @unless ($isSelf)
                                    @if ($user->is_active)
                                        <x-ui.action-menu.item :action="route('admin.users.toggle-active', $user)" method="PATCH" icon="person-dash"
                                            confirm="They will be signed out right away and cannot sign in until the account is activated again."
                                            :confirm-title="'Deactivate '.$user->name.'?'" confirm-button="Deactivate">Deactivate</x-ui.action-menu.item>
                                    @else
                                        <x-ui.action-menu.item :action="route('admin.users.toggle-active', $user)" method="PATCH" icon="person-check"
                                            confirm="They will be able to sign in again."
                                            :confirm-title="'Activate '.$user->name.'?'" confirm-button="Activate">Activate</x-ui.action-menu.item>
                                    @endif
                                    @if ($user->last_login_at === null)
                                        <x-ui.action-menu.item :action="route('admin.users.resend-invitation', $user)" icon="envelope"
                                            confirm="They will get a new invitation email with a link to choose their password. Any earlier invitation link stops working."
                                            :confirm-title="'Resend invitation to '.$user->name.'?'" confirm-button="Resend invitation">Resend invitation</x-ui.action-menu.item>
                                    @endif
                                    <x-ui.action-menu.item :action="route('admin.users.reset-password', $user)" icon="key"
                                        confirm="They will get an email with a link to set a new password."
                                        :confirm-title="'Reset password for '.$user->name.'?'" confirm-button="Send reset link">Reset password</x-ui.action-menu.item>
                                    <x-ui.action-menu.divider />
                                    <x-ui.action-menu.item :action="route('admin.users.destroy', $user)" method="DELETE" icon="trash" danger
                                        confirm="This permanently removes the account. Accounts linked to clinic records cannot be deleted; deactivate them instead."
                                        :confirm-title="'Delete '.$user->name.'?'" confirm-button="Delete user">Delete</x-ui.action-menu.item>
                                @endunless
                            @endif
                        </x-ui.action-menu>
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if ($filtered)
                    <x-ui.empty-state icon="search" title="No users match these filters" description="Try a different name or clear the filters." compact>
                        <x-ui.button variant="secondary" size="sm" :href="route('admin.users.index')">Clear filters</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="people" title="No users yet" description="Add the clinic staff who need to sign in." compact>
                        @can('manage-users')
                            <x-ui.button size="sm" icon="person-plus" :href="route('admin.users.create')">Add user</x-ui.button>
                        @endcan
                    </x-ui.empty-state>
                @endif
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
