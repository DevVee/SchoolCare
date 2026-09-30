@extends('layouts.app')

@section('title', 'Website')

{{--
    Administration > Website, "Page text" tab: the Website settings group.
    From WebsiteController@edit: $fields, $settings, $fallbacks, $order, $counts.
    Every boolean of the group is on this page (unchecked switches save as off).
--}}

@php
    $sectionLabels = \App\Services\LandingContent::SECTIONS;
    $plural = fn (int $n, string $word) => $n.' '.\Illuminate\Support\Str::plural($word, $n);
    $sectionHints = [
        'advisories' => ($counts['advisories'] ?? 0) > 0
            ? $plural((int) $counts['advisories'], 'advisory').'. Only those showing right now appear.'
            : 'None posted. Stays hidden until you post one.',
        'services'   => ($counts['service'] ?? 0) > 0 ? $plural((int) $counts['service'], 'service').'.' : 'None added. Stays hidden until you add one.',
        'schedule'   => 'Opening hours from the appointment settings, and the doctor and dentist visit days.',
        'steps'      => 'The three steps written further down this page.',
        'team'       => ($counts['team'] ?? 0) > 0 ? $plural((int) $counts['team'], 'person').'.' : 'Nobody added. Stays hidden until you add someone.',
        'faq'        => ($counts['faq'] ?? 0) > 0 ? $plural((int) $counts['faq'], 'question').'.' : 'None added. Stays hidden until you add one.',
        'contact'    => 'Clinic phone, email and address, and the links further down this page.',
    ];
    $sectionLinks = [
        'advisories' => route('admin.website.advisories.index'),
        'services'   => route('admin.website.items.index', 'services'),
        'team'       => route('admin.website.items.index', 'team'),
        'faq'        => route('admin.website.items.index', 'faqs'),
    ];
    $orderValue = old('landing_section_order', implode("\n", $order));
    $shownOrder = array_values(array_unique(array_merge(
        array_intersect(preg_split('/[\r\n,]+/', (string) $orderValue) ?: [], array_keys($sectionLabels)),
        $order
    )));

    $heroPath = (string) $settings->get('landing_hero_image', '');
    $f = ['fields' => $fields, 'settings' => $settings, 'fallbacks' => $fallbacks];
@endphp

@section('content')
<div class="vstack gap-3 website-admin">

    @include('admin.website._header', ['active' => 'content'])

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Nothing was saved. Check the highlighted fields and try again.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('admin.website.update') }}" enctype="multipart/form-data" class="vstack gap-3" novalidate>
        @csrf
        @method('PUT')

        {{-- Sections: on / off and order --}}
        <x-ui.card title="Sections" subtitle="Choose which parts of the clinic page are shown, and in what order. A section with nothing in it stays hidden.">
            <input type="hidden" name="landing_section_order" id="sectionOrder" value="{{ implode("\n", $shownOrder) }}">
            <ol class="wa-sections" id="sectionList" aria-describedby="sectionOrderHelp">
                @foreach ($shownOrder as $i => $key)
                    <li class="wa-section-row" data-section="{{ $key }}" data-label="{{ $sectionLabels[$key] }}">
                        <span class="wa-section-num tabular" aria-hidden="true">{{ $i + 1 }}</span>
                        <div class="wa-section-main">
                            <x-ui.switch :name="'landing_show_'.$key" :id="'setting_landing_show_'.$key" :label="$sectionLabels[$key]"
                                :description="$sectionHints[$key] ?? null" :checked="(bool) $settings->get('landing_show_'.$key, true)" />
                            @isset($sectionLinks[$key])
                                <a href="{{ $sectionLinks[$key] }}" class="wa-section-link">Edit {{ strtolower($sectionLabels[$key]) }}</a>
                            @endisset
                        </div>
                        <div class="wa-section-move" data-move-controls hidden>
                            <x-ui.button variant="ghost" size="sm" icon="chevron-up" icon-only :label="'Move '.$sectionLabels[$key].' up'" data-move="-1" />
                            <x-ui.button variant="ghost" size="sm" icon="chevron-down" icon-only :label="'Move '.$sectionLabels[$key].' down'" data-move="1" />
                        </div>
                    </li>
                @endforeach
            </ol>
            <p class="wa-note mt-3" id="sectionOrderHelp">Use the arrows to change the order. The new order is saved with the other changes.</p>
            <p class="visually-hidden" aria-live="polite" id="sectionOrderStatus"></p>
            <x-ui.field-error name="landing_section_order" />
        </x-ui.card>

        {{-- Top of the page --}}
        <x-ui.card title="Top of the page" subtitle="The first thing visitors see: the heading, the buttons and a photo.">
            <div class="form-grid">
                @include('admin.website._setting', $f + ['key' => 'landing_hero_heading'])
                @include('admin.website._setting', $f + ['key' => 'landing_hero_subheading'])
                @include('admin.website._setting', $f + ['key' => 'landing_hero_description', 'col' => 'col-full'])

                @include('admin.website._setting', $f + ['key' => 'landing_primary_cta_label'])
                @include('admin.website._setting', $f + ['key' => 'landing_primary_cta_target'])
                <div class="col-full" data-cta-url="landing_primary_cta_target">
                    @include('admin.website._setting', $f + ['key' => 'landing_primary_cta_url'])
                </div>

                @include('admin.website._setting', $f + ['key' => 'landing_secondary_cta_label'])
                @include('admin.website._setting', $f + ['key' => 'landing_secondary_cta_target'])
                <div class="col-full" data-cta-url="landing_secondary_cta_target">
                    @include('admin.website._setting', $f + ['key' => 'landing_secondary_cta_url'])
                </div>

                @isset($fields['landing_hero_image'])
                    @include('admin.website._photo', [
                        'name' => 'landing_hero_image',
                        'label' => $fields['landing_hero_image']['label'] ?? 'Photo',
                        'help' => $fields['landing_hero_image']['help'] ?? null,
                        'url' => $heroPath !== '' ? $settings->imageUrl('landing_hero_image') : '',
                        'removeName' => 'remove_landing_hero_image',
                        'removeLabel' => 'Remove the photo',
                        'emptyText' => 'No photo yet. The "Today at the clinic" card is shown instead.',
                        'shape' => 'wide',
                        'col' => 'col-full',
                    ])
                @endisset
                @include('admin.website._setting', $f + ['key' => 'landing_hero_image_alt', 'col' => 'col-full'])

                @include('admin.website._setting', $f + ['key' => 'landing_nurse_on_duty'])
                @include('admin.website._setting', $f + ['key' => 'landing_location'])
            </div>
        </x-ui.card>

        {{-- How to get care --}}
        <x-ui.card title="How to get care" subtitle="Three short steps. Leave a step empty to leave it out.">
            <div class="row g-3">
                @foreach ([1, 2, 3] as $step)
                    <div class="col-12 col-lg-4">
                        <div class="wa-step">
                            <p class="wa-step-num">Step {{ $step }}</p>
                            <div class="vstack gap-3">
                                @include('admin.website._setting', $f + ['key' => "landing_step{$step}_title"])
                                @include('admin.website._setting', $f + ['key' => "landing_step{$step}_body", 'rows' => 4])
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        {{-- Section introductions --}}
        <x-ui.card title="Section introductions" subtitle="The line under each section heading. Leave one empty to show only the heading.">
            <div class="form-grid">
                @include('admin.website._setting', $f + ['key' => 'landing_services_intro'])
                @include('admin.website._setting', $f + ['key' => 'landing_schedule_intro'])
                @include('admin.website._setting', $f + ['key' => 'landing_team_intro'])
                @include('admin.website._setting', $f + ['key' => 'landing_faq_intro'])
            </div>
        </x-ui.card>

        {{-- Contact and links --}}
        <x-ui.card title="Contact and links" subtitle="The clinic phone, email and address come from the clinic details in Settings.">
            <div class="form-grid">
                @include('admin.website._setting', $f + ['key' => 'landing_hotline'])
                @include('admin.website._setting', $f + ['key' => 'landing_map_url'])
                @include('admin.website._setting', $f + ['key' => 'landing_contact_intro', 'col' => 'col-full'])
                @include('admin.website._setting', $f + ['key' => 'landing_facebook_url'])
                @include('admin.website._setting', $f + ['key' => 'landing_messenger_url'])
                @include('admin.website._setting', $f + ['key' => 'landing_instagram_url'])
                @include('admin.website._setting', $f + ['key' => 'landing_youtube_url'])
                @include('admin.website._setting', $f + ['key' => 'landing_school_website_url'])
            </div>
        </x-ui.card>

        {{-- Footer --}}
        <x-ui.card title="Footer" subtitle="The bottom of every public page.">
            <div class="form-grid">
                @include('admin.website._setting', $f + ['key' => 'landing_footer_about', 'col' => 'col-full'])
                @include('admin.website._setting', $f + ['key' => 'landing_copyright', 'col' => 'col-full'])
                @include('admin.website._setting', $f + ['key' => 'landing_show_credit', 'col' => 'col-full'])
                @include('admin.website._setting', $f + ['key' => 'landing_credit_text', 'col' => 'col-full'])
            </div>
        </x-ui.card>

        {{-- Privacy notice --}}
        <x-ui.card title="Privacy notice">
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" icon-right="box-arrow-up-right" :href="route('privacy')" target="_blank" rel="noopener">
                    View<span class="visually-hidden"> the privacy notice (opens in a new tab)</span>
                </x-ui.button>
            </x-slot:actions>
            @include('admin.website._setting', $f + ['key' => 'landing_privacy_text', 'rows' => 12])
        </x-ui.card>

        {{-- Search engines --}}
        <x-ui.card title="Search results" subtitle="How the clinic page appears in Google and on the browser tab.">
            <div class="form-grid">
                @include('admin.website._setting', $f + ['key' => 'landing_seo_title', 'col' => 'col-full'])
                @include('admin.website._setting', $f + ['key' => 'landing_seo_description', 'col' => 'col-full'])
            </div>
        </x-ui.card>

        <div class="save-bar save-bar-standalone">
            <x-ui.button variant="secondary" :href="route('admin.website.edit')">Discard changes</x-ui.button>
            <x-ui.button type="submit" icon="check-lg">Save changes</x-ui.button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function init() {
        // Section order: move rows with the arrows, keep the hidden field in sync.
        var list = document.getElementById('sectionList');
        var field = document.getElementById('sectionOrder');
        var status = document.getElementById('sectionOrderStatus');
        if (list && field) {
            var rows = function () { return Array.prototype.slice.call(list.querySelectorAll('[data-section]')); };
            var refresh = function () {
                var all = rows();
                all.forEach(function (row, i) {
                    row.querySelector('.wa-section-num').textContent = i + 1;
                    row.querySelector('[data-move="-1"]').disabled = i === 0;
                    row.querySelector('[data-move="1"]').disabled = i === all.length - 1;
                });
                field.value = all.map(function (row) { return row.dataset.section; }).join('\n');
            };
            list.querySelectorAll('[data-move-controls]').forEach(function (el) { el.hidden = false; });
            list.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-move]');
                if (!btn || btn.disabled) return;
                var row = btn.closest('[data-section]');
                var up = btn.dataset.move === '-1';
                var other = up ? row.previousElementSibling : row.nextElementSibling;
                if (!other) return;
                list.insertBefore(up ? row : other, up ? other : row);
                refresh();
                var pos = rows().indexOf(row) + 1;
                if (status) status.textContent = row.dataset.label + ' moved to position ' + pos + '.';
                if (btn.disabled) {
                    var sibling = row.querySelector('[data-move="' + (up ? '1' : '-1') + '"]');
                    if (sibling) sibling.focus();
                } else {
                    btn.focus();
                }
            });
            refresh();
        }

        // Hero buttons: the web address box only matters when the button opens another web address.
        document.querySelectorAll('[data-cta-url]').forEach(function (wrap) {
            var select = document.getElementById('setting_' + wrap.dataset.ctaUrl);
            if (!select) return;
            var sync = function () {
                wrap.hidden = select.value !== 'url' && !wrap.querySelector('.is-invalid');
            };
            select.addEventListener('change', sync);
            sync();
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
@endpush
