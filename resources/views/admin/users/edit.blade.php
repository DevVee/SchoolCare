@extends('layouts.app')

@section('title', 'Edit '.$user->name)

@php
    $roleOptions = $roles->mapWithKeys(fn ($r) => [$r->name => \Illuminate\Support\Str::headline($r->name)])->all();
    $isSelf = $user->id === auth()->id();
    $currentRole = $user->roles->first()?->name;
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="'Edit '.$user->name" description="Update the account details, role or sign-in status."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Users' => route('admin.users.index'), $user->name => route('admin.users.show', $user), 'Edit' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="eye" :href="route('admin.users.show', $user)">View profile</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Check the highlighted fields and try again.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf
        @method('PUT')
        <x-ui.card>
            <x-ui.section title="Account" description="The name staff will see, and the email used to sign in.">
                <div class="row g-3">
                    <x-ui.input wrapper-class="col-12" name="name" label="Full name" :value="$user->name" required autocomplete="off" />
                    <x-ui.input wrapper-class="col-12" name="email" type="email" label="Email" :value="$user->email" required autocomplete="off" />
                </div>
            </x-ui.section>

            <x-ui.section title="Password" description="Leave both fields empty to keep the current password. To give a one-time temporary password instead, use Reset password on the user's profile.">
                <div class="row g-3">
                    <x-ui.input wrapper-class="col-12 col-md-6" name="password" type="password" label="New password" optional autocomplete="new-password"
                        help="At least 10 characters with upper and lowercase letters, a number and a symbol." />
                    <x-ui.input wrapper-class="col-12 col-md-6" name="password_confirmation" type="password" label="Confirm new password" optional autocomplete="new-password" />
                </div>
            </x-ui.section>

            <x-ui.section title="Role and access" description="The role decides which parts of the system this person can open.">
                <div class="row g-3">
                    <x-ui.select wrapper-class="col-12 col-md-7" name="role" id="role" label="Role" :options="$roleOptions" :selected="$currentRole" required
                        :disabled="$isSelf" :help="$isSelf ? 'You cannot change your own role.' : null" />
                    @if ($isSelf)
                        <input type="hidden" name="role" value="{{ $currentRole }}">
                    @endif
                    <div class="col-12">
                        @if ($isSelf)
                            <x-ui.switch name="is_active" label="Active" description="You cannot deactivate your own account." :checked="true" disabled :unchecked-value="null" />
                            <input type="hidden" name="is_active" value="1">
                        @else
                            <x-ui.switch name="is_active" label="Active" description="Inactive users cannot sign in. Turning this off signs them out right away." :checked="(bool) $user->is_active" />
                        @endif
                    </div>
                </div>
            </x-ui.section>

            <x-slot:footer>
                <x-ui.button variant="secondary" :href="route('admin.users.index')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check-lg">Save changes</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection
