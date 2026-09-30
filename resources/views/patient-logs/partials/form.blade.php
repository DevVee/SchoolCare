{{--
    Clinic visit form, shared by create and edit.
    Expects: $patients, $medicines (dispensable, with usable_quantity / next_expiry),
             $log (PatientLog|null), $selectedPatient (create only),
             $submitLabel, $cancelUrl.
--}}
@php
    $log        = $log ?? null;
    $isEdit     = $log !== null;
    $vitals     = $log?->vital_signs ?? [];
    $severities = \App\Models\PatientLog::severities();
    if ($log?->severity && ! in_array($log->severity, $severities, true)) {
        $severities[] = $log->severity;
    }
    $reasonOptions = \App\Models\PatientLog::reasonOptions();
    foreach ((array) ($log?->reasons ?? []) as $r) {
        if (! in_array($r, $reasonOptions, true)) {
            $reasonOptions[] = $r;
        }
    }
    $checkedReasons = (array) old('reasons', $log?->reasons ?? []);
    $otherReason    = old('other_reason', $log?->other_reason);

    $dispositions = \App\Models\PatientLog::dispositions();
    if ($log?->disposition && ! isset($dispositions[$log->disposition])) {
        $dispositions[$log->disposition] = $log->disposition_label;
    }
    $currentDisposition = old('disposition', $log?->disposition ?? array_key_first($dispositions));

    $vitalErrors = $errors->hasAny(['vital_temp', 'vital_bp', 'vital_pulse', 'vital_weight', 'vital_height']);
    $showVitals  = $vitals || $vitalErrors || filled(old('vital_temp')) || filled(old('vital_bp')) || filled(old('vital_pulse'));

    $oldMedicineRows = collect(old('medicines', []))->filter(fn ($r) => is_array($r))->values();
    $submitLabel ??= 'Save';
    $cancelUrl   ??= route('patient-logs.index');
@endphp

<x-ui.card padding="lg">

    {{-- Patient --}}
    <x-ui.section title="Patient" description="Who came to the clinic.">
        <x-ui.field label="Patient" name="patient_id" for="patientSelect" required>
            <select name="patient_id" id="patientSelect" required
                    @class(['form-select', 'is-invalid' => $errors->has('patient_id')])
                    @error('patient_id') aria-invalid="true" aria-describedby="patientSelect-error" @enderror>
                <option value="">Select a patient</option>
                @foreach($patients as $p)
                <option value="{{ $p->id }}"
                        data-guardian="{{ $p->guardian_name }}"
                        data-guardian-contact="{{ $p->guardian_contact }}"
                        data-first-name="{{ $p->first_name }}"
                        data-category="{{ ucwords(str_replace('_', ' ', $p->category)) }}"
                        data-year="{{ $p->year_level }}"
                        data-section="{{ $p->section }}"
                        @selected(old('patient_id', $log?->patient_id ?? ($selectedPatient ?? null)) == $p->id)>
                    {{ $p->last_name }}, {{ $p->first_name }}{{ $p->middle_name ? ' '.mb_substr($p->middle_name, 0, 1).'.' : '' }}
                    ({{ $p->patient_number }}){{ method_exists($p, 'trashed') && $p->trashed() ? ' (archived)' : '' }}
                </option>
                @endforeach
            </select>
        </x-ui.field>

        <div id="patientInfo" class="small text-ink-2 mt-2 d-none" aria-live="polite">
            <div><span id="patientInfoText"></span></div>
            <div id="guardianInfo" class="d-none">
                Guardian: <span id="guardianName">-</span>
                <span id="guardianContact" class="text-muted"></span>
            </div>
        </div>
    </x-ui.section>

    {{-- Date and time --}}
    <x-ui.section title="Date and time" description="Leave time out empty while the patient is still in the clinic. Use Discharge on the logbook when they leave.">
        <div class="row g-3">
            <x-ui.input wrapper-class="col-12 col-sm-4" type="date" name="log_date" id="logDate" label="Visit date" required
                :value="$log?->log_date?->toDateString() ?? today()->toDateString()" max="{{ today()->toDateString() }}" />
            <x-ui.input wrapper-class="col-6 col-sm-4" type="time" name="time_in" id="timeIn" label="Time in" required
                :value="$log ? \Carbon\Carbon::parse($log->time_in)->format('H:i') : now()->format('H:i')" />
            <x-ui.input wrapper-class="col-6 col-sm-4" type="time" name="time_out" id="timeOut" label="Time out" optional
                :value="$log?->time_out ? \Carbon\Carbon::parse($log->time_out)->format('H:i') : ''" />
        </div>
    </x-ui.section>

    {{-- Reasons and severity --}}
    <x-ui.section title="Reason for visit" description="Pick all that apply.">
        <fieldset class="mb-3">
            <legend class="form-label">Reasons<span class="form-required" aria-hidden="true">*</span><span class="visually-hidden"> (required)</span></legend>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-0" id="reasonList">
                @foreach($reasonOptions as $i => $reason)
                <div class="col">
                    <x-ui.checkbox name="reasons[]" :value="$reason" :id="'reason_'.$i" :label="$reason"
                        :checked="in_array($reason, $checkedReasons, true)" />
                </div>
                @endforeach
                <div class="col">
                    <div class="form-check c-check">
                        <input class="form-check-input" type="checkbox" id="reasonOther" aria-controls="otherReasonWrap" @checked(filled($otherReason))>
                        <label class="form-check-label" for="reasonOther">Other</label>
                    </div>
                </div>
            </div>
            <x-ui.field-error name="reasons" class="mt-1" />
            <x-ui.field-error name="reasons.*" class="mt-1" />
        </fieldset>

        <div class="mb-3 {{ filled($otherReason) ? '' : 'd-none' }}" id="otherReasonWrap">
            <x-ui.input name="other_reason" id="otherReason" label="Other reason" maxlength="255"
                placeholder="Type the reason" :value="$log?->other_reason" />
        </div>

        <fieldset class="mb-3">
            <legend class="form-label">Severity @unless($isEdit)<span class="form-required" aria-hidden="true">*</span><span class="visually-hidden"> (required)</span>@endunless</legend>
            <div class="d-flex flex-wrap column-gap-4">
                @foreach($severities as $i => $sev)
                    <x-ui.checkbox type="radio" name="severity" :value="$sev" :id="'sev_'.$i" :label="$sev"
                        :checked="$log?->severity === $sev" />
                @endforeach
            </div>
            <x-ui.field-error name="severity" class="mt-1" />
        </fieldset>

        <x-ui.textarea name="chief_complaint" id="chiefComplaint" label="Complaint details" optional rows="2" maxlength="1000"
            placeholder="What the patient says, when it started, anything else worth noting" :value="$log?->chief_complaint" />
    </x-ui.section>

    {{-- Vital signs (optional, collapsed until needed) --}}
    <x-ui.section title="Vital signs" description="Optional.">
        <x-ui.button variant="secondary" size="sm" icon="thermometer-half" :class="$showVitals ? 'd-none' : ''"
            id="vitalsToggle" data-bs-toggle="collapse" data-bs-target="#vitalsPanel"
            aria-expanded="{{ $showVitals ? 'true' : 'false' }}" aria-controls="vitalsPanel">Record vital signs</x-ui.button>
        <div class="collapse {{ $showVitals ? 'show' : '' }}" id="vitalsPanel">
            <div class="row g-3">
                <x-ui.input wrapper-class="col-6 col-sm-4" type="number" name="vital_temp" id="vTemp" step="0.1" label="Temperature (°C)"
                    placeholder="37.2" :value="$vitals['temperature'] ?? ''" />
                <x-ui.input wrapper-class="col-6 col-sm-4" name="vital_bp" id="vBp" label="Blood pressure"
                    placeholder="120/80" :value="$vitals['blood_pressure'] ?? ''" />
                <x-ui.input wrapper-class="col-6 col-sm-4" type="number" name="vital_pulse" id="vPulse" label="Pulse (bpm)"
                    placeholder="72" :value="$vitals['pulse'] ?? ''" />
                <x-ui.input wrapper-class="col-6 col-sm-4" type="number" name="vital_weight" id="vWeight" step="0.1" label="Weight (kg)"
                    placeholder="50" :value="$vitals['weight'] ?? ''" />
                <x-ui.input wrapper-class="col-6 col-sm-4" type="number" name="vital_height" id="vHeight" step="0.1" label="Height (cm)"
                    placeholder="160" :value="$vitals['height'] ?? ''" />
            </div>
        </div>
    </x-ui.section>

    {{-- Assessment and treatment --}}
    <x-ui.section title="Assessment and treatment">
        <div class="vstack gap-3">
            <x-ui.textarea name="assessment" id="assessment" label="Assessment" optional rows="2"
                placeholder="Findings and observations" :value="$log?->assessment" />
            <x-ui.textarea name="treatment" id="treatment" label="Treatment or action taken" optional rows="2"
                placeholder="First aid given, advice, referral" :value="$log?->treatment" />
        </div>
    </x-ui.section>

    {{-- Medicines given --}}
    <x-ui.section title="Medicines given" description="Deducted from stock, earliest expiry first.">
        @if($isEdit && $log->dispensingRecords->isNotEmpty())
        <div class="mb-3">
            <p class="form-label mb-1">Already given</p>
            <ul class="list-unstyled border rounded mb-1">
                @foreach($log->dispensingRecords as $rec)
                <li class="d-flex justify-content-between gap-2 px-3 py-2 small {{ $loop->first ? '' : 'border-top' }}">
                    <span>{{ $rec->medicine?->name ?? 'Removed medicine' }}</span>
                    <span class="fw-semibold tabular">{{ $rec->quantity }} {{ $rec->medicine?->unit }}(s)</span>
                </li>
                @endforeach
            </ul>
            <div class="form-text">Medicines already given cannot be changed here. Add another row if more was given.</div>
        </div>
        @endif

        @if($medicines->isEmpty())
            <p class="text-muted small mb-0">No medicine is available to give right now (none in stock or all expired).</p>
        @else
        <div id="medicineRows" data-next-index="{{ max(0, $oldMedicineRows->count()) }}">
            @foreach($oldMedicineRows as $i => $row)
                @include('patient-logs.partials.medicine-row', ['index' => $i, 'row' => $row])
            @endforeach
        </div>
        <x-ui.field-error name="medicines" class="mb-2" />
        <x-ui.button variant="secondary" size="sm" icon="plus-lg" id="addMedicineRow">Add medicine</x-ui.button>
        <template id="medicineRowTemplate">
            @include('patient-logs.partials.medicine-row', ['index' => '__INDEX__', 'row' => []])
        </template>
        @endif
    </x-ui.section>

    {{-- Outcome (disposition) --}}
    <x-ui.section title="Outcome" description="Where the patient went after the visit.">
        <fieldset>
            <legend class="visually-hidden">Outcome</legend>
            <div class="row row-cols-1 row-cols-sm-2 g-0">
                @foreach($dispositions as $val => $label)
                <div class="col">
                    <x-ui.checkbox type="radio" name="disposition" :value="$val" :id="'disp_'.$val" :label="$label"
                        :checked="$currentDisposition === $val" />
                </div>
                @endforeach
            </div>
            <x-ui.field-error name="disposition" class="mt-1" />
        </fieldset>
    </x-ui.section>

    {{-- SMS to guardian (create only; hidden when turned off in Settings) --}}
    @if (! $isEdit && settings('sms_enabled') && settings('sms_log_guardian_enabled'))
    <x-ui.section title="Text the guardian" description="Sent to the guardian number on the patient record. Nothing is sent if there is no number."
        id="smsCard"
        data-template="{{ app(\App\Services\SmsService::class)->render('sms_template_clinic_log', ['guardian' => '{guardian}', 'name' => '{name}', 'full_name' => '{full_name}', 'date' => '{date}', 'time' => '{time}', 'complaint' => '{complaint}', 'treatment' => '{treatment}', 'disposition' => '{disposition}']) }}">
        <x-ui.switch name="sms_guardian" id="smsToggle" label="Send a text message to the guardian" :checked="false" />
        <div class="collapse {{ old('sms_guardian') ? 'show' : '' }}" id="smsPanel">
            <div class="border rounded bg-surface-2 p-3 mt-2 small">
                <p class="overline mb-1">Message preview</p>
                <span id="smsPreviewText">Select a patient to see the message.</span>
            </div>
        </div>
    </x-ui.section>
    @endif

    {{-- Notes --}}
    <x-ui.section title="Additional notes">
        <x-ui.textarea name="notes" id="notes" label="Notes" optional rows="2"
            placeholder="Follow-up instructions or other notes" :value="$log?->notes" />
    </x-ui.section>

    <div class="save-bar">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancel</x-ui.button>
        <x-ui.button type="submit" icon="check-lg">{{ $submitLabel }}</x-ui.button>
    </div>
</x-ui.card>

@push('scripts')
<script>
(function () {
    const patientSelect = document.getElementById('patientSelect');
    const patientInfo   = document.getElementById('patientInfo');
    const patientInfoTx = document.getElementById('patientInfoText');
    const guardianInfo  = document.getElementById('guardianInfo');
    const guardianName  = document.getElementById('guardianName');
    const guardianCont  = document.getElementById('guardianContact');
    const smsToggle     = document.getElementById('smsToggle');
    const smsPanel      = document.getElementById('smsPanel');
    const smsPreview    = document.getElementById('smsPreviewText');

    // Patient summary
    function updatePatientInfo() {
        const opt = patientSelect.selectedOptions[0];
        if (!opt || !opt.value) { patientInfo.classList.add('d-none'); return; }
        const parts = [opt.dataset.category, opt.dataset.year, opt.dataset.section].filter(Boolean);
        patientInfoTx.textContent = parts.join(', ') || 'No category on record';
        const gName = opt.dataset.guardian || '';
        const gCont = opt.dataset.guardianContact || '';
        if (gName || gCont) {
            guardianName.textContent = gName || 'Not recorded';
            guardianCont.textContent = gCont ? '(' + gCont + ')' : '(no number on record)';
            guardianInfo.classList.remove('d-none');
        } else {
            guardianInfo.classList.add('d-none');
        }
        patientInfo.classList.remove('d-none');
        updateSmsPreview();
    }
    patientSelect.addEventListener('change', updatePatientInfo);

    // "Other" reason
    const otherToggle = document.getElementById('reasonOther');
    const otherWrap   = document.getElementById('otherReasonWrap');
    const otherInput  = document.getElementById('otherReason');
    otherToggle.addEventListener('change', function () {
        otherWrap.classList.toggle('d-none', !this.checked);
        if (this.checked) { otherInput.focus(); } else { otherInput.value = ''; }
        updateSmsPreview();
    });

    // Vital signs: hide the "Record" button once the fields are open
    const vitalsToggle = document.getElementById('vitalsToggle');
    document.getElementById('vitalsPanel').addEventListener('shown.bs.collapse', function () {
        vitalsToggle.classList.add('d-none');
        document.getElementById('vTemp').focus();
    });

    function reasonSummary() {
        const picked = Array.from(document.querySelectorAll('input[name="reasons[]"]:checked')).map(el => el.value);
        if (otherInput.value.trim()) picked.push(otherInput.value.trim());
        return picked.length ? picked.join(', ') : (document.getElementById('chiefComplaint').value.trim() || '-');
    }

    // SMS preview (create page, when SMS is on)
    function formatTime(t) {
        const [h, m] = t.split(':');
        return ((+h % 12) || 12) + ':' + m + ' ' + (+h >= 12 ? 'PM' : 'AM');
    }
    function updateSmsPreview() {
        if (!smsPreview) return;
        const opt = patientSelect.selectedOptions[0];
        if (!opt || !opt.value) return;
        const timeIn = document.getElementById('timeIn').value;
        const dispEl = document.querySelector('input[name="disposition"]:checked');
        const vars = {
            guardian: opt.dataset.guardian || 'Parent/Guardian',
            name: opt.dataset.firstName || '',
            full_name: opt.dataset.firstName || '',
            time: timeIn ? formatTime(timeIn) : '-',
            date: new Date().toLocaleDateString('en-US', { month: 'long', day: '2-digit', year: 'numeric' }),
            complaint: reasonSummary(),
            treatment: document.getElementById('treatment').value || 'Attended by clinic staff',
            disposition: dispEl ? (document.querySelector('label[for="' + dispEl.id + '"]')?.textContent.trim() || '') : '',
        };
        const tpl = document.getElementById('smsCard').dataset.template || '';
        smsPreview.textContent = tpl.replace(/\{([a-z_]+)\}/g, (m, k) => (k in vars ? vars[k] : m));
    }
    document.getElementById('logForm').addEventListener('input', updateSmsPreview);
    document.getElementById('logForm').addEventListener('change', updateSmsPreview);
    smsToggle?.addEventListener('change', function () {
        bootstrap.Collapse.getOrCreateInstance(smsPanel, { toggle: false })[this.checked ? 'show' : 'hide']();
        updateSmsPreview();
    });

    // Medicine rows
    const rows     = document.getElementById('medicineRows');
    const addBtn   = document.getElementById('addMedicineRow');
    const template = document.getElementById('medicineRowTemplate');
    const MAX_ROWS = {{ \App\Http\Requests\PatientLog\SavePatientLogRequest::MAX_MEDICINE_ROWS }};

    function refreshRow(row) {
        const sel  = row.querySelector('select');
        const qty  = row.querySelector('input[type="number"]');
        const help = row.querySelector('.medicine-stock');
        const opt  = sel.selectedOptions[0];
        if (opt && opt.value) {
            qty.max = opt.dataset.available;
            help.textContent = opt.dataset.available + ' ' + opt.dataset.unit + '(s) usable'
                + (opt.dataset.expiry ? ', next expiry ' + opt.dataset.expiry : '');
        } else {
            qty.removeAttribute('max');
            help.textContent = '';
        }
    }
    function bindRow(row) {
        row.querySelector('select').addEventListener('change', () => refreshRow(row));
        row.querySelector('.remove-medicine').addEventListener('click', () => {
            row.remove();
            addBtn.disabled = rows.children.length >= MAX_ROWS;
            addBtn.focus();
        });
        refreshRow(row);
    }
    if (rows && addBtn && template) {
        rows.querySelectorAll('.medicine-row').forEach(bindRow);
        addBtn.addEventListener('click', function () {
            if (rows.children.length >= MAX_ROWS) return;
            const index = rows.dataset.nextIndex++;
            const html  = template.innerHTML.replaceAll('__INDEX__', index);
            rows.insertAdjacentHTML('beforeend', html);
            const row = rows.lastElementChild;
            bindRow(row);
            row.querySelector('select').focus();
            addBtn.disabled = rows.children.length >= MAX_ROWS;
        });
    }

    if (patientSelect.value) updatePatientInfo();
})();
</script>
@endpush
