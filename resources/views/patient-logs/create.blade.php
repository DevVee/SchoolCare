@extends('layouts.app')

@section('title', 'Log a Patient Visit')

@section('content')
<x-ui.page-header title="Log a visit" description="Record who came in, why, and what was given."
    :back="route('patient-logs.index')" back-label="Back to logbook" />

@if ($errors->any())
    <x-ui.alert variant="danger" title="Please fix the errors below" class="mb-3">
        Nothing was saved and no stock was deducted.
    </x-ui.alert>
@endif

<form method="POST" action="{{ route('patient-logs.store') }}" id="logForm" novalidate>
    @csrf
    @include('patient-logs.partials.form', [
        'log' => null,
        'submitLabel' => 'Save visit',
        'cancelUrl' => route('patient-logs.index'),
    ])
</form>
@endsection
