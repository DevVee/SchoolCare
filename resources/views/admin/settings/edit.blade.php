@extends('layouts.app')

@section('title', 'Settings: '.$meta['label'])

@php
    $navItems = [];
    foreach ($groups as $name => $g) {
        $navItems[$name] = [
            'label' => $g['label'],
            'icon' => $g['icon'] ?? 'gear',
            'href' => route('admin.settings.edit', $name),
        ];
    }

    // Plain-language sections for the generic groups: title => [description, [keys]].
    // Keys not listed here fall into a final "Other settings" section.
    $layouts = [
        'general' => [
            'Names' => ['How the system and the school are named on screens, reports and messages.', ['app_name', 'app_short_name', 'app_tagline', 'org_name', 'org_short_name']],
            'Dates and lists' => ['How dates and times are shown, and how many rows each list shows per page.', ['timezone', 'date_format', 'time_format', 'records_per_page']],
        ],
        'clinic' => [
            'Clinic details' => ['Printed on reports and forms, and used in text messages.', ['clinic_name', 'clinic_contact', 'clinic_email', 'clinic_address']],
            'Patient choices' => ['Choices offered when adding or editing a patient. Type one choice per line.', ['patient_categories', 'patient_genders', 'blood_types']],
            'Visit choices' => ['Choices offered when logging a clinic visit.', ['visit_reasons', 'visit_severity_levels', 'dispositions']],
            'Medicines and appointments' => ['Choices offered in the medicine and appointment forms.', ['medicine_units', 'appointment_providers', 'specialist_types']],
        ],
        'branding' => [
            'Logo' => ['Shown in the sidebar, top bar, sign-in page, printed reports and health cards.', ['brand_logo', 'brand_favicon']],
            'Name and colour' => ['What the sidebar and top bar show, and the main colour of buttons and links.', ['header_title', 'topbar_show_school', 'brand_primary_color']],
            'Sign-in page' => ['Text on the page where staff sign in.', ['login_headline', 'login_subtext', 'login_quote', 'login_quote_author']],
        ],
        'appointments' => [
            'Booking rules' => ['Limits that apply when staff or patients book an appointment.', ['max_daily_appointments', 'booking_max_days_ahead', 'appointment_slot_minutes', 'allow_weekend_booking', 'appointment_cancel_reason_required']],
            'Opening hours' => ['The days and hours the clinic is open. Turn a day off to mark it closed.', ['clinic_weekly_hours']],
            'Reminders' => ['When patients get a reminder text before their appointment.', ['reminder_hours_before']],
            'Online requests' => ['Let patients and parents request an appointment from the public website.', ['public_booking_enabled', 'appointment_purposes']],
        ],
        'intake' => [
            'Online health form' => ['A public form where students and parents send health information. Nothing is added to patient records until staff approve it.', ['public_intake_enabled', 'intake_consent_text', 'intake_success_message']],
        ],
        'inventory' => [
            'Stock alerts' => ['When medicines are flagged as running low or expiring soon.', ['low_stock_threshold', 'expiry_warning_days']],
            'Equipment' => ['Choices for the condition of clinic equipment and supplies.', ['asset_conditions']],
        ],
        'notifications' => [
            'Text messages (SMS)' => ['Which events send a text message. The main switch must be on for any text to go out.', ['sms_enabled', 'notify_sms_appointment_created', 'notify_sms_appointment_approved', 'notify_sms_appointment_rescheduled', 'notify_sms_appointment_cancelled', 'notify_sms_appointment_reminder', 'sms_log_guardian_enabled', 'notify_sms_clinic_discharge', 'notify_sms_intake_approved']],
            'Email' => ['Which events send an email.', ['notify_email_appointments', 'notify_email_user_created']],
        ],
    ];

    $sections = [];
    if (empty($meta['partial'])) {
        $used = [];
        foreach ($layouts[$group] ?? [] as $title => [$desc, $keys]) {
            $keys = array_values(array_filter($keys, fn ($k) => isset($fields[$k])));
            if ($keys) {
                $sections[] = ['title' => $title, 'description' => $desc, 'keys' => $keys];
                $used = array_merge($used, $keys);
            }
        }
        $rest = array_values(array_diff(array_keys($fields), $used));
        if ($rest) {
            $sections[] = ['title' => $sections ? 'Other settings' : $meta['label'], 'description' => $sections ? null : ($meta['description'] ?? null), 'keys' => $rest];
        }
    }
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Settings" :description="'Set up how '.settings('app_name').' works for your clinic.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Settings' => route('admin.settings.index'), $meta['label'] => null]" />

    <div class="row g-4">
        <div class="col-lg-3 settings-nav-col">
            <x-ui.section-nav class="settings-nav" title="Settings" :active="$group" :items="$navItems" />
        </div>

        <div class="col-lg-9 vstack gap-3 min-w-0">
            @if ($errors->any() && ! $errors->has('test_number'))
                <x-ui.alert variant="danger" title="Please fix the errors below">Nothing was saved. Check the highlighted fields and try again.</x-ui.alert>
            @endif

            {{-- Status card above the form (SMS, Email, AI): plain words, technical details collapsed. --}}
            @includeIf('admin.settings.partials.'.$group.'-status')

            <form method="POST" action="{{ route('admin.settings.update', $group) }}" enctype="multipart/form-data" id="settingsForm">
                @csrf
                @method('PUT')

                <x-ui.card :title="$meta['label']" :subtitle="$meta['description'] ?? null">

                    @if (! empty($meta['partial']))
                        @include($meta['partial'])
                    @else
                        @foreach ($sections as $section)
                            <x-ui.section :title="$section['title']" :description="$section['description']">
                                <div class="row g-3">
                                    @foreach ($section['keys'] as $key)
                                        @include('admin.settings.partials.field', ['key' => $key, 'def' => $fields[$key]])
                                    @endforeach
                                </div>
                            </x-ui.section>
                        @endforeach
                    @endif

                    <x-slot:footer>
                        <x-ui.button variant="secondary" :href="route('admin.settings.edit', $group)">Discard changes</x-ui.button>
                        <x-ui.button type="submit" icon="check-lg">Save changes</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>

            {{-- Actions outside the settings form (test messages). --}}
            @includeIf('admin.settings.partials.'.$group.'-actions')
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Image fields: preview the chosen file before saving.
    document.querySelectorAll('[data-image-field]').forEach(function (wrap) {
        var input = wrap.querySelector('[data-image-input]');
        var img = wrap.querySelector('[data-image-preview]');
        var empty = wrap.querySelector('.image-field-empty');
        if (!input || !img) return;
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;
            img.src = URL.createObjectURL(file);
            img.alt = 'Selected image';
            img.hidden = false;
            if (empty) empty.hidden = true;
        });
    });

    // Colour fields: keep the picker and the text box in sync.
    document.querySelectorAll('[data-color-picker]').forEach(function (picker) {
        var text = document.getElementById(picker.dataset.colorPicker);
        if (!text) return;
        picker.addEventListener('input', function () { text.value = picker.value.toUpperCase(); });
        text.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) picker.value = text.value;
        });
    });
});
</script>
@endpush
