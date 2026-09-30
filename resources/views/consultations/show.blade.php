@extends('layouts.app')

@section('title', 'Consultation #' . $consultation->id)

@section('content')
@use('App\Support\DisplayFormat')
@php
    $patient = $consultation->patient;
    $name    = $patient?->full_name ?? 'Unknown patient';
    $when    = $consultation->visit_date->format('l').', '.DisplayFormat::date($consultation->visit_date)
        .($consultation->visit_time ? ' at '.DisplayFormat::time($consultation->visit_time) : '');
@endphp

<x-ui.page-header :title="'Consultation: '.$name" :description="$when"
    :breadcrumbs="['Consultations' => route('consultations.index'), 'Consultation #'.$consultation->id => null]">
    @canany(['update-consultations', 'delete-consultations'])
    <x-slot:actions>
        @can('update-consultations')
            <x-ui.button icon="pencil" :href="route('consultations.edit', $consultation)">Edit</x-ui.button>
        @endcan
        @can('delete-consultations')
        <x-ui.dropdown label="More" variant="secondary">
            <x-ui.dropdown-item :action="route('consultations.destroy', $consultation)" method="DELETE" icon="trash" tone="danger"
                confirm="This cannot be undone." :confirm-title="'Delete the consultation for '.$name.'?'"
                confirm-button="Delete consultation">Delete consultation</x-ui.dropdown-item>
        </x-ui.dropdown>
        @endcan
    </x-slot:actions>
    @endcanany
</x-ui.page-header>

<div class="row g-4">

    {{-- Patient and visit --}}
    <div class="col-lg-4">
        <div class="vstack gap-4">
            <x-ui.card title="Patient">
                @if ($patient)
                <div class="identity mb-3">
                    <x-ui.avatar :name="$name" size="md" />
                    <div class="identity-text">
                        <span class="identity-title">{{ $name }}</span>
                        <span class="identity-sub">{{ $patient->patient_number }}<x-archived-badge :patient="$patient" /></span>
                    </div>
                </div>
                <x-ui.description-list layout="compact" :items="[
                    'Category' => $patient->category ? (\App\Models\Patient::categoryLabels()[$patient->category] ?? ucfirst($patient->category)) : null,
                    'Age' => $patient->age !== null ? $patient->age.' years' : null,
                    'Sex' => $patient->sex ? ucfirst($patient->sex) : null,
                ]" />
                @can('view-patients')
                <x-slot:footer>
                    <x-ui.button variant="secondary" size="sm" icon="person-lines-fill" :href="route('patients.show', $patient->id)">View patient record</x-ui.button>
                </x-slot:footer>
                @endcan
                @else
                    <p class="text-muted mb-0">Unknown patient</p>
                @endif
            </x-ui.card>

            <x-ui.card title="Visit">
                <x-ui.description-list layout="compact">
                    <x-ui.description-item label="Date">{{ DisplayFormat::date($consultation->visit_date) }}</x-ui.description-item>
                    <x-ui.description-item label="Time">{{ DisplayFormat::time($consultation->visit_time) }}</x-ui.description-item>
                    <x-ui.description-item label="Recorded by">{{ $consultation->nurse?->name ?? 'Deleted user' }}</x-ui.description-item>
                    <x-ui.description-item label="Appointment">
                        @if ($consultation->appointment)
                            <a href="{{ route('appointments.show', $consultation->appointment) }}">{{ DisplayFormat::date($consultation->appointment->appointment_date) }}</a>
                        @else
                            <span class="text-muted">Walk-in</span>
                        @endif
                    </x-ui.description-item>
                    <x-ui.description-item label="Saved">{{ $consultation->created_at->diffForHumans() }}</x-ui.description-item>
                </x-ui.description-list>
            </x-ui.card>
        </div>
    </div>

    {{-- Clinical notes and medicines --}}
    <div class="col-lg-8">
        <div class="vstack gap-4">
            <x-ui.card title="Clinical notes">
                <x-ui.description-list layout="stacked">
                    <x-ui.description-item label="Chief complaint">{{ $consultation->chief_complaint }}</x-ui.description-item>
                    <x-ui.description-item label="Assessment and findings">{{ $consultation->assessment }}</x-ui.description-item>
                    <x-ui.description-item label="Diagnosis">{{ $consultation->diagnosis }}</x-ui.description-item>
                    <x-ui.description-item label="Treatment or medicine given">{{ $consultation->treatment }}</x-ui.description-item>
                    @if ($consultation->notes)
                    <x-ui.description-item label="Additional notes">{{ $consultation->notes }}</x-ui.description-item>
                    @endif
                </x-ui.description-list>
            </x-ui.card>

            <x-ui.card title="Medicines dispensed" flush>
                <x-slot:actions>
                    <span class="text-muted small tabular">{{ $consultation->dispensingRecords->count() }}</span>
                </x-slot:actions>
                <x-ui.table dense caption="Medicines dispensed for this consultation">
                    <x-slot:head>
                        <x-ui.th>Medicine</x-ui.th>
                        <x-ui.th align="end">Quantity</x-ui.th>
                    </x-slot:head>
                    @foreach ($consultation->dispensingRecords as $rec)
                    <tr>
                        <x-ui.td>
                            @can('view-dispensing')
                                <a href="{{ route('dispensing.show', $rec) }}" class="cell-title text-decoration-none">{{ $rec->medicine->name ?? 'Removed medicine' }}</a>
                            @else
                                <span class="cell-title">{{ $rec->medicine->name ?? 'Removed medicine' }}</span>
                            @endcan
                        </x-ui.td>
                        <x-ui.td numeric>{{ $rec->quantity }} {{ $rec->medicine->unit ?? '' }}</x-ui.td>
                    </tr>
                    @endforeach
                    <x-slot:empty>
                        <x-ui.empty-state quiet icon="capsule" title="No medicines dispensed for this visit." class="px-3" />
                    </x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection
