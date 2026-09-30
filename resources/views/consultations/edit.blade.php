@extends('layouts.app')

@section('title', 'Edit Consultation #' . $consultation->id)

@section('content')
<x-ui.page-header title="Edit consultation"
    :description="$consultation->patient->full_name.', '.\App\Support\DisplayFormat::date($consultation->visit_date)"
    :back="route('consultations.show', $consultation)" back-label="Back to consultation">
    <x-archived-badge :patient="$consultation->patient" class="ms-0" />
</x-ui.page-header>

@if ($errors->any())
    <x-ui.alert variant="danger" title="Please fix the errors below" class="mb-3">Nothing was saved.</x-ui.alert>
@endif

<form method="POST" action="{{ route('consultations.update', $consultation) }}">
    @csrf @method('PUT')
    @include('consultations.partials.form', [
        'submitLabel' => 'Save changes',
        'cancelUrl' => route('consultations.show', $consultation),
    ])
</form>
@endsection
