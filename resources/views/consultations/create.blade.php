@extends('layouts.app')

@section('title', 'New Consultation')

@section('content')
<x-ui.page-header title="New consultation" description="Record a patient visit with clinical notes."
    :back="route('consultations.index')" back-label="Back to consultations" />

@if ($errors->any())
    <x-ui.alert variant="danger" title="Please fix the errors below" class="mb-3">Nothing was saved.</x-ui.alert>
@endif

<form method="POST" action="{{ route('consultations.store') }}">
    @csrf
    @include('consultations.partials.form', [
        'consultation' => null,
        'submitLabel' => 'Save consultation',
        'cancelUrl' => route('consultations.index'),
    ])
</form>
@endsection

@push('scripts')
<script>
const apptByPatient = @json($appointmentOptions);
const apptSelect    = document.getElementById('appointmentSelect');
const oldAppt       = @json((string) old('appointment_id', $selectedAppointment));

function populateAppointments(patientId) {
    apptSelect.innerHTML = '<option value="">None (walk-in)</option>';
    (apptByPatient[patientId] || []).forEach(a => {
        const opt  = document.createElement('option');
        opt.value  = a.id;
        opt.textContent = a.label; // formatted server-side (avoids "Invalid Date")
        if (String(a.id) === oldAppt) opt.selected = true;
        apptSelect.appendChild(opt);
    });
}

const patientSel = document.getElementById('patientSelect');
patientSel.addEventListener('change', function () { populateAppointments(this.value); });

// Fill the list on load when a patient is already selected
if (patientSel.value) populateAppointments(patientSel.value);
</script>
@endpush
