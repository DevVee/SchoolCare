{{--
    x-ui.patient-picker: search patients by name, patient number or school ID (x-ui.combobox
    + route patients.lookup). Each result shows the full name, the school placement (course,
    year, section; the category for staff and visitors) and the patient number. The form
    submits the patient id under `name`, like the old <select>.

    <x-ui.patient-picker name="patient_id" id="patientSelect" required
        :value="$log?->patient_id ?? $selectedPatient" :patient="$log?->patient" />

    value:   id to pre-select (old input wins). Only active, non-archived patients are
             pre-selected, the same patients the search offers.
    patient: a patient that stays selectable anyway (edit forms keep an inactive or
             archived current patient).
--}}
@props([
    'name' => 'patient_id',
    'value' => null,
    'patient' => null,
    'label' => 'Patient',
    'id' => null,
    'placeholder' => 'Search by name or ID number',
    'help' => null,
    'required' => false,
    'optional' => false,
    'wrapperClass' => null,
])
@php
    $errorKey  = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $currentId = old($errorKey, $value ?? $patient?->getKey());
    $chosen    = null;
    if (is_scalar($currentId) && ctype_digit((string) $currentId)) {
        $chosen = $patient && (string) $patient->getKey() === (string) $currentId
            ? $patient
            : \App\Models\Patient::active()->select(\App\Models\Patient::PICKER_COLUMNS)->find((int) $currentId);
    }
    $minChars = \App\Http\Controllers\PatientLookupController::MIN_CHARS;
@endphp
<x-ui.combobox :name="$name" :label="$label" :id="$id" :placeholder="$placeholder" :help="$help"
    :required="$required" :optional="$optional" :wrapper-class="$wrapperClass"
    :source="route('patients.lookup')" :selected="$chosen?->toPickerItem()" :min-chars="$minChars"
    nouns="patients" :hint="'Type at least '.$minChars.' letters of a name, or an ID number.'"
    clear-label="Change patient" />
