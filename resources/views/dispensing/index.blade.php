@extends('layouts.app')

@section('title', 'Dispensing Records')

@section('content')
@use('App\Support\DisplayFormat')
@php
    $medicineOptions = $medicines->pluck('name', 'id')->all();
    $hasFilters = $filters['search'] || $filters['dateFrom'] || $filters['dateTo'] || $filters['medicineId'];
@endphp

<x-ui.page-header title="Dispensing records" description="Every medicine given to a patient, newest first.">
    @can('create-dispensing')
    <x-slot:actions>
        <x-ui.button :href="route('dispensing.create')" icon="plus-lg">Dispense medicine</x-ui.button>
    </x-slot:actions>
    @endcan
</x-ui.page-header>

<x-ui.filters class="mb-3" :action="route('dispensing.index')" search-placeholder="Patient, patient no. or medicine"
    :reset-url="route('dispensing.index')"
    :labels="['date_from' => 'From', 'date_to' => 'To', 'medicine_id' => 'Medicine']"
    :options="[
        'date_from' => [(string) $filters['dateFrom'] => $filters['dateFrom'] ? DisplayFormat::date($filters['dateFrom']) : ''],
        'date_to' => [(string) $filters['dateTo'] => $filters['dateTo'] ? DisplayFormat::date($filters['dateTo']) : ''],
        'medicine_id' => $medicineOptions,
    ]">
    <x-slot:inline>
        <x-ui.date-range from-name="date_from" to-name="date_to" :from="$filters['dateFrom']" :to="$filters['dateTo']" label="Dates given" />
    </x-slot:inline>
    <x-ui.select name="medicine_id" label="Medicine" size="sm" :options="$medicineOptions" placeholder="All medicines" :selected="$filters['medicineId']" />
</x-ui.filters>

<x-ui.card flush>
    <x-ui.table responsive="stack" :paginator="$records" noun="records" caption="Dispensing records">
        <x-slot:head>
            <x-ui.th>Patient</x-ui.th>
            <x-ui.th>Medicine</x-ui.th>
            <x-ui.th align="end">Quantity</x-ui.th>
            <x-ui.th priority="md">Given on</x-ui.th>
            <x-ui.th priority="lg">Given by</x-ui.th>
            <x-ui.th priority="xl">Remarks</x-ui.th>
            <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
        </x-slot:head>

        @foreach($records as $rec)
        @php $name = $rec->patient?->full_name ?? 'Unknown patient'; @endphp
        <tr>
            <x-ui.td identity>
                <div class="identity">
                    <div class="identity-text">
                        <a href="{{ route('dispensing.show', $rec) }}" class="identity-title" title="View record">{{ $name }}</a>
                        <span class="identity-sub">{{ $rec->patient?->patient_number }}<x-archived-badge :patient="$rec->patient" /></span>
                    </div>
                </div>
            </x-ui.td>
            <x-ui.td label="Medicine" truncate>{{ $rec->medicine?->name ?? 'Removed medicine' }}</x-ui.td>
            <x-ui.td label="Quantity" numeric>{{ $rec->quantity }} {{ $rec->medicine?->unit }}</x-ui.td>
            <x-ui.td label="Given on" priority="md" class="tabular">{{ DisplayFormat::date($rec->dispensed_at) }}, {{ DisplayFormat::time($rec->dispensed_at) }}</x-ui.td>
            <x-ui.td label="Given by" priority="lg" muted>{{ $rec->dispensedBy?->name ?? 'Deleted user' }}</x-ui.td>
            <x-ui.td label="Remarks" priority="xl" muted truncate>{{ $rec->remarks ?: '-' }}</x-ui.td>
            <x-ui.td actions>
                <x-ui.action-menu :label="'Actions for dispensing record of '.$name">
                    <x-ui.action-menu.item :href="route('dispensing.show', $rec)" icon="eye">View</x-ui.action-menu.item>
                    @if($rec->patient)
                    @can('view-patients')
                    <x-ui.action-menu.item :href="route('patients.show', $rec->patient->id)" icon="person">Patient record</x-ui.action-menu.item>
                    @endcan
                    @endif
                </x-ui.action-menu>
            </x-ui.td>
        </tr>
        @endforeach

        <x-slot:empty>
            @if($hasFilters)
                <x-ui.empty-state compact icon="search" title="No records match these filters" description="Try other dates or clear the filters.">
                    <x-ui.button variant="secondary" size="sm" :href="route('dispensing.index')">Clear filters</x-ui.button>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state compact module="dispensing" title="No medicines dispensed yet" description="Medicines given to patients appear here.">
                    @can('create-dispensing')
                    <x-ui.button size="sm" icon="plus-lg" :href="route('dispensing.create')">Dispense medicine</x-ui.button>
                    @endcan
                </x-ui.empty-state>
            @endif
        </x-slot:empty>
    </x-ui.table>
</x-ui.card>
@endsection
