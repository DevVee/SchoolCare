@extends('layouts.app')

@section('title', $visit->exists ? 'Edit specialist visit' : 'Schedule specialist visit')

@php
    $types = $types;
    if ($visit->type && ! in_array($visit->type, $types, true)) {
        $types[] = $visit->type;
    }
    $typeOptions = $types ? array_combine($types, $types) : [];
    $cancelUrl = $visit->exists ? route('specialist-visits.show', $visit) : route('specialist-visits.index');
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="$visit->exists ? 'Edit specialist visit' : 'Schedule a specialist visit'"
        description="Types come from Settings, Clinic, Specialist types."
        :breadcrumbs="array_merge(['Dashboard' => route('dashboard'), 'Specialist visits' => route('specialist-visits.index')],
            $visit->exists ? [\App\Support\DisplayFormat::date($visit->visit_date) => route('specialist-visits.show', $visit), 'Edit' => null] : ['Schedule' => null])" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Please fix the errors below">Fields with a problem are marked in red.</x-ui.alert>
    @endif

    <form method="POST" action="{{ $visit->exists ? route('specialist-visits.update', $visit) : route('specialist-visits.store') }}" novalidate>
        @csrf
        @if ($visit->exists) @method('PUT') @endif
        <x-ui.card>
            <x-ui.section title="Specialist" columns="2">
                <x-ui.select name="type" id="sv-type" label="Type" required :options="$typeOptions" :selected="$visit->type" />
                <x-ui.input name="specialist_name" id="sv-name" label="Specialist name" required maxlength="150"
                    :value="$visit->specialist_name" placeholder="e.g. Dr. Ana Reyes" />
            </x-ui.section>

            <x-ui.section title="Date and hours" columns="3">
                <x-ui.input type="date" name="visit_date" id="sv-date" label="Date" required
                    :value="$visit->visit_date?->format('Y-m-d')" :min="$visit->exists ? null : now()->format('Y-m-d')" />
                <x-ui.input type="time" name="start_time" id="sv-start" label="Start" required :value="substr((string) $visit->start_time, 0, 5)" />
                <x-ui.input type="time" name="end_time" id="sv-end" label="End" required :value="substr((string) $visit->end_time, 0, 5)" />
                <x-ui.input type="number" name="capacity" id="sv-capacity" label="Patient limit" optional min="1" max="500" step="1"
                    :value="$visit->capacity" help="Leave empty for no limit." />
                @if ($visit->exists)
                    <x-ui.select name="status" id="sv-status" label="Status" :options="\App\Models\SpecialistVisit::STATUSES" :selected="$visit->status" />
                @endif
            </x-ui.section>

            <x-ui.section title="Notes">
                <x-ui.textarea name="notes" id="sv-notes" label="Notes" optional rows="3" maxlength="1000"
                    :value="$visit->notes" placeholder="e.g. Dental check-up for Grade 1 to 3" />
            </x-ui.section>

            <x-slot:footer class="justify-content-end">
                <x-ui.button variant="secondary" :href="$cancelUrl">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check-lg">{{ $visit->exists ? 'Save changes' : 'Schedule visit' }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection
