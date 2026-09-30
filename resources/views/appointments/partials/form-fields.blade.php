{{--
    Shared appointment fields (create + edit).
    Expects: $patients, $timeSlots, $providers, $specialistVisits,
             $appointment (edit) or $selected / $presetDate / $presetVisit (create).
    The slot list shows the places left for the chosen date (appointments.availability).
--}}
@use('App\Support\DisplayFormat')
@php
    $appointment   = $appointment ?? null;
    $patientValue  = old('patient_id', $appointment?->patient_id ?? ($selected ?? null));
    $dateValue     = old('appointment_date', $appointment?->appointment_date?->format('Y-m-d') ?? ($presetDate ?? now()->format('Y-m-d')));
    $timeValue     = old('appointment_time', $appointment?->appointment_time);
    $providerValue = old('provider', $appointment?->provider);
    $visitValue    = (int) old('specialist_visit_id', $appointment?->specialist_visit_id ?? ($presetVisit ?? 0));
    $unlinked      = $appointment && $appointment->patient_id === null;
    if ($providerValue && ! in_array($providerValue, $providers, true)) {
        $providers[] = $providerValue;
    }
    $categoryLabels = \App\Models\Patient::categoryLabels();
@endphp

<div id="apptFields" data-availability="{{ route('appointments.availability') }}">
    <x-ui.section title="Patient and time" columns="3">
        <x-ui.field label="Patient" name="patient_id" for="patientSelect" :required="! $unlinked" class="col-full">
            <select name="patient_id" id="patientSelect" class="form-select @error('patient_id') is-invalid @enderror" @required(! $unlinked)>
                <option value="">{{ $unlinked ? 'Not linked yet (online request)' : 'Search and select a patient' }}</option>
                @foreach ($patients as $patient)
                    <option value="{{ $patient->id }}" @selected((string) $patientValue === (string) $patient->id)>
                        {{ $patient->last_name }}, {{ $patient->first_name }} ({{ $patient->patient_number }}),
                        {{ $categoryLabels[$patient->category] ?? $patient->category }}
                    </option>
                @endforeach
            </select>
        </x-ui.field>

        <x-ui.field label="Date" name="appointment_date" for="apptDate" required>
            <input type="date" name="appointment_date" id="apptDate"
                   class="form-control @error('appointment_date') is-invalid @enderror"
                   value="{{ $dateValue }}" @if (! $appointment) min="{{ now()->format('Y-m-d') }}" @endif required>
        </x-ui.field>

        <x-ui.field label="Time" name="appointment_time" for="apptTime" required>
            <select name="appointment_time" id="apptTime" data-current="{{ $timeValue }}"
                    class="form-select @error('appointment_time') is-invalid @enderror" required aria-describedby="apptTimeHelp">
                <option value="">Select a time</option>
                @foreach ($timeSlots as $slot)
                    <option value="{{ $slot->slot_time }}" @selected($timeValue === $slot->slot_time)>
                        {{ $slot->display_label }} (up to {{ $slot->max_appointments }})
                    </option>
                @endforeach
            </select>
            <div class="form-text" id="apptTimeHelp" aria-live="polite">Places left are shown once a date is chosen.</div>
        </x-ui.field>

        @if ($providers)
            <x-ui.field label="Appointment with" name="provider" for="apptProvider" optional>
                <select name="provider" id="apptProvider" class="form-select @error('provider') is-invalid @enderror">
                    <option value="">Not specified</option>
                    @foreach ($providers as $prov)
                        <option value="{{ $prov }}" @selected($providerValue === $prov)>{{ $prov }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        @endif
    </x-ui.section>

    <x-ui.section title="Visit details" columns="2">
        <x-ui.field label="Purpose of visit" name="purpose" for="apptPurpose" required class="col-full">
            <input type="text" name="purpose" id="apptPurpose" maxlength="500"
                   class="form-control @error('purpose') is-invalid @enderror"
                   placeholder="e.g. General check-up, fever, wound dressing"
                   value="{{ old('purpose', $appointment?->purpose) }}" required>
        </x-ui.field>

        <x-ui.field label="Specialist visit" name="specialist_visit_id" for="apptVisit" optional
            help="Only visits on the chosen date can be selected." class="col-full">
            <select name="specialist_visit_id" id="apptVisit" class="form-select @error('specialist_visit_id') is-invalid @enderror">
                <option value="">None</option>
                @foreach ($specialistVisits as $visit)
                    <option value="{{ $visit->id }}" data-date="{{ $visit->visit_date->toDateString() }}" data-type="{{ $visit->type }}"
                            @selected($visitValue === $visit->id)>
                        {{ DisplayFormat::date($visit->visit_date) }}: {{ $visit->type }}, {{ $visit->specialist_name }} ({{ $visit->time_range }})
                    </option>
                @endforeach
            </select>
        </x-ui.field>

        <x-ui.field label="Notes" name="notes" for="apptNotes" optional class="col-full">
            <textarea name="notes" id="apptNotes" rows="3" maxlength="2000"
                      class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $appointment?->notes) }}</textarea>
        </x-ui.field>
    </x-ui.section>
</div>

@push('scripts')
<script>
(function () {
    const wrap = document.getElementById('apptFields');
    if (!wrap) return;
    const date = document.getElementById('apptDate');
    const time = document.getElementById('apptTime');
    const help = document.getElementById('apptTimeHelp');
    const visit = document.getElementById('apptVisit');
    const provider = document.getElementById('apptProvider');
    const labels = {};
    Array.from(time.options).forEach(o => { if (o.value) labels[o.value] = o.textContent.trim(); });

    function filterVisits() {
        if (!visit) return;
        Array.from(visit.options).forEach(o => {
            if (!o.value) return;
            const ok = o.dataset.date === date.value;
            o.disabled = !ok;
            o.hidden = !ok;
            if (!ok && o.selected) visit.value = '';
        });
    }

    async function loadAvailability() {
        filterVisits();
        if (!date.value) return;
        help.textContent = 'Checking places left...';
        try {
            const res = await fetch(wrap.dataset.availability + '?date=' + encodeURIComponent(date.value), { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            const current = time.value || time.dataset.current;
            const byTime = {};
            data.slots.forEach(s => { byTime[s.time] = s; });
            Array.from(time.options).forEach(o => {
                if (!o.value) return;
                const s = byTime[o.value];
                if (!s) {
                    o.textContent = labels[o.value] + ' (not offered this day)';
                    o.disabled = o.value !== current;
                } else {
                    o.textContent = s.label + (s.available ? ' (' + s.remaining + ' left)' : (s.remaining > 0 ? ' (time passed)' : ' (full)'));
                    o.disabled = !s.available && o.value !== current;
                }
            });
            const open = data.slots.filter(s => s.available).length;
            help.textContent = open ? open + ' time slot(s) have places left on this date.' : 'No places left on this date. Choose another date.';
        } catch (e) {
            help.textContent = 'Could not check places left. The server checks again when you save.';
        }
    }

    if (visit && provider) {
        visit.addEventListener('change', () => {
            const o = visit.selectedOptions[0];
            if (o && o.dataset.type && !provider.value) {
                const match = Array.from(provider.options).find(p => p.value === o.dataset.type);
                if (match) provider.value = match.value;
            }
        });
    }
    date.addEventListener('change', loadAvailability);
    loadAvailability();
})();
</script>
@endpush
