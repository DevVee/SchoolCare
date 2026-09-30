@extends('layouts.app')

{{--
    Add or edit one service, common question or team member (Administration > Website).
    From LandingItemController@create / @edit: $item, $section (service | faq | team), $segment, $meta, $icons.
--}}

@php
    $editing = $item->exists;
    $singular = $meta['singular'];
    $title = $editing ? 'Edit '.$singular : 'Add '.(in_array($singular[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a').' '.$singular;
    $listUrl = route('admin.website.items.index', $segment);
    $action = $editing ? route('admin.website.items.update', [$segment, $item]) : route('admin.website.items.store', $segment);
    $currentIcon = (string) old('icon', $item->icon ?? '');
    $imageUrl = $item->imageUrl() ?? '';
    $intro = [
        'service' => 'A service shows as a card on the clinic page, with an icon or a small picture.',
        'faq'     => 'Common questions show as a list that visitors open one at a time.',
        'team'    => 'Team members show with their photo, or their initials when there is no photo.',
    ][$section];
@endphp

@section('title', $title)

@section('content')
<div class="vstack gap-3 website-admin">

    <x-ui.page-header :title="$title" :description="$intro"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Website' => route('admin.website.edit'), $meta['plural'] => $listUrl, ($editing ? 'Edit' : 'Add') => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Nothing was saved. Fields with a problem are marked in red.</x-ui.alert>
    @endif

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card>
            @if ($section === 'service')
                <x-ui.section title="Service">
                    <div class="vstack gap-3">
                        <x-ui.input name="title" label="Service name" required maxlength="150" :value="$item->title"
                            placeholder="Example: First aid and emergency care" />
                        <x-ui.textarea name="body" label="Description" required rows="4" maxlength="1000" :value="$item->body"
                            help="One or two sentences about what the clinic does for this." />
                    </div>
                </x-ui.section>

                <x-ui.section title="Icon or picture" description="Pick an icon, or upload a small picture instead. When both are set, the picture is shown.">
                    <fieldset class="wa-icon-picker">
                        <legend class="visually-hidden">Icon</legend>
                        <div class="icon-picker-grid">
                            <label @class(['icon-option', 'selected' => $currentIcon === ''])>
                                <input type="radio" name="icon" value="" class="visually-hidden" @checked($currentIcon === '')>
                                <x-ui.icon name="plus-square" />
                                <span>Default</span>
                            </label>
                            @foreach ($icons as $icon => $iconLabel)
                                <label @class(['icon-option', 'selected' => $currentIcon === $icon]) title="{{ $iconLabel }}">
                                    <input type="radio" name="icon" value="{{ $icon }}" class="visually-hidden" @checked($currentIcon === $icon)>
                                    <x-ui.icon :name="$icon" />
                                    <span>{{ $iconLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        <x-ui.field-error name="icon" />
                    </fieldset>

                    <div class="mt-3">
                        @include('admin.website._photo', [
                            'name' => 'image',
                            'label' => 'Picture',
                            'help' => 'PNG, JPG or WebP up to 2 MB. A square picture works best.',
                            'url' => $imageUrl,
                            'removeName' => 'remove_image',
                            'removeLabel' => 'Remove the picture and use the icon',
                            'emptyText' => 'No picture. The icon is shown.',
                            'shape' => 'square',
                        ])
                    </div>
                </x-ui.section>

                <x-ui.section title="Link" description="Optional. Adds a link at the bottom of the card, for example to a form or a page with more details." columns="2">
                    <x-ui.input name="link_label" label="Link text" optional maxlength="60" :value="$item->link_label" placeholder="Learn more" />
                    <x-ui.input name="link_url" type="url" label="Link address" optional maxlength="300" :value="$item->link_url" placeholder="https://" />
                </x-ui.section>

            @elseif ($section === 'faq')
                <x-ui.section title="Question and answer">
                    <div class="vstack gap-3">
                        <x-ui.input name="title" label="Question" required maxlength="150" :value="$item->title"
                            placeholder="Example: Can the clinic give my child medicine?" />
                        <x-ui.textarea name="body" label="Answer" required rows="6" maxlength="3000" :value="$item->body"
                            help="Plain words, as you would explain it at the clinic window." />
                    </div>
                </x-ui.section>

            @else
                <x-ui.section title="Person" columns="2">
                    <x-ui.input name="title" label="Name" required maxlength="150" :value="$item->title" placeholder="Example: Maria Santos, RN" />
                    <x-ui.input name="subtitle" label="Role" required maxlength="150" :value="$item->subtitle" placeholder="Example: School nurse" />
                    <x-ui.textarea wrapper-class="col-full" name="body" label="Short note" optional rows="3" maxlength="500" :value="$item->body"
                        help="Optional. For example the days they are at the clinic." />
                </x-ui.section>

                <x-ui.section title="Photo" description="A clear photo of the face helps students recognise them.">
                    @include('admin.website._photo', [
                        'name' => 'image',
                        'label' => 'Photo',
                        'help' => 'PNG, JPG or WebP up to 2 MB. A square photo works best.',
                        'url' => $imageUrl,
                        'removeName' => 'remove_image',
                        'removeLabel' => 'Remove the photo and show initials',
                        'emptyText' => 'No photo. Their initials are shown.',
                        'shape' => 'round',
                    ])
                </x-ui.section>
            @endif

            <x-ui.section title="On the website">
                <x-ui.switch name="is_enabled" label="Show on the clinic page"
                    description="Turn off to keep it here without showing it to visitors."
                    :checked="(bool) $item->is_enabled" />
            </x-ui.section>

            <x-slot:footer class="justify-content-between flex-wrap gap-2">
                <div>
                    @if ($editing)
                        <x-ui.button variant="ghost" icon="trash" class="text-danger"
                            data-confirm="It is taken off the clinic page right away. This cannot be undone."
                            :data-confirm-title="'Delete '.\Illuminate\Support\Str::limit($item->title, 60).'?'"
                            :data-confirm-button="'Delete '.$singular" data-confirm-variant="danger"
                            :data-confirm-action="route('admin.website.items.destroy', [$segment, $item])" data-confirm-method="DELETE">Delete</x-ui.button>
                    @endif
                </div>
                <div class="d-flex gap-2">
                    <x-ui.button variant="secondary" :href="$listUrl">Cancel</x-ui.button>
                    <x-ui.button type="submit" icon="check-lg">{{ $editing ? 'Save changes' : 'Add '.$singular }}</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection

@if ($section === 'service')
    @push('scripts')
    <script>
    (function () {
        function init() {
            var picker = document.querySelector('.wa-icon-picker');
            if (!picker) return;
            picker.addEventListener('change', function (e) {
                if (!e.target.matches('input[type="radio"]')) return;
                picker.querySelectorAll('.icon-option').forEach(function (opt) {
                    opt.classList.toggle('selected', opt.contains(e.target));
                });
            });
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    })();
    </script>
    @endpush
@endif
