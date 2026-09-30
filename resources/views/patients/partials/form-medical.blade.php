{{-- Health information section (patients.create / patients.edit) --}}
@php
    $bloodTypes = $bloodTypes ?? \App\Models\Patient::bloodTypes();
    $bloodOptions = array_combine($bloodTypes, $bloodTypes) ?: [];
@endphp
<x-ui.section title="Health information" description="Allergies and conditions show on the patient profile and when logging a visit." columns="3">
    <x-ui.select name="blood_type" label="Blood type" :options="$bloodOptions" placeholder="Unknown" :selected="$patient->blood_type ?? null" />
    <x-ui.input name="pediatrician_name" label="Pediatrician or family doctor" optional :value="$patient->pediatrician_name ?? null" />
    <x-ui.input name="pediatrician_contact" type="tel" label="Doctor's contact number" optional :value="$patient->pediatrician_contact ?? null" />

    <x-ui.textarea name="allergies" label="Known allergies" rows="3" wrapper-class="col-full"
        placeholder="Drug, food or environmental allergies" :value="$patient->allergies ?? null" />
    <x-ui.textarea name="medical_conditions" label="Existing medical conditions" rows="3" wrapper-class="col-full"
        placeholder="e.g. Asthma, hypertension, diabetes" :value="$patient->medical_conditions ?? null" />
    <x-ui.textarea name="current_medications" label="Current medications" rows="3" wrapper-class="col-full"
        placeholder="Maintenance medicines and doses" :value="$patient->current_medications ?? null" />
    <x-ui.textarea name="notes" label="Additional notes" rows="3" wrapper-class="col-full" optional
        placeholder="Any other relevant health information" :value="$patient->notes ?? null" />
</x-ui.section>
