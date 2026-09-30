@extends('layouts.app')

{{--
    Post or edit an advisory (Administration > Website > Advisories).
    From AnnouncementController@create / @edit: $advisory.
--}}

@php
    $editing = $advisory->exists;
    $title = $editing ? 'Edit advisory' : 'Post an advisory';
    $listUrl = route('admin.website.advisories.index');
    $action = $editing ? route('admin.website.advisories.update', $advisory) : route('admin.website.advisories.store');
    $types = \App\Models\Announcement::TYPES;
    $audiences = \App\Models\Announcement::AUDIENCES;
@endphp

@section('title', $title)

@section('content')
<div class="vstack gap-3 website-admin">

    <x-ui.page-header :title="$title" description="Advisories show at the top of the clinic page, on the staff dashboard, or both."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Website' => route('admin.website.edit'), 'Advisories' => $listUrl, ($editing ? 'Edit' : 'Post') => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Nothing was saved. Fields with a problem are marked in red.</x-ui.alert>
    @endif

    <form method="POST" action="{{ $action }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card>
            <x-ui.section title="Advisory">
                <div class="vstack gap-3">
                    <x-ui.input name="title" label="Title" required maxlength="150" :value="$advisory->title"
                        placeholder="Example: The clinic is closed on Friday for the school foundation day" />
                    <x-ui.textarea name="body" label="Message" optional rows="4" maxlength="2000" :value="$advisory->body"
                        help="Optional. A few sentences with the details." />
                </div>
            </x-ui.section>

            <x-ui.section title="Kind and place" columns="2">
                <x-ui.select name="type" label="Kind" required :options="$types" :selected="$advisory->type"
                    help="Urgent advisories are shown in red." />
                <x-ui.select name="audience" label="Where to show it" required :options="$audiences" :selected="$advisory->audience" />
            </x-ui.section>

            <x-ui.section title="When" description="Leave both empty to show it until you turn it off." columns="2">
                <x-ui.input type="datetime-local" name="starts_at" label="Start" optional :value="$advisory->starts_at" />
                <x-ui.input type="datetime-local" name="ends_at" label="End" optional :value="$advisory->ends_at" />
            </x-ui.section>

            <x-ui.section title="Link" description="Optional. A link under the message, for example to a form or a school announcement." columns="2">
                <x-ui.input name="link_label" label="Link text" optional maxlength="60" :value="$advisory->link_label" placeholder="Read more" />
                <x-ui.input name="link_url" type="url" label="Link address" optional maxlength="300" :value="$advisory->link_url" placeholder="https://" />
            </x-ui.section>

            <x-ui.section title="Turned on">
                <x-ui.switch name="is_enabled" label="Show this advisory"
                    description="Turn off to keep it here without showing it anywhere."
                    :checked="(bool) $advisory->is_enabled" />
            </x-ui.section>

            <x-slot:footer class="justify-content-between flex-wrap gap-2">
                <div>
                    @if ($editing)
                        <x-ui.button variant="ghost" icon="trash" class="text-danger"
                            data-confirm="It is taken off the clinic page and the staff dashboard right away. This cannot be undone."
                            :data-confirm-title="'Delete '.\Illuminate\Support\Str::limit($advisory->title, 60).'?'"
                            data-confirm-button="Delete advisory" data-confirm-variant="danger"
                            :data-confirm-action="route('admin.website.advisories.destroy', $advisory)" data-confirm-method="DELETE">Delete</x-ui.button>
                    @endif
                </div>
                <div class="d-flex gap-2">
                    <x-ui.button variant="secondary" :href="$listUrl">Cancel</x-ui.button>
                    <x-ui.button type="submit" icon="check-lg">{{ $editing ? 'Save changes' : 'Post advisory' }}</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection
