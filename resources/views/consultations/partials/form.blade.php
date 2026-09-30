{{--
    Consultation form fields, shared by create and edit.
    Create expects: $patients, $selectedPatient. Edit expects: $consultation, $appointments.
    Both: $submitLabel, $cancelUrl.
--}}
@php
    $consultation ??= null;
    $isEdit = $consultation !== null;
@endphp

<x-ui.card padding="lg">
    <x-ui.section title="Visit" description="Who was seen and when.">
        <div class="row g-3">
            @if ($isEdit)
                <div class="col-12">
                    <x-ui.field label="Patient" for="patientName" help="The patient cannot be changed after the consultation is saved.">
                        <input type="hidden" name="patient_id" value="{{ $consultation->patient_id }}">
                        <input type="text" id="patientName" class="form-control" readonly
                               value="{{ $consultation->patient->full_name }} ({{ $consultation->patient->patient_number }})">
                    </x-ui.field>
                </div>
                <x-ui.select wrapper-class="col-12" name="appointment_id" label="Linked appointment" optional
                    placeholder="None (walk-in)" :selected="$consultation->appointment_id"
                    :options="$appointments->mapWithKeys(fn ($appt) => [$appt->id => \App\Support\DisplayFormat::date($appt->appointment_date).', '.\Illuminate\Support\Str::limit($appt->purpose, 30).' ('.ucfirst(str_replace('_', ' ', $appt->status)).')'])->all()" />
            @else
                <x-ui.select wrapper-class="col-12" name="patient_id" id="patientSelect" label="Patient" required
                    placeholder="Select a patient" :selected="$selectedPatient"
                    :options="$patients->mapWithKeys(fn ($p) => [$p->id => $p->last_name.', '.$p->first_name.($p->middle_name ? ' '.mb_substr($p->middle_name, 0, 1).'.' : '').' ('.$p->patient_number.')'])->all()" />
                <x-ui.select wrapper-class="col-12" name="appointment_id" id="appointmentSelect" label="Linked appointment" optional
                    placeholder="None (walk-in)" :options="[]" help="Linking an appointment marks it as completed." />
            @endif
            <x-ui.input wrapper-class="col-12 col-sm-6" type="date" name="visit_date" label="Visit date" required
                :value="$isEdit ? $consultation->visit_date->toDateString() : today()->toDateString()" />
            <x-ui.input wrapper-class="col-12 col-sm-6" type="time" name="visit_time" label="Visit time" optional
                :value="$isEdit ? ($consultation->visit_time ? \Carbon\Carbon::parse($consultation->visit_time)->format('H:i') : '') : now()->format('H:i')" />
        </div>
    </x-ui.section>

    <x-ui.section title="Clinical notes">
        <div class="row g-3">
            <x-ui.textarea wrapper-class="col-12" name="chief_complaint" label="Chief complaint" required rows="3"
                placeholder="Main reason for the visit" :value="$consultation?->chief_complaint" />
            <x-ui.textarea wrapper-class="col-12" name="assessment" label="Assessment and findings" optional rows="3"
                placeholder="Physical examination findings" :value="$consultation?->assessment" />
            <x-ui.input wrapper-class="col-12 col-md-6" name="diagnosis" label="Diagnosis" optional
                placeholder="For example: acute upper respiratory infection" :value="$consultation?->diagnosis" />
            <x-ui.input wrapper-class="col-12 col-md-6" name="treatment" label="Treatment or medicine given" optional
                placeholder="For example: Paracetamol 500 mg" :value="$consultation?->treatment" />
            <x-ui.textarea wrapper-class="col-12" name="notes" label="Additional notes" optional rows="2"
                placeholder="Follow-up or referral" :value="$consultation?->notes" />
        </div>
    </x-ui.section>

    <div class="save-bar">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancel</x-ui.button>
        <x-ui.button type="submit" icon="check-lg">{{ $submitLabel }}</x-ui.button>
    </div>
</x-ui.card>
