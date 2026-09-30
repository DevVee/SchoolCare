@extends('layouts.public')

@section('title', 'Request an appointment')
@section('nav', 'request')

@php
    $dayNames = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
    $openDays = collect($week)->filter()->keys()->map(fn ($d) => $dayNames[$d] ?? ucfirst($d))->values();
    $openDaysText = $openDays->count() > 1 ? $openDays->slice(0, -1)->implode(', ').' and '.$openDays->last() : $openDays->implode('');
    $dateValue = old('appointment_date', $firstDate);
    $timeValue = (string) old('appointment_time', '');
    $clinicPhone = trim((string) settings('clinic_contact'));
    $purposeOptions = $purposes ? array_combine($purposes, $purposes) : [];
    $providerOptions = $providers ? array_combine($providers, $providers) : [];
    $timeError = $errors->has('appointment_time');
@endphp

@section('content')
<div class="pub-narrow">
    <header class="pub-head">
        <h1 class="pub-title">Request an appointment</h1>
        <p class="pub-intro">
            Send a request to the school clinic. The clinic staff will review it and confirm by text message.
            @if ($openDaysText !== '') The clinic takes appointments on {{ $openDaysText }}. @endif
        </p>
        <p class="pub-intro small">
            For emergencies, go to the clinic directly{{ $clinicPhone !== '' ? ' or call '.$clinicPhone : '' }}.
            <a href="{{ route('public.schedule') }}">See today's open times</a>
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

    <form method="POST" action="{{ route('public.appointments.store') }}" novalidate id="requestForm" class="pub-form"
          data-slots-url="{{ route('public.appointments.slots') }}">
        @csrf
        {{-- Honeypot: hidden from people, filled in by bots. --}}
        <div class="hp-field" aria-hidden="true">
            <label for="website">Leave this empty</label>
            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="pub-steps">
            <x-ui.card class="pub-step">
                <x-ui.section title="About you" description="So the clinic knows who is coming and how to reach you." columns="2">
                    <x-ui.input name="requester_name" label="Full name" required maxlength="150" autocomplete="name" wrapper-class="col-full" />
                    <x-ui.input name="requester_contact" type="tel" label="Mobile number" required maxlength="30" autocomplete="tel"
                        inputmode="tel" placeholder="09171234567" help="The confirmation is sent to this number." />
                    <x-ui.input name="requester_student_id" label="Student or employee ID" optional maxlength="50" autocomplete="off" />
                    <x-ui.input name="requester_email" type="email" label="Email" optional maxlength="150" autocomplete="email" wrapper-class="col-full" />
                </x-ui.section>
            </x-ui.card>

            <x-ui.card class="pub-step">
                <x-ui.section title="Your visit" description="Pick a reason, a date and one of the open times." columns="2">
                    @if ($purposeOptions)
                        <x-ui.select name="purpose" label="Reason for the visit" :options="$purposeOptions" placeholder="Choose a reason" required
                            :wrapper-class="$providerOptions ? null : 'col-full'" />
                    @else
                        <x-ui.input name="purpose" label="Reason for the visit" required maxlength="150"
                            :wrapper-class="$providerOptions ? null : 'col-full'" />
                    @endif
                    @if ($providerOptions)
                        <x-ui.select name="provider" label="See the" :options="$providerOptions" placeholder="No preference" optional />
                    @endif

                    <x-ui.input name="appointment_date" type="date" label="Preferred date" required
                        :value="$dateValue" :min="$minDate" :max="$maxDate" />

                    <x-ui.field label="Time" name="appointment_time" required>
                        <select name="appointment_time" id="f-appointment_time" required data-current="{{ $timeValue }}"
                                class="form-select @if ($timeError) is-invalid @endif"
                                aria-describedby="slotHelp{{ $timeError ? ' f-appointment_time-error' : '' }}"
                                @if ($timeError) aria-invalid="true" @endif>
                            <option value="">Choose a time</option>
                            @foreach ($slots as $s)
                                <option value="{{ $s['slot']->slot_time }}" @disabled(! $s['available']) @selected($timeValue === $s['slot']->slot_time)>
                                    {{ $s['slot']->display_label }}{{ $s['available'] ? ' ('.$s['remaining'].' left)' : ($s['past'] ? ' (time passed)' : ' (full)') }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text" id="slotHelp" aria-live="polite"></div>
                    </x-ui.field>

                    <x-ui.textarea name="details" label="Anything the nurse should know?" optional rows="3" maxlength="500" wrapper-class="col-full" />
                </x-ui.section>

                <div class="pub-submit">
                    <p class="pub-submit-hint">Your request is not final until the clinic approves it.</p>
                    <button type="submit" class="btn btn-primary" id="requestSubmit">
                        <x-ui.icon name="send" class="me-1" />Send request
                    </button>
                </div>
            </x-ui.card>
        </div>
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

    // Open times: reload the time choices when the date changes.
    var form = document.getElementById('requestForm');
    var date = document.getElementById('f-appointment_date');
    var time = document.getElementById('f-appointment_time');
    var help = document.getElementById('slotHelp');

    async function load() {
        if (!date.value) return;
        help.textContent = 'Checking open times...';
        try {
            var res = await fetch(form.dataset.slotsUrl + '?date=' + encodeURIComponent(date.value), { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error(res.status);
            var data = await res.json();
            var keep = time.value || time.dataset.current;
            time.innerHTML = '<option value="">Choose a time</option>';
            if (!data.open) {
                help.textContent = 'The clinic does not take appointments on this date. Choose another date.';
                return;
            }
            data.slots.forEach(function (s) {
                var o = document.createElement('option');
                o.value = s.time;
                o.textContent = s.label + (s.available ? ' (' + s.remaining + ' left)' : (s.remaining > 0 ? ' (time passed)' : ' (full)'));
                o.disabled = !s.available;
                if (s.available && s.time === keep) o.selected = true;
                time.appendChild(o);
            });
            var open = data.slots.filter(function (s) { return s.available; }).length;
            help.textContent = open === 1 ? '1 time is open on this date.'
                : (open ? open + ' times are open on this date.' : 'All times are full on this date. Choose another date.');
        } catch (e) {
            help.textContent = 'Could not load open times. You can still send the form and the clinic will check.';
        }
    }

    date.addEventListener('change', load);
})();
</script>
@endpush
