@extends('layouts.app')

@section('title', 'New role')

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="New role" description="Name the role, pick an icon, then tick what people with this role may do."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Roles and permissions' => route('admin.roles.index'), 'New role' => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">
            @error('permissions.*')One of the ticked permissions is not recognised. Reload the page and try again.@else Check the highlighted fields and try again.@enderror
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('admin.roles.store') }}" class="vstack gap-3">
        @csrf

        <x-ui.card>
            <x-ui.section title="Role name" description="Shown when you assign the role to a user.">
                <x-ui.input name="name" id="name" label="Name" required placeholder="head-nurse" pattern="[a-z0-9_\-]+" autocomplete="off"
                    help="Use lowercase letters, numbers and hyphens, for example head-nurse. The name cannot be changed later." />
            </x-ui.section>
            <x-ui.section title="Icon" description="Optional. Helps people recognise the role in lists.">
                @include('admin.roles._icon-picker', ['currentIcon' => old('icon', 'person-fill')])
            </x-ui.section>
        </x-ui.card>

        @include('admin.roles._matrix', ['selected' => old('permissions', [])])

        <div class="save-bar save-bar-standalone">
            <x-ui.button variant="secondary" :href="route('admin.roles.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check-lg">Create role</x-ui.button>
        </div>
    </form>
</div>
@endsection
