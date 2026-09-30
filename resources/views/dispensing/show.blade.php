@extends('layouts.app')

@section('title', 'Dispensing Record')

@section('content')
@use('App\Support\DisplayFormat')
@php
    $patient  = $dispensing->patient;
    $medicine = $dispensing->medicine;
@endphp

<x-ui.page-header :title="$medicine->name ?? 'Removed medicine'"
    :description="'Given to '.($patient?->full_name ?? 'an unknown patient').' on '.DisplayFormat::date($dispensing->dispensed_at).' at '.DisplayFormat::time($dispensing->dispensed_at)"
    :breadcrumbs="['Dispensing records' => route('dispensing.index'), 'Record #'.$dispensing->id => null]" />

<div class="row g-4">
    <div class="col-lg-7">
        <div class="vstack gap-4">
            <x-ui.card title="Dispensing details">
                <x-ui.description-list>
                    <x-ui.description-item label="Medicine">{{ $medicine->name ?? 'Removed medicine' }}</x-ui.description-item>
                    <x-ui.description-item label="Category">{{ $medicine->category->name ?? '' }}</x-ui.description-item>
                    <x-ui.description-item label="Quantity"><span class="fw-semibold tabular">{{ $dispensing->quantity }}</span> {{ $medicine->unit ?? '' }}(s)</x-ui.description-item>
                    <x-ui.description-item label="Given on">{{ DisplayFormat::date($dispensing->dispensed_at) }}, {{ DisplayFormat::time($dispensing->dispensed_at) }}</x-ui.description-item>
                    <x-ui.description-item label="Given by">{{ $dispensing->dispensedBy?->name ?? 'Deleted user' }}</x-ui.description-item>
                    <x-ui.description-item label="Remarks" empty="None">{{ $dispensing->remarks }}</x-ui.description-item>
                </x-ui.description-list>
            </x-ui.card>

            <x-ui.card title="Batches used" description="Earliest expiry first." flush>
                <x-ui.table dense caption="Batches used for this record">
                    <x-slot:head>
                        <x-ui.th>Batch</x-ui.th>
                        <x-ui.th>Expiry</x-ui.th>
                        <x-ui.th align="end">Quantity</x-ui.th>
                    </x-slot:head>
                    @foreach($dispensing->transactions as $t)
                    <tr>
                        <x-ui.td>{{ $t->batch_number ?: 'No batch no.' }}</x-ui.td>
                        <x-ui.td muted>{{ $t->expiration_date ? DisplayFormat::date($t->expiration_date) : '-' }}</x-ui.td>
                        <x-ui.td numeric>{{ abs($t->quantity) }}</x-ui.td>
                    </tr>
                    @endforeach
                    <x-slot:empty>
                        <x-ui.empty-state quiet icon="box-seam" title="No batch details recorded." class="px-3" />
                    </x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="vstack gap-4">
            <x-ui.card title="Patient">
                @if($patient)
                    <div class="identity">
                        <x-ui.avatar :name="$patient->full_name" size="md" />
                        <div class="identity-text">
                            <span class="identity-title">{{ $patient->full_name }}</span>
                            <span class="identity-sub">{{ $patient->patient_number }}@if($patient->category), {{ \App\Models\Patient::categoryLabels()[$patient->category] ?? ucfirst($patient->category) }}@endif<x-archived-badge :patient="$patient" /></span>
                        </div>
                    </div>
                    @can('view-patients')
                    <x-slot:footer>
                        <x-ui.button variant="secondary" size="sm" icon="person-lines-fill" :href="route('patients.show', $patient->id)">View patient record</x-ui.button>
                    </x-slot:footer>
                    @endcan
                @else
                    <p class="text-muted mb-0">Patient not found.</p>
                @endif
            </x-ui.card>

            <x-ui.card title="Given during">
                @if($dispensing->consultation)
                    <x-ui.description-list layout="compact">
                        <x-ui.description-item label="Consultation">{{ DisplayFormat::date($dispensing->consultation->visit_date) }}</x-ui.description-item>
                        <x-ui.description-item label="Complaint">{{ Str::limit($dispensing->consultation->chief_complaint, 80) }}</x-ui.description-item>
                    </x-ui.description-list>
                    @if($dispensing->consultation->trashed())
                        <x-ui.badge color="neutral" class="mt-2">Consultation record deleted</x-ui.badge>
                    @else
                        <x-slot:footer>
                            <x-ui.button variant="secondary" size="sm" icon="eye" :href="route('consultations.show', $dispensing->consultation)">View consultation</x-ui.button>
                        </x-slot:footer>
                    @endif
                @elseif($dispensing->patientLog)
                    <x-ui.description-list layout="compact">
                        <x-ui.description-item label="Clinic visit">{{ DisplayFormat::date($dispensing->patientLog->log_date) }}</x-ui.description-item>
                        <x-ui.description-item label="Reason">{{ Str::limit($dispensing->patientLog->complaint_summary, 80) }}</x-ui.description-item>
                    </x-ui.description-list>
                    @can('view-patient-logs')
                    <x-slot:footer>
                        <x-ui.button variant="secondary" size="sm" icon="eye" :href="route('patient-logs.show', $dispensing->patientLog)">View visit</x-ui.button>
                    </x-slot:footer>
                    @endcan
                @else
                    <p class="text-muted mb-0">Walk-in dispensing (no consultation or visit linked).</p>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
@endsection
