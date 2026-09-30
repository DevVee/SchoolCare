@extends('layouts.app')

@section('title', 'Edit patient: ' . $patient->full_name)

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Edit patient" :description="$patient->full_name.', '.$patient->patient_number"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => route('patients.index'), $patient->full_name => route('patients.show', $patient), 'Edit' => null]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Fields with a problem are marked in red.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('patients.update', $patient) }}" id="patientForm" novalidate>
        @csrf @method('PUT')

        <x-ui.card>
            @include('patients.partials.form-personal')
            @include('patients.partials.form-academic')
            @include('patients.partials.form-guardian')
            @include('patients.partials.form-medical')

            <x-ui.section title="Record status">
                {{-- The switch always submits 0 when off, so turning it off deactivates the patient. --}}
                <x-ui.switch name="is_active" label="Active patient" :checked="(bool) $patient->is_active"
                    description="Inactive patients stay on record but are hidden from quick patient pickers." />
            </x-ui.section>

            <x-slot:footer class="justify-content-end">
                <x-ui.button variant="secondary" :href="route('patients.show', $patient)">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check-lg">Save changes</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection

@push('scripts')
@include('patients.partials.address-picker-script')
@endpush
