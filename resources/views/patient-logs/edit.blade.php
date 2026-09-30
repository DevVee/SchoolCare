@extends('layouts.app')

@section('title', 'Edit Log Entry')

@section('content')
<x-ui.page-header title="Edit visit"
    :description="($patientLog->patient->full_name ?? 'Unknown patient').', '.\App\Support\DisplayFormat::date($patientLog->log_date)"
    :back="route('patient-logs.show', $patientLog)" back-label="Back to visit">
    <x-archived-badge :patient="$patientLog->patient" class="ms-0" />
</x-ui.page-header>

@if ($errors->any())
    <x-ui.alert variant="danger" title="Please fix the errors below" class="mb-3">
        Nothing was saved and no stock was deducted.
    </x-ui.alert>
@endif

<form method="POST" action="{{ route('patient-logs.update', $patientLog) }}" id="logForm" novalidate>
    @csrf @method('PUT')
    @include('patient-logs.partials.form', [
        'log' => $patientLog,
        'submitLabel' => 'Save changes',
        'cancelUrl' => route('patient-logs.show', $patientLog),
    ])
</form>
@endsection
