@extends('layouts.app')

@section('title', 'Consultations')

@section('content')
@use('App\Support\DisplayFormat')
@php
    $nurseOptions = $nurses->pluck('name', 'id')->all();
    $hasFilters   = $filters['search'] || $filters['date'] || $filters['nurseId'];
@endphp

<x-ui.page-header title="Consultations" description="Visit records with clinical notes, diagnosis and treatment.">
    @can('create-consultations')
    <x-slot:actions>
        <x-ui.button :href="route('consultations.create')" icon="plus-lg">New consultation</x-ui.button>
    </x-slot:actions>
    @endcan
</x-ui.page-header>

<x-ui.filters class="mb-3" :action="route('consultations.index')" search-placeholder="Patient, patient no., complaint or diagnosis"
    :reset-url="route('consultations.index')"
    :labels="['date' => 'Visit date', 'nurse_id' => 'Recorded by']"
    :options="[
        'date' => [(string) $filters['date'] => $filters['date'] ? DisplayFormat::date($filters['date']) : ''],
        'nurse_id' => $nurseOptions,
    ]">
    <x-slot:inline>
        <x-ui.input name="date" type="date" size="sm" aria-label="Visit date" :value="$filters['date']" />
    </x-slot:inline>
    <x-ui.select name="nurse_id" label="Recorded by" size="sm" :options="$nurseOptions" placeholder="Anyone" :selected="$filters['nurseId']" />
</x-ui.filters>

<x-ui.card flush>
    <x-ui.table responsive="stack" :paginator="$consultations" noun="consultations" caption="Consultations">
        <x-slot:head>
            <x-ui.th>Patient</x-ui.th>
            <x-ui.th>Visit date</x-ui.th>
            <x-ui.th priority="md">Chief complaint</x-ui.th>
            <x-ui.th priority="lg">Diagnosis</x-ui.th>
            <x-ui.th priority="xl">Recorded by</x-ui.th>
            <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
        </x-slot:head>

        @foreach ($consultations as $consult)
        @php $name = $consult->patient?->full_name ?? 'Unknown patient'; @endphp
        <tr>
            <x-ui.td identity>
                <div class="identity">
                    <div class="identity-text">
                        <a href="{{ route('consultations.show', $consult) }}" class="identity-title" title="View consultation">{{ $name }}</a>
                        <span class="identity-sub">{{ $consult->patient?->patient_number }}<x-archived-badge :patient="$consult->patient" /></span>
                    </div>
                </div>
            </x-ui.td>
            <x-ui.td label="Visit date" class="tabular">
                {{ DisplayFormat::date($consult->visit_date) }}@if ($consult->visit_time), {{ DisplayFormat::time($consult->visit_time) }}@endif
            </x-ui.td>
            <x-ui.td label="Chief complaint" priority="md" truncate>{{ $consult->chief_complaint }}</x-ui.td>
            <x-ui.td label="Diagnosis" priority="lg" truncate>{{ $consult->diagnosis ?: '-' }}</x-ui.td>
            <x-ui.td label="Recorded by" priority="xl" muted>{{ $consult->nurse?->name ?? 'Deleted user' }}</x-ui.td>
            <x-ui.td actions>
                <x-ui.action-menu :label="'Actions for consultation of '.$name">
                    <x-ui.action-menu.item :href="route('consultations.show', $consult)" icon="eye">View</x-ui.action-menu.item>
                    @can('update-consultations')
                    <x-ui.action-menu.item :href="route('consultations.edit', $consult)" icon="pencil">Edit</x-ui.action-menu.item>
                    @endcan
                    @if ($consult->patient)
                    @can('view-patients')
                    <x-ui.action-menu.item :href="route('patients.show', $consult->patient->id)" icon="person">Patient record</x-ui.action-menu.item>
                    @endcan
                    @endif
                    @can('delete-consultations')
                    <x-ui.action-menu.divider />
                    <x-ui.action-menu.item :action="route('consultations.destroy', $consult)" method="DELETE" icon="trash" danger
                        confirm="This cannot be undone."
                        :confirm-title="'Delete the consultation for '.$name.'?'" confirm-button="Delete consultation">Delete</x-ui.action-menu.item>
                    @endcan
                </x-ui.action-menu>
            </x-ui.td>
        </tr>
        @endforeach

        <x-slot:empty>
            @if ($hasFilters)
                <x-ui.empty-state compact icon="search" title="No consultations match these filters" description="Try another date or clear the filters.">
                    <x-ui.button variant="secondary" size="sm" :href="route('consultations.index')">Clear filters</x-ui.button>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state compact module="consultations" title="No consultations yet" description="Consultations you record appear here.">
                    @can('create-consultations')
                    <x-ui.button size="sm" icon="plus-lg" :href="route('consultations.create')">New consultation</x-ui.button>
                    @endcan
                </x-ui.empty-state>
            @endif
        </x-slot:empty>
    </x-ui.table>
</x-ui.card>
@endsection
