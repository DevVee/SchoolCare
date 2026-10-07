@extends('layouts.app')

@section('title', 'Settings: '.$meta['label'])

{{-- One settings group (Shopify style, like ServiceCo's SettingsBlock): header with the group's icon,
     status, then one block per section (title and description on the left, the fields in a white card
     on the right; stacked below lg), a sticky save bar, then actions outside the form (test messages).
     On desktop the settings panel beside the rail is the menu; below lg an "All settings" button goes
     back to the settings home. Styles: resources/scss/pages/_settings.scss. --}}
@php
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
            'Online requests' => ['Let patients and parents request an appointment from the public website, and what the page says.', ['public_booking_enabled', 'public_booking_intro', 'public_booking_success_message', 'public_booking_closed_message']],
            'Online request dates and times' => ['Which dates and times the request form offers. The times and the places in each come from Administration > Appointment Slots, and the days from the opening hours above.', ['public_booking_days_ahead', 'public_booking_min_notice_hours', 'public_booking_slot_limit', 'public_booking_specialist_days_only', 'public_booking_closed_dates']],
            'Online request form' => ['The reasons offered and which questions the form asks. Categories and appointment types are set under Clinic; grades, programs and sections under Academic.', ['appointment_purposes', 'public_booking_field_category', 'public_booking_field_school', 'public_booking_field_student_id', 'public_booking_field_email', 'public_booking_field_provider', 'public_booking_field_details']],
            'Online request privacy' => ['The statement people agree to before they send a request.', ['public_booking_consent_required', 'public_booking_consent_text']],
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
            'Email' => ['Which events send an email. Invitations and password resets always send.', ['notify_email_appointments', 'notify_email_online_request', 'online_request_notify_email']],
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
<div class="vstack gap-3 settings-page">

    <x-ui.page-header :title="$meta['label']" :icon="$meta['icon'] ?? 'gear'" :description="$meta['description'] ?? null"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Settings' => route('admin.settings.index'), $meta['label'] => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-left" :href="route('admin.settings.index')" class="d-lg-none">All settings</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any() && ! $errors->has('test_number'))
        <x-ui.alert variant="danger" title="Please fix the errors below">Nothing was saved. Check the highlighted fields and try again.</x-ui.alert>
    @endif

    {{-- Status card above the form (SMS, Email, AI): plain words, technical details collapsed. --}}
    @includeIf('admin.settings.partials.'.$group.'-status')

    <form method="POST" action="{{ route('admin.settings.update', $group) }}" enctype="multipart/form-data" id="settingsForm" class="settings-form">
        @csrf
        @method('PUT')

        {{-- Every x-ui.section inside becomes a settings block (pages/_settings.scss). --}}
        <div class="settings-blocks">
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
        </div>

        {{-- Save bar: sticks to the bottom of the screen; stands out once something changed (script below). --}}
        @php $notSaved = $errors->any() && ! $errors->has('test_number'); @endphp
        <div @class(['settings-savebar', 'is-dirty' => $notSaved]) data-settings-savebar @if ($notSaved) data-start-dirty @endif>
            <p class="settings-savebar-msg" role="status" aria-live="polite" data-settings-dirty>
                {{ $notSaved ? 'Nothing was saved. Fix the highlighted fields and save again.' : 'No unsaved changes' }}
            </p>
            <x-ui.button variant="secondary" :href="route('admin.settings.edit', $group)" class="settings-savebar-discard">Discard</x-ui.button>
            <x-ui.button type="submit" icon="check-lg" class="settings-savebar-save">Save</x-ui.button>
        </div>
    </form>

    {{-- Actions outside the settings form (test messages), as settings blocks too. --}}
    @if (view()->exists('admin.settings.partials.'.$group.'-actions'))
        <div class="settings-blocks settings-blocks-after">
            @include('admin.settings.partials.'.$group.'-actions')
        </div>
    @endif
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

    // Save bar: how many fields differ from what was loaded; the bar stands out while any do.
    var form = document.getElementById('settingsForm');
    var status = form && form.querySelector('[data-settings-dirty]');
    var bar = form && form.querySelector('[data-settings-savebar]');
    if (form && status) {
        // Every setting (name without [..]) -> its values as one string.
        var snapshot = function () {
            var out = {};
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!el.name || el.name === '_token' || el.name === '_method' || el.disabled || el.type === 'submit' || el.type === 'button') return;
                var name = el.name.replace(/\[.*$/, '');
                var value = el.type === 'checkbox' || el.type === 'radio' ? (el.checked ? el.value : '') : (el.type === 'file' ? (el.files && el.files.length ? 'file' : '') : el.value);
                out[name] = (out[name] || '') + '\u0001' + value;
            });
            return out;
        };
        var initial = snapshot();
        var update = function () {
            var now = snapshot(), names = {};
            Object.keys(now).concat(Object.keys(initial)).forEach(function (name) {
                if (now[name] !== initial[name]) names[name] = true;
            });
            var n = Object.keys(names).length;
            // After a failed save the page reloads with what was typed: it is still not saved.
            var keep = n === 0 && bar && bar.hasAttribute('data-start-dirty');
            if (!keep) {
                var text = n === 0 ? 'No unsaved changes' : (n === 1 ? 'You have 1 unsaved change.' : 'You have ' + n + ' unsaved changes.');
                // Write only on a change: a write is itself a mutation inside the form (see the observer below).
                if (status.textContent !== text) status.textContent = text;
            }
            if (bar) bar.classList.toggle('is-dirty', n > 0 || keep);
        };
        form.addEventListener('input', update);
        form.addEventListener('change', update);
        // List editors add or remove rows without an input event. Changes to the save bar's own
        // text and to the live previews (Printing) are not edits: reacting to them looped forever
        // (update wrote the status, the write fired the observer, and so on) and froze the page.
        var ignore = function (node) {
            return status.contains(node) || !!(node.closest && node.closest('[data-paper], [data-sig-preview], [data-preview-caption], [data-image-status], [data-signer-state]'));
        };
        new MutationObserver(function (records) {
            for (var i = 0; i < records.length; i++) {
                var t = records[i].target;
                if (!ignore(t.nodeType === 1 ? t : t.parentElement || t)) { update(); return; }
            }
        }).observe(form, { childList: true, subtree: true });
    }
});
</script>
@endpush
