@extends('layouts.app')

@section('title', 'Add user')

@php
    $roleOptions = $roles->mapWithKeys(fn ($r) => [$r->name => \Illuminate\Support\Str::headline($r->name)])->all();
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Add user" :description="'Create a sign-in account for a staff member and choose what they can do in '.settings('app_name').'.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Users' => route('admin.users.index'), 'Add user' => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Check the highlighted fields and try again.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <x-ui.card>
            <x-ui.section title="Account" description="The name staff will see, and the email used to sign in.">
                <div class="row g-3">
                    <x-ui.input wrapper-class="col-12" name="name" label="Full name" required autofocus autocomplete="off" placeholder="Juan Dela Cruz" />
                    <x-ui.input wrapper-class="col-12" name="email" type="email" label="Email" required autocomplete="off" placeholder="name@school.edu" />
                </div>
            </x-ui.section>

            <x-ui.section title="Password" description="Give this password to the user. They can change it after signing in.">
                <div class="row g-3">
                    <x-ui.input wrapper-class="col-12 col-md-6" name="password" type="password" label="Password" required autocomplete="new-password"
                        help="At least 10 characters with upper and lowercase letters, a number and a symbol." />
                    <x-ui.input wrapper-class="col-12 col-md-6" name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" />
                </div>
            </x-ui.section>

            <x-ui.section title="Role and access" description="The role decides which parts of the system this person can open.">
                <div class="row g-3">
                    <x-ui.select wrapper-class="col-12 col-md-7" name="role" id="role" label="Role" :options="$roleOptions" placeholder="Choose a role" required />
                    <div class="col-12" id="roleInfoPanel" hidden>
                        <x-ui.alert variant="info"><span id="roleInfoText"></span></x-ui.alert>
                    </div>
                    <div class="col-12 vstack gap-2">
                        <x-ui.switch name="is_active" label="Active" description="Active users can sign in." :checked="true" />
                        <x-ui.switch name="must_change_password" label="Ask for a new password at first sign in" description="Recommended. The user picks their own password the first time they sign in." :checked="true" />
                    </div>
                </div>
            </x-ui.section>

            <x-slot:footer>
                <x-ui.button variant="secondary" :href="route('admin.users.index')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="person-plus">Create user</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var roleDescriptions = {
        administrator: 'Full access. Can manage users, roles, settings and every clinic module.',
        nurse: 'Clinic work. Can manage patients, visits, consultations, medicines, dispensing and reports.',
        staff: 'Front desk. Can view patients and book appointments.'
    };
    var select = document.getElementById('role');
    var panel = document.getElementById('roleInfoPanel');
    var text = document.getElementById('roleInfoText');
    if (!select || !panel) return;
    function update() {
        var desc = roleDescriptions[select.value];
        text.textContent = desc || '';
        panel.hidden = !desc;
    }
    select.addEventListener('change', update);
    update();
});
</script>
@endpush
