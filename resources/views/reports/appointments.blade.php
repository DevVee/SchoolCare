@extends('layouts.app')

@section('title', 'Appointments report')

@use('App\Support\DisplayFormat')

@php
    $statusLabels = \App\Models\Appointment::statusLabels();
    $statusCard = [
        'pending'   => ['hourglass-split', 'warning'],
        'approved'  => ['check2-circle', 'success'],
        'completed' => ['check2-all', 'brand'],
        'cancelled' => ['x-circle', 'neutral'],
        'no_show'   => ['person-x', 'neutral'],
    ];
    $cards = collect($statusLabels)
        ->map(fn ($label, $status) => [
            'label' => $label, 'value' => (int) ($byStatus[$status] ?? 0),
            'icon'  => $statusCard[$status][0] ?? 'calendar', 'tone' => $statusCard[$status][1] ?? 'neutral',
        ])
        ->values()
        ->push(['label' => 'Total', 'value' => $appointments->count(), 'icon' => 'calendar-check', 'tone' => 'brand', 'module' => 'appointments'])
        ->all();
@endphp

@section('content')

<x-ui.page-header title="Appointments report" :description="DisplayFormat::date($from).' to '.DisplayFormat::date($to)"
    :breadcrumbs="['Reports' => route('reports.index'), 'Appointments report' => null]">
    <x-slot:actions>
        <form method="GET" action="{{ route('reports.appointments') }}" class="report-period">
            <x-ui.input type="date" name="from" :value="$from" aria-label="From date" />
            <span class="report-period-sep">to</span>
            <x-ui.input type="date" name="to" :value="$to" aria-label="To date" />
            <x-ui.button type="submit" variant="secondary">Show</x-ui.button>
        </form>
        @include('reports.partials.export', ['type' => 'appointments', 'params' => ['from' => $from, 'to' => $to]])
    </x-slot:actions>
</x-ui.page-header>

@include('reports.partials.stat-cards', ['cards' => $cards])

<x-ui.card flush module="appointments" title="Appointment list" :description="$appointments->count().' '.Str::plural('appointment', $appointments->count())">
    <x-ui.table responsive="stack" caption="Appointment list">
        <x-slot:head>
            <x-ui.th>Date</x-ui.th>
            <x-ui.th>Time</x-ui.th>
            <x-ui.th>Patient</x-ui.th>
            <x-ui.th priority="md">Purpose</x-ui.th>
            <x-ui.th>Status</x-ui.th>
        </x-slot:head>
        @foreach($appointments as $a)
        <tr>
            <x-ui.td label="Date">{{ DisplayFormat::date($a->appointment_date, '-') }}</x-ui.td>
            <x-ui.td label="Time" muted>{{ DisplayFormat::time($a->appointment_time, '-') }}</x-ui.td>
            <x-ui.td identity>{{ $a->patient?->full_name ?? $a->requester_name ?? 'Unknown patient' }} <x-archived-badge :patient="$a->patient" /></x-ui.td>
            <x-ui.td label="Purpose" priority="md" truncate>{{ $a->purpose ?: '-' }}</x-ui.td>
            <x-ui.td label="Status"><x-ui.status-badge :status="$a->status" type="appointment" :label="$statusLabels[$a->status] ?? null" size="sm" /></x-ui.td>
        </tr>
        @endforeach
        <x-slot:empty>
            <x-ui.empty-state compact icon="calendar-check" title="No appointments in this period" description="Try a wider date range." />
        </x-slot:empty>
    </x-ui.table>
</x-ui.card>

@endsection
