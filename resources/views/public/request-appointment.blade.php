{{--
    Public appointment request form (/request-appointment). What it asks and
    offers is set in Admin > Settings > Appointments (App\Services\OnlineBooking):
    the reasons, which questions are required, optional or hidden, the dates
    (open days up to "how far ahead", closed dates, specialist visit days) and the
    times (Administration > Appointment Slots, clinic hours, places left).

    Dropdowns that follow another one:
      category          -> grade or year level, program, section (Settings > Academic)
      reason "Other..." -> a box to type the reason
      appointment with  -> dates (specialist visit days, when that setting is on)
      date              -> times (fetched from public.appointments.slots)
--}}
@extends('layouts.public')

@section('title', 'Request an appointment')
@section('nav', 'request')

@php
    $dayNames = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
    $openDays = collect($week)->filter()->keys()
        ->when(! settings('allow_weekend_booking', true), fn ($c) => $c->reject(fn ($d) => in_array($d, ['saturday', 'sunday'], true)))
        ->map(fn ($d) => $dayNames[$d] ?? ucfirst($d))->values();
    $openDaysText = $openDays->count() > 1 ? $openDays->slice(0, -1)->implode(', ').' and '.$openDays->last() : $openDays->implode('');
    $clinicPhone = trim((string) settings('clinic_contact'));
    $intro = trim((string) settings('public_booking_intro', ''));

    // Category, and the grade / program / section lists that follow it.
    $showCategory = $online->shows('category');
    $showSchool   = $online->shows('school');
    $categoryValue = (string) old('category', '');
    $lists = ($showCategory && $categoryValue === '')
        ? []
        : \App\Support\AcademicLists::forCategory($showCategory ? $categoryValue : null);
    $schoolFields = [
        ['name' => 'year_level',     'key' => 'levels',   'label' => 'Grade or year level',       'placeholder' => 'Choose grade or year level'],
        ['name' => 'program_strand', 'key' => 'programs', 'label' => 'Program, strand or course', 'placeholder' => 'Choose program or strand'],
        ['name' => 'section',        'key' => 'sections', 'label' => 'Section',                   'placeholder' => 'Choose section'],
    ];
    $schoolApplies = collect($schoolFields)->contains(fn ($f) => ($lists[$f['key']] ?? []) !== [] || old($f['name'], '') !== '');

    // Reason, with a box to type one when "Other..." is chosen.
    $purposeValue = (string) old('purpose', '');
    $isOther = $purposes && \App\Services\OnlineBooking::isOther($purposeValue);

    // Appointment with, and the dates it allows.
    $showProvider  = $online->shows('provider');
    $providerValue = $showProvider ? (string) old('provider', '') : '';
    $specialist    = in_array($providerValue, $specialists, true) ? $providerValue : null;
    $dateOptions   = array_values(array_filter($dates, fn ($d) => $specialist === null || in_array($specialist, $d['visits'], true)));
    $dateText = function (array $d) use ($specialist) {
        if ($d['closed'] !== null) {
            return $d['label'].' (closed'.($d['closed'] !== '' ? ': '.$d['closed'] : '').')';
        }
        if ($d['open'] === 0) {
            return $d['label'].' (full)';
        }
        if ($specialist !== null) {
            return $d['label'].' ('.mb_strtolower($specialist).' visit)';
        }

        return $d['label'].' ('.$d['open'].' '.($d['open'] === 1 ? 'time' : 'times').' open)';
    };
    $anyOpenDate = collect($dateOptions)->contains(fn ($d) => $d['closed'] === null && $d['open'] > 0);

    $timeValue  = app(\App\Services\AppointmentBooking::class)->normalizeTime((string) old('appointment_time', ''));
    $timeSlots  = $slots->whereIn('state', ['open', 'full'])->values();
    $openTimes  = $timeSlots->where('available', true)->count();
    $dateError  = $errors->has('appointment_date');
    $timeError  = $errors->has('appointment_time');

    $consentRequired = $online->consentRequired();
    $consentText = trim((string) settings('public_booking_consent_text', ''));

    // What the script below needs to rebuild the dropdowns that follow each other.
    $config = [
        'academic'      => $academic,
        'categoryShown' => $showCategory,
        'dates'         => $dates,
        'specialists'   => $specialists,
        'slotsUrl'      => route('public.appointments.slots'),
    ];
@endphp

@section('content')
<div class="pub-narrow">
    <header class="pub-head">
        <h1 class="pub-title">Request an appointment</h1>
        @if ($intro !== '')
            <p class="pub-intro">{{ $intro }}</p>
        @endif
        <p class="pub-intro small">
            @if ($openDaysText !== '') The clinic takes appointments on {{ $openDaysText }}. @endif
            For emergencies, go to the clinic directly{{ $clinicPhone !== '' ? ' or call '.$clinicPhone : '' }}.
            <a href="{{ route('public.schedule') }}">See today's open times</a>
        </p>
        <p class="pub-intro small">
            Fields marked <span class="form-required" aria-hidden="true">*</span><span class="visually-hidden">with an asterisk</span> are required.
        </p>
    </header>

    @if ($errors->any())
        <div class="alert-c tone-danger pub-errors" role="alert" tabindex="-1" id="formErrors">
            <x-ui.icon name="exclamation-octagon-fill" class="alert-icon" />
            <div class="alert-content">
                <p class="alert-title">
                    {{ $errors->count() === 1 ? 'Please fix 1 field before sending your request.' : 'Please fix '.$errors->count().' fields before sending your request.' }}
                </p>
                <ul>
                    @foreach ($errors->messages() as $field => $messages)
                        <li><a href="#f-{{ $field }}" data-error-link>{{ $messages[0] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('public.appointments.store') }}" novalidate id="requestForm" class="pub-form">
        @csrf
        {{-- Honeypot: hidden from people, filled in by bots. --}}
        <div class="hp-field" aria-hidden="true">
            <label for="website">Leave this empty</label>
            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="pub-steps">
            {{-- 1. About you --}}
            <x-ui.card class="pub-step">
                <x-ui.section title="About you" description="So the clinic knows who is coming and how to reach you." columns="2">
                    <x-ui.input name="requester_name" label="Full name" required maxlength="150" autocomplete="name" wrapper-class="col-full" />
                    <x-ui.input name="requester_contact" type="tel" label="Mobile number" required maxlength="30" autocomplete="tel"
                        inputmode="tel" placeholder="09171234567" help="The clinic texts you about your request." />

                    @if ($showCategory)
                        <x-ui.select name="category" label="Category" :options="$categories" placeholder="Choose a category"
                            :required="$online->requires('category')" :optional="! $online->requires('category')" data-booking-category />
                    @endif

                    @if ($online->shows('student_id'))
                        <x-ui.input name="requester_student_id" label="Student or employee ID" maxlength="50" autocomplete="off"
                            :required="$online->requires('student_id')" :optional="! $online->requires('student_id')" />
                    @endif

                    @if ($online->shows('email'))
                        <x-ui.input name="requester_email" type="email" label="Email" maxlength="150" autocomplete="email"
                            :required="$online->requires('email')" :optional="! $online->requires('email')" />
                    @endif

                    @if ($showSchool)
                        <div class="col-full pub-school" data-school-block @if (! $schoolApplies) hidden @endif>
                            <p class="pub-subhead">School details <span>The choices follow the category.</span></p>
                            <div class="pub-school-grid">
                                @foreach ($schoolFields as $f)
                                    @php
                                        $options = $lists[$f['key']] ?? [];
                                        $current = (string) old($f['name'], '');
                                        $hasErr  = $errors->has($f['name']);
                                        $applies = $options !== [] || $current !== '';
                                        $fieldRequired = $online->requires('school');
                                    @endphp
                                    <x-ui.field :label="$f['label']" :name="$f['name']" :required="$fieldRequired" :optional="! $fieldRequired"
                                        :hidden="! $applies" data-school-field>
                                        <select name="{{ $f['name'] }}" id="f-{{ $f['name'] }}" data-academic-field="{{ $f['key'] }}"
                                                data-placeholder="{{ $f['placeholder'] }}"
                                                class="form-select @if ($hasErr) is-invalid @endif"
                                                @if ($hasErr) aria-invalid="true" aria-describedby="f-{{ $f['name'] }}-error" @endif
                                                @disabled(! $applies)>
                                            <option value="">{{ $f['placeholder'] }}</option>
                                            @foreach ($options as $opt)
                                                <option value="{{ $opt }}" @selected($current === $opt)>{{ $opt }}</option>
                                            @endforeach
                                            @if ($current !== '' && ! in_array($current, $options, true))
                                                <option value="{{ $current }}" selected>{{ $current }}</option>
                                            @endif
                                        </select>
                                    </x-ui.field>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </x-ui.section>
            </x-ui.card>

            {{-- 2. Your visit --}}
            <x-ui.card class="pub-step">
                <x-ui.section title="Your visit" description="Pick a reason, then a date and one of the open times." columns="2">
                    @if ($purposes)
                        <x-ui.select name="purpose" label="Reason for the visit" :options="array_combine($purposes, $purposes)"
                            placeholder="Choose a reason" required :wrapper-class="$showProvider ? null : 'col-full'" data-booking-purpose />
                    @else
                        <x-ui.input name="purpose" label="Reason for the visit" required maxlength="150"
                            :wrapper-class="$showProvider ? null : 'col-full'" />
                    @endif

                    @if ($showProvider)
                        <x-ui.select name="provider" label="Appointment with" :options="array_combine($providers, $providers)"
                            :placeholder="$online->requires('provider') ? 'Choose one' : 'No preference'"
                            :required="$online->requires('provider')" :optional="! $online->requires('provider')" data-booking-provider />
                    @endif

                    @if ($purposes)
                        <div class="col-full" data-other-wrap @if (! $isOther) hidden @endif>
                            <x-ui.input name="purpose_other" label="Please specify the reason" maxlength="150" required
                                :disabled="! $isOther" placeholder="For example: clearance for a school trip" />
                        </div>
                    @endif

                    @if (! $anyOpenDate)
                        <x-ui.alert variant="info" class="col-full" title="No open dates right now">
                            There are no open appointment times until {{ $lastDate->format('F j') }}.
                            Please visit the clinic during clinic hours{{ $clinicPhone !== '' ? ' or call '.$clinicPhone : '' }}.
                        </x-ui.alert>
                    @endif

                    <x-ui.field label="Date" name="appointment_date" for="f-appointment_date" required>
                        <select name="appointment_date" id="f-appointment_date" required
                                class="form-select @if ($dateError) is-invalid @endif"
                                aria-describedby="dateHelp{{ $dateError ? ' f-appointment_date-error' : '' }}"
                                @if ($dateError) aria-invalid="true" @endif>
                            <option value="">Choose a date</option>
                            @foreach ($dateOptions as $d)
                                <option value="{{ $d['date'] }}" @disabled($d['closed'] !== null || $d['open'] === 0) @selected($dateValue === $d['date'])>{{ $dateText($d) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text" id="dateHelp" aria-live="polite">
                            @if ($specialist !== null && $dateOptions === [])
                                No {{ mb_strtolower($specialist) }} visit days are scheduled yet. Choose another option or call the clinic.
                            @else
                                Dates up to {{ $lastDate->format('F j') }}.
                            @endif
                        </div>
                    </x-ui.field>

                    <x-ui.field label="Time" name="appointment_time" for="f-appointment_time" required>
                        <select name="appointment_time" id="f-appointment_time" required data-current="{{ $timeValue }}"
                                class="form-select @if ($timeError) is-invalid @endif"
                                aria-describedby="slotHelp{{ $timeError ? ' f-appointment_time-error' : '' }}"
                                @if ($timeError) aria-invalid="true" @endif>
                            <option value="">{{ $dateValue === '' ? 'Choose a date first' : 'Choose a time' }}</option>
                            @foreach ($timeSlots as $s)
                                <option value="{{ $s['time'] }}" @disabled(! $s['available']) @selected($timeValue === $s['time'])>
                                    {{ $s['label'] }}{{ $s['available'] ? ' ('.$s['remaining'].' left)' : ' (full)' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text" id="slotHelp" aria-live="polite">
                            @if ($dateValue !== '')
                                {{ $openTimes === 1 ? '1 time is open on this date.' : ($openTimes ? $openTimes.' times are open on this date.' : 'All times are taken on this date. Choose another date.') }}
                            @endif
                        </div>
                    </x-ui.field>

                    @if ($online->shows('details'))
                        <x-ui.textarea name="details" label="Anything the nurse should know?" rows="3" maxlength="500" wrapper-class="col-full"
                            :required="$online->requires('details')" :optional="! $online->requires('details')" />
                    @endif
                </x-ui.section>

                @unless ($consentRequired)
                    <div class="pub-submit">
                        <p class="pub-submit-hint">
                            Your request is not final until the clinic approves it. By sending it you agree to the
                            <a href="{{ route('privacy') }}" target="_blank" rel="noopener">privacy notice<span class="visually-hidden"> (opens in a new tab)</span></a>.
                        </p>
                        <button type="submit" class="btn btn-primary" id="requestSubmit">
                            <x-ui.icon name="send" class="me-1" />Send request
                        </button>
                    </div>
                @endunless
            </x-ui.card>

            {{-- 3. Privacy --}}
            @if ($consentRequired)
                <x-ui.card class="pub-step">
                    <x-ui.section title="Privacy" description="Please read this before you send your request.">
                        <div>
                            <label for="f-consent" @class(['pub-consent-check', 'is-invalid' => $errors->has('consent')])>
                                <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" name="consent" value="1" id="f-consent"
                                       required @checked(old('consent'))
                                       @error('consent') aria-invalid="true" aria-describedby="f-consent-error" @enderror>
                                <span class="pub-consent-label">
                                    {{ $consentText !== '' ? $consentText : 'I agree that the school clinic may use the details in this request to schedule my visit and to contact me about it.' }}
                                    <span class="form-required" aria-hidden="true">*</span><span class="visually-hidden"> (required)</span>
                                </span>
                            </label>
                            <x-ui.field-error name="consent" id="f-consent-error" />
                            <p class="pub-consent-more">
                                <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Read the privacy notice<span class="visually-hidden"> (opens in a new tab)</span></a>
                            </p>
                        </div>
                    </x-ui.section>

                    <div class="pub-submit">
                        <p class="pub-submit-hint">Your request is not final until the clinic approves it.</p>
                        <button type="submit" class="btn btn-primary" id="requestSubmit">
                            <x-ui.icon name="send" class="me-1" />Send request
                        </button>
                    </div>
                </x-ui.card>
            @endif
        </div>
        <script type="application/json" id="bookingConfig">@json($config)</script>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Error summary: focus it on load, and move focus to the field when a message is clicked.
    var summary = document.getElementById('formErrors');
    if (summary) {
        summary.focus();
        summary.querySelectorAll('[data-error-link]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var el = document.getElementById(link.getAttribute('href').slice(1));
                if (!el) return;
                e.preventDefault();
                el.scrollIntoView({ block: 'center' });
                el.focus({ preventScroll: true });
            });
        });
    }

    var form = document.getElementById('requestForm');
    if (!form) return;
    var cfg = {};
    try { cfg = JSON.parse(document.getElementById('bookingConfig').textContent || '{}'); } catch (e) { cfg = {}; }

    // Replace a dropdown's choices. Searchable dropdowns read the native options,
    // so they are told when the list changed.
    function fill(sel, items, placeholder, keep) {
        sel.innerHTML = '';
        if (placeholder !== null) sel.appendChild(new Option(placeholder, ''));
        items.forEach(function (item) {
            var o = new Option(item.text, item.value);
            o.disabled = !!item.disabled;
            if (!item.disabled && item.value === keep) o.selected = true;
            sel.appendChild(o);
        });
        sel.dispatchEvent(new CustomEvent('options:change', { bubbles: true }));
    }

    // 1. Category: the grade, program and section choices that apply to it.
    var category = form.querySelector('[data-booking-category]');
    var school = form.querySelector('[data-school-block]');
    function listsFor(value) {
        var a = cfg.academic || {};
        if (!cfg.categoryShown) return a.flat || {};
        if (!value) return {};
        return (a.byCategory && a.byCategory[value]) || a.flat || {};
    }
    function rebuildSchool() {
        if (!school) return;
        var lists = listsFor(category ? category.value : '');
        var any = false;
        school.querySelectorAll('[data-academic-field]').forEach(function (sel) {
            var options = lists[sel.dataset.academicField] || [];
            var keep = sel.value;
            fill(sel, options.map(function (v) { return { value: v, text: v }; }), sel.dataset.placeholder, keep);
            sel.disabled = options.length === 0;
            var field = sel.closest('[data-school-field]');
            if (field) field.hidden = options.length === 0;
            if (options.length) any = true;
        });
        school.hidden = !any;
    }
    if (category) category.addEventListener('change', rebuildSchool);

    // 2. Reason: "Other..." asks for the reason in words.
    var purpose = form.querySelector('select[data-booking-purpose]');
    var otherWrap = form.querySelector('[data-other-wrap]');
    function syncOther() {
        if (!purpose || !otherWrap) return;
        var on = /^other\b/i.test(purpose.value.trim());
        var input = otherWrap.querySelector('input');
        otherWrap.hidden = !on;
        if (input) input.disabled = !on;
    }
    if (purpose) purpose.addEventListener('change', syncOther);

    // 3. Appointment with -> dates (specialist visit days); date -> open times.
    var provider = form.querySelector('[data-booking-provider]');
    var date = document.getElementById('f-appointment_date');
    var time = document.getElementById('f-appointment_time');
    var dateHelp = document.getElementById('dateHelp');
    var slotHelp = document.getElementById('slotHelp');
    var specialists = cfg.specialists || [];
    var lastLabel = dateHelp ? dateHelp.textContent.trim() : '';

    function dateText(d, specialist) {
        if (d.closed !== null) return d.label + ' (closed' + (d.closed ? ': ' + d.closed : '') + ')';
        if (d.open === 0) return d.label + ' (full)';
        if (specialist) return d.label + ' (' + specialist.toLowerCase() + ' visit)';
        return d.label + ' (' + d.open + (d.open === 1 ? ' time' : ' times') + ' open)';
    }

    function rebuildDates() {
        var p = provider ? provider.value : '';
        var specialist = specialists.indexOf(p) !== -1 ? p : '';
        var list = (cfg.dates || []).filter(function (d) { return !specialist || d.visits.indexOf(specialist) !== -1; });
        var items = list.map(function (d) {
            return { value: d.date, text: dateText(d, specialist), disabled: d.closed !== null || d.open === 0 };
        });
        var keep = date.value;
        var usable = items.filter(function (i) { return !i.disabled; });
        if (!usable.some(function (i) { return i.value === keep; })) keep = usable.length ? usable[0].value : '';
        fill(date, items, 'Choose a date', keep);
        if (dateHelp) {
            dateHelp.textContent = specialist && !list.length
                ? 'No ' + specialist.toLowerCase() + ' visit days are scheduled yet. Choose another option or call the clinic.'
                : (specialist ? 'Only the days the ' + specialist.toLowerCase() + ' visits are listed.' : lastLabel);
        }
        loadTimes();
    }

    var request = 0;
    async function loadTimes() {
        var mine = ++request;
        if (!date.value) {
            fill(time, [], 'Choose a date first', '');
            slotHelp.textContent = '';
            return;
        }
        slotHelp.textContent = 'Checking open times...';
        try {
            var url = cfg.slotsUrl + '?date=' + encodeURIComponent(date.value)
                + (provider && provider.value ? '&provider=' + encodeURIComponent(provider.value) : '');
            var res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error(res.status);
            var data = await res.json();
            if (mine !== request) return;
            var keep = time.value || time.dataset.current;
            var slots = (data.slots || []).filter(function (s) { return s.state === 'open' || s.state === 'full'; });
            fill(time, slots.map(function (s) {
                return { value: s.time, text: s.label + (s.available ? ' (' + s.remaining + ' left)' : ' (full)'), disabled: !s.available };
            }), 'Choose a time', keep);
            var open = slots.filter(function (s) { return s.available; }).length;
            slotHelp.textContent = !data.open ? (data.message || 'Choose another date.')
                : (open === 1 ? '1 time is open on this date.'
                : (open ? open + ' times are open on this date.' : (data.message || 'All times are taken on this date. Choose another date.')));
        } catch (e) {
            if (mine !== request) return;
            slotHelp.textContent = 'Could not load open times. You can still send the form and the clinic will check.';
        }
    }

    if (provider && specialists.length) provider.addEventListener('change', rebuildDates);
    if (date) date.addEventListener('change', loadTimes);
})();
</script>
@endpush
