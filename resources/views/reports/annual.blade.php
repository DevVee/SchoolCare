@extends('layouts.app')

@section('title', 'Annual report')

@php
    $months = array_column($monthlyData, 'month');
@endphp

@section('content')
{{-- Printed only: letterhead banner (Admin > Settings > Printing), as on the PDF --}}
@include('reports.pdf._letterhead', ['document' => 'reports', 'screen' => true, 'printOnly' => true])

<x-ui.page-header title="Annual report" :description="'January to December '.$year"
    :breadcrumbs="['Reports' => route('reports.index'), 'Annual report' => null]">
    <x-slot:actions>
        <form method="GET" action="{{ route('reports.annual') }}" class="report-period" data-autosubmit>
            <x-ui.select name="year" aria-label="Year" :selected="$year"
                :options="collect(range(now()->year, now()->year - 5))->mapWithKeys(fn ($y) => [$y => $y])->all()" />
            <noscript><x-ui.button type="submit" variant="secondary">Show</x-ui.button></noscript>
        </form>
        @include('reports.partials.export', ['type' => 'annual', 'params' => ['year' => $year]])
    </x-slot:actions>
</x-ui.page-header>

@include('reports.partials.stat-cards', ['cards' => [
    ['label' => 'Clinic visits', 'value' => $totalVisits, 'module' => 'logbook', 'tone' => 'teal', 'icon' => 'journal-medical'],
    ['label' => 'Patients seen', 'value' => $totalVisitPatients, 'module' => 'patients', 'tone' => 'info', 'icon' => 'people'],
    ['label' => 'Consultations', 'value' => $totalConsultations, 'module' => 'consultations', 'tone' => 'info', 'icon' => 'clipboard2-pulse'],
    ['label' => 'Appointments', 'value' => $totalAppointments, 'module' => 'appointments', 'tone' => 'brand', 'icon' => 'calendar-check'],
]])

{{-- Monthly trend: chart + the month-by-month table under it --}}
<x-ui.card flush class="mb-4" module="reports" icon="graph-up" title="Monthly trend" :subtitle="(string) $year">
    <div class="p-3 p-sm-4 pb-sm-3">
        <x-ui.chart type="line" :table="false"
            :series="[
                ['name' => 'Clinic visits', 'data' => array_column($monthlyData, 'visits')],
                ['name' => 'Consultations', 'data' => array_column($monthlyData, 'consultations')],
                ['name' => 'Appointments', 'data' => array_column($monthlyData, 'appointments')],
            ]"
            :categories="$months" height="300" :empty="'No visits, consultations or appointments in '.$year.'.'" />
    </div>
    <x-ui.table dense caption="Month-by-month breakdown">
        <x-slot:head>
            <x-ui.th>Month</x-ui.th>
            <x-ui.th align="end">Clinic visits</x-ui.th>
            <x-ui.th align="end">Consultations</x-ui.th>
            <x-ui.th align="end">Appointments</x-ui.th>
        </x-slot:head>
        @foreach($monthlyData as $row)
        <tr>
            <x-ui.td>{{ $row['month'] }}</x-ui.td>
            <x-ui.td numeric>{{ number_format($row['visits']) }}</x-ui.td>
            <x-ui.td numeric>{{ number_format($row['consultations']) }}</x-ui.td>
            <x-ui.td numeric>{{ number_format($row['appointments']) }}</x-ui.td>
        </tr>
        @endforeach
        <x-slot:foot>
            <tr class="fw-semibold">
                <th scope="row">Total</th>
                <td class="cell-numeric">{{ number_format($totalVisits) }}</td>
                <td class="cell-numeric">{{ number_format($totalConsultations) }}</td>
                <td class="cell-numeric">{{ number_format($totalAppointments) }}</td>
            </tr>
        </x-slot:foot>
    </x-ui.table>
</x-ui.card>

<div class="row g-4">
    <div class="col-lg-7">
        <x-ui.card class="h-100" module="logbook" icon="clipboard2-pulse" title="Top reasons for visit" :subtitle="(string) $year">
            <x-ui.chart type="line"
                :series="[['name' => 'Visits', 'data' => $topReasons->pluck('total')->all()]]"
                :categories="$topReasons->pluck('reason')->all()" category-label="Reason"
                height="260"
                :empty="'No visits in '.$year.'.'" />
        </x-ui.card>
    </div>
    <div class="col-lg-5">
        <x-ui.card flush module="patients" title="Visits by patient category" class="h-100">
            <x-ui.table dense caption="Visits by patient category">
                <x-slot:head>
                    <x-ui.th>Category</x-ui.th>
                    <x-ui.th align="end">Visits</x-ui.th>
                    <x-ui.th align="end">Patients</x-ui.th>
                </x-slot:head>
                @foreach($byCategory as $cat)
                <tr>
                    <x-ui.td>{{ $cat->label }}</x-ui.td>
                    <x-ui.td numeric>{{ number_format($cat->total) }}</x-ui.td>
                    <x-ui.td numeric muted>{{ number_format($cat->patients) }}</x-ui.td>
                </tr>
                @endforeach
                <x-slot:empty>
                    <x-ui.empty-state compact icon="people" title="No visits this year" />
                </x-slot:empty>
            </x-ui.table>
        </x-ui.card>
    </div>
</div>

{{-- Printed only: signatures and footer line, as on the PDF --}}
@include('reports.pdf._signatures', ['document' => 'reports', 'screen' => true])

@endsection
