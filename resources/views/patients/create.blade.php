@extends('layouts.app')

@section('title', 'New patient')

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="New patient" description="Only the fields marked required are needed now. You can fill in the rest later."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => route('patients.index'), 'New patient' => null]" />

    @if (! empty($linkAppointment))
        <x-ui.alert variant="info" icon="link-45deg" title="Linked to an online appointment request">
            This patient will be linked to the request of <strong>{{ $linkAppointment->requester_name }}</strong>
            on {{ $linkAppointment->appointment_date->format('M d, Y') }}. The details they typed are filled in below. Check them before saving.
        </x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Fields with a problem are marked in red.</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('patients.store') }}" id="patientForm" novalidate>
        @csrf
        @if (! empty($linkAppointment))
            <input type="hidden" name="link_appointment_id" value="{{ $linkAppointment->id }}">
        @endif

        <x-ui.card>
            @include('patients.partials.form-personal')
            @include('patients.partials.form-academic')
            @include('patients.partials.form-guardian')
            @include('patients.partials.form-medical')

            <x-slot:footer class="justify-content-end">
                <x-ui.button variant="secondary" :href="route('patients.index')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check-lg">Save patient</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection

@push('scripts')
@include('patients.partials.address-picker-script')
@endpush
