@extends('layouts.app')

@section('title', $user->name)

@php
    $isSelf = $user->id === auth()->id();
    $canTouch = auth()->user()->can('manage-users') && (! $isSuperAdmin || auth()->user()->isAdmin());
    $role = $user->roles->first();
    $loginAt = $user->last_login_at ?? $lastLogin?->created_at;
    $loginIp = $user->last_login_ip ?? $lastLogin?->ip_address;

    // Permissions grouped by module, in plain words ("Patients: View, Create, Edit").
    $granted = $user->getAllPermissions()->pluck('name')->flip();
    $columns = \App\Support\PermissionCatalog::columns();
    $access = [];
    foreach (\App\Support\PermissionCatalog::modules() as $module) {
        $can = [];
        foreach ($module['actions'] ?? [] as $col => $perm) {
            if ($granted->has($perm)) {
                $can[] = $columns[$col] ?? ucfirst($col);
            }
        }
        foreach ($module['other'] ?? [] as $perm => $label) {
            if ($granted->has($perm)) {
                $can[] = $label;
            }
        }
        if ($can) {
            $access[] = ['label' => $module['label'], 'icon' => $module['icon'] ?? 'grid', 'can' => $can];
        }
    }
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="$user->name" :description="$user->email"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Users' => route('admin.users.index'), $user->name => null]">
        @if ($canTouch)
            <x-slot:actions>
                @unless ($isSelf)
                    <x-ui.dropdown label="More" variant="secondary" align="end">
                        @if ($user->is_active)
                            <x-ui.dropdown-item :action="route('admin.users.toggle-active', $user)" method="PATCH" icon="person-dash"
                                confirm="They will be signed out right away and cannot sign in until the account is activated again."
                                :confirm-title="'Deactivate '.$user->name.'?'" confirm-button="Deactivate">Deactivate</x-ui.dropdown-item>
                        @else
                            <x-ui.dropdown-item :action="route('admin.users.toggle-active', $user)" method="PATCH" icon="person-check"
                                confirm="They will be able to sign in again." :confirm-title="'Activate '.$user->name.'?'" confirm-button="Activate">Activate</x-ui.dropdown-item>
                        @endif
                        <x-ui.dropdown-item :action="route('admin.users.reset-password', $user)" icon="key"
                            confirm="A temporary password will be shown to you once. Their current password stops working and they are signed out."
                            :confirm-title="'Reset password for '.$user->name.'?'" confirm-button="Reset password">Reset password</x-ui.dropdown-item>
                        @if ($clinicalRecords === 0)
                            <x-ui.dropdown-divider />
                            <x-ui.dropdown-item :action="route('admin.users.destroy', $user)" method="DELETE" icon="trash" tone="danger"
                                confirm="This permanently removes the account. It cannot be undone."
                                :confirm-title="'Delete '.$user->name.'?'" confirm-button="Delete user">Delete</x-ui.dropdown-item>
                        @endif
                    </x-ui.dropdown>
                @endunless
                <x-ui.button icon="pencil" :href="route('admin.users.edit', $user)">Edit</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if (session('temp_password'))
        <x-ui.alert variant="warning" :title="'Temporary password for '.$user->name">
            <div class="d-flex align-items-center gap-2 flex-wrap my-2">
                <code class="fs-5 px-2 py-1 bg-body border rounded-1 user-select-all text-ink" id="tempPassword">{{ session('temp_password') }}</code>
                <x-ui.button variant="secondary" size="sm" icon="clipboard" id="copyTempPassword">Copy</x-ui.button>
            </div>
            Give it to the user in person or through a private message. It is shown only once, and they must choose a new password when they next sign in.
        </x-ui.alert>
    @endif

    @if ($user->must_change_password)
        <x-ui.alert variant="info">This user must choose a new password the next time they sign in.</x-ui.alert>
    @endif

    @if ($clinicalRecords > 0)
        <x-ui.alert variant="neutral" icon="link-45deg">
            Linked to {{ number_format($clinicalRecords) }} clinic {{ \Illuminate\Support\Str::plural('record', $clinicalRecords) }}.
            This account cannot be deleted. Deactivate it to stop access while keeping the records.
        </x-ui.alert>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <x-ui.card>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <x-ui.avatar :name="$user->name" :src="$user->avatar ? $user->avatarUrl() : null" size="lg" />
                    <div class="min-w-0">
                        <p class="fw-semibold text-ink mb-1 text-truncate">{{ $user->name }}</p>
                        <div class="d-flex flex-wrap gap-1">
                            @if ($role)
                                <x-ui.badge color="neutral" :dot="false">{{ \Illuminate\Support\Str::headline($role->name) }}</x-ui.badge>
                            @endif
                            <x-ui.status-badge :status="(bool) $user->is_active" type="patient" />
                        </div>
                    </div>
                </div>
                <x-ui.description-list layout="stacked">
                    <x-ui.description-item label="Email">{{ $user->email }}</x-ui.description-item>
                    <x-ui.description-item label="Last sign in">
                        @if ($loginAt)
                            <time datetime="{{ $loginAt->toIso8601String() }}" title="{{ $loginAt->format('M j, Y, g:i A') }}">{{ $loginAt->diffForHumans() }}</time>
                            <span class="text-muted">({{ $loginAt->format('M j, Y, g:i A') }})</span>
                        @else
                            Never
                        @endif
                    </x-ui.description-item>
                    <x-ui.description-item label="Signed in from" empty="Not recorded">{{ $loginIp }}</x-ui.description-item>
                    <x-ui.description-item label="Added">{{ $user->created_at->format('M j, Y') }}</x-ui.description-item>
                </x-ui.description-list>
            </x-ui.card>
        </div>

        <div class="col-lg-8 vstack gap-3">
            <x-ui.card title="What this user can do" :subtitle="$role ? 'From the '.\Illuminate\Support\Str::headline($role->name).' role.' : 'No role assigned.'">
                @if ($isSuperAdmin)
                    <p class="mb-0"><x-ui.badge color="brand" :dot="false" icon="shield-check">Full access</x-ui.badge>
                        <span class="ms-1">Administrators can open and change everything in the system.</span></p>
                @elseif (empty($access))
                    <x-ui.empty-state quiet icon="lock" title="No access yet. Give this user a role with permissions." />
                @else
                    <ul class="list-unstyled mb-0 vstack gap-2">
                        @foreach ($access as $row)
                            <li class="d-flex gap-2">
                                <x-ui.icon :name="$row['icon']" class="text-muted mt-1" />
                                <div>
                                    <span class="fw-semibold text-ink">{{ $row['label'] }}:</span>
                                    <span class="text-ink-2">{{ implode(', ', $row['can']) }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card flush title="Recent activity" subtitle="The last 10 things this user did.">
                @can('view-audit-logs')
                    <x-slot:actions>
                        <x-ui.button variant="secondary" size="sm" :href="route('admin.audit-logs.index', ['user_id' => $user->id])">View all activity</x-ui.button>
                    </x-slot:actions>
                @endcan
                <x-ui.table dense responsive="stack" caption="Recent activity">
                    <x-slot:head>
                        <x-ui.th>When</x-ui.th>
                        <x-ui.th>Action</x-ui.th>
                        <x-ui.th>Details</x-ui.th>
                    </x-slot:head>
                    @foreach ($recentLogs as $log)
                        <tr>
                            <x-ui.td label="When" muted>
                                <time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('M j, Y, g:i A') }}">{{ $log->created_at->format('M j, g:i A') }}</time>
                            </x-ui.td>
                            <x-ui.td label="Action"><x-ui.status-badge :status="$log->action" type="audit" /></x-ui.td>
                            <x-ui.td label="Details" truncate>{{ $log->description }}</x-ui.td>
                        </tr>
                    @endforeach
                    <x-slot:empty>
                        <x-ui.empty-state quiet icon="clock-history" title="No activity recorded yet." />
                    </x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection

@if (session('temp_password'))
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('copyTempPassword');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var text = document.getElementById('tempPassword').textContent.trim();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    if (window.toast) window.toast('Temporary password copied.');
                });
            }
        });
    });
    </script>
    @endpush
@endif
