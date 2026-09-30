@extends('layouts.app')

@section('title', 'Daily report')

@use('App\Support\DisplayFormat')

@php
    $day          = \Carbon\Carbon::parse($date);
    $dispositions = \App\Models\PatientLog::dispositions();
@endphp

@section('content')
{{-- Printed only: letterhead banner (Admin > Settings > Printing), as on the PDF --}}
@include('reports.pdf._letterhead', ['document' => 'reports', 'screen' => true, 'printOnly' => true])

<x-ui.page-header title="Daily report" :description="$day->format('l, F j, Y')"
    :breadcrumbs="['Reports' => route('reports.index'), 'Daily report' => null]">
    <x-slot:actions>
        <form method="GET" action="{{ route('reports.daily') }}" class="report-period" data-autosubmit>
            <x-ui.input type="date" name="date" :value="$date" aria-label="Report date" />
            <noscript><x-ui.button type="submit" variant="secondary">Show</x-ui.button></noscript>
        </form>
        @include('reports.partials.export', ['type' => 'daily', 'params' => ['date' => $date]])
    </x-slot:actions>
</x-ui.page-header>

@include('reports.partials.stat-cards', ['cards' => [
    ['label' => 'Clinic visits', 'value' => $visits->count(), 'module' => 'logbook', 'tone' => 'teal', 'icon' => 'journal-medical'],
    ['label' => 'Consultations', 'value' => $consultations->count(), 'module' => 'consultations', 'tone' => 'info', 'icon' => 'clipboard2-pulse'],
    ['label' => 'Appointments', 'value' => $appointments->count(), 'module' => 'appointments', 'tone' => 'brand', 'icon' => 'calendar-check'],
    ['label' => 'Medicines dispensed', 'value' => $dispensed->count(), 'module' => 'dispensing', 'tone' => 'success', 'icon' => 'prescription2'],
]])

@if($visits->isNotEmpty())
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <x-ui.card flush module="patients" title="Visits by patient category" class="h-100">
            <x-ui.table dense caption="Visits by patient category">
                <x-slot:head>
                    <x-ui.th>Category</x-ui.th>
                    <x-ui.th align="end">Visits</x-ui.th>
                </x-slot:head>
                @foreach($visitsByCategory as $cat)
                <tr>
                    <x-ui.td>{{ $cat->label }}</x-ui.td>
                    <x-ui.td numeric>{{ number_format($cat->total) }}</x-ui.td>
                </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    </div>
    <div class="col-md-6">
        <x-ui.card flush module="logbook" icon="signpost-split" title="Visits by outcome" class="h-100">
            <x-ui.table dense caption="Visits by outcome">
                <x-slot:head>
                    <x-ui.th>Outcome</x-ui.th>
                    <x-ui.th align="end">Visits</x-ui.th>
                </x-slot:head>
                @foreach($visitsByDisposition as $key => $count)
                <tr>
                    <x-ui.td>{{ $dispositions[$key] ?? ucwords(str_replace('_', ' ', (string) $key)) }}</x-ui.td>
                    <x-ui.td numeric>{{ number_format($count) }}</x-ui.td>
                </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    </div>
</div>
@endif

{{-- Clinic visits (logbook) --}}
<x-ui.card flush module="logbook" title="Clinic visits" :description="$visits->count().' '.Str::plural('visit', $visits->count())" class="mb-4">
    <x-ui.table responsive="stack" caption="Clinic visits">
        <x-slot:head>
            <x-ui.th>Time in</x-ui.th>
            <x-ui.th>Patient</x-ui.th>
            <x-ui.th>Complaint</x-ui.th>
            <x-ui.th priority="lg">Treatment</x-ui.th>
            <x-ui.th>Outcome</x-ui.th>
            <x-ui.th priority="xl">Logged by</x-ui.th>
        </x-slot:head>
        @foreach($visits as $v)
        <tr>
            <x-ui.td label="Time in">{{ DisplayFormat::time($v->time_in, '-') }}</x-ui.td>
            <x-ui.td identity>
                <div class="identity-text">
                    <span class="identity-title">{{ $v->patient?->full_name ?? 'Unknown patient' }} <x-archived-badge :patient="$v->patient" /></span>
                    <span class="identity-sub">{{ $v->patient?->category_label ?? '-' }}</span>
                </div>
            </x-ui.td>
            <x-ui.td label="Complaint" truncate>{{ $v->complaint_summary ?: '-' }}</x-ui.td>
            <x-ui.td label="Treatment" priority="lg" truncate>{{ $v->treatment ?: '-' }}</x-ui.td>
            <x-ui.td label="Outcome"><x-ui.status-badge :status="$v->disposition" type="disposition" :label="$v->disposition_label" size="sm" /></x-ui.td>
            <x-ui.td label="Logged by" priority="xl" muted>{{ $v->loggedBy?->name ?? 'Deleted user' }}</x-ui.td>
        </tr>
        @endforeach
        <x-slot:empty>
            <x-ui.empty-state compact icon="journal-medical" title="No clinic visits on this day" />
        </x-slot:empty>
    </x-ui.table>
</x-ui.card>

{{-- Appointments --}}
<x-ui.card flush module="appointments" title="Appointments" :description="$appointments->count().' '.Str::plural('appointment', $appointments->count())" class="mb-4">
    <x-ui.table responsive="stack" caption="Appointments">
        <x-slot:head>
            <x-ui.th>Time</x-ui.th>
            <x-ui.th>Patient</x-ui.th>
            <x-ui.th priority="md">Purpose</x-ui.th>
            <x-ui.th>Status</x-ui.th>
        </x-slot:head>
        @foreach($appointments as $a)
        <tr>
            <x-ui.td label="Time">{{ DisplayFormat::time($a->appointment_time, '-') }}</x-ui.td>
            <x-ui.td identity>{{ $a->patient?->full_name ?? $a->requester_name ?? 'Unknown patient' }} <x-archived-badge :patient="$a->patient" /></x-ui.td>
            <x-ui.td label="Purpose" priority="md" truncate>{{ $a->purpose ?: '-' }}</x-ui.td>
            <x-ui.td label="Status"><x-ui.status-badge :status="$a->status" type="appointment" size="sm" /></x-ui.td>
        </tr>
        @endforeach
        <x-slot:empty>
            <x-ui.empty-state compact icon="calendar-check" title="No appointments on this day" />
        </x-slot:empty>
    </x-ui.table>
</x-ui.card>

{{-- Consultations --}}
<x-ui.card flush module="consultations" title="Consultations" :description="$consultations->count().' '.Str::plural('consultation', $consultations->count())" class="mb-4">
    <x-ui.table responsive="stack" caption="Consultations">
        <x-slot:head>
            <x-ui.th>Time</x-ui.th>
            <x-ui.th>Patient</x-ui.th>
            <x-ui.th>Chief complaint</x-ui.th>
            <x-ui.th priority="md">Nurse</x-ui.th>
        </x-slot:head>
        @foreach($consultations as $c)
        <tr>
            <x-ui.td label="Time">{{ DisplayFormat::time($c->visit_time, '-') }}</x-ui.td>
            <x-ui.td identity>{{ $c->patient?->full_name ?? 'Unknown patient' }} <x-archived-badge :patient="$c->patient" /></x-ui.td>
            <x-ui.td label="Chief complaint" truncate>{{ $c->chief_complaint ?: '-' }}</x-ui.td>
            <x-ui.td label="Nurse" priority="md" muted>{{ $c->nurse?->name ?? 'Deleted user' }}</x-ui.td>
        </tr>
        @endforeach
        <x-slot:empty>
            <x-ui.empty-state compact icon="clipboard2-pulse" title="No consultations on this day" />
        </x-slot:empty>
    </x-ui.table>
</x-ui.card>

{{-- Medicines dispensed --}}
<x-ui.card flush module="dispensing" title="Medicines dispensed" :description="$dispensed->count().' '.Str::plural('record', $dispensed->count())">
    <x-ui.table responsive="stack" caption="Medicines dispensed">
        <x-slot:head>
            <x-ui.th>Time</x-ui.th>
            <x-ui.th>Patient</x-ui.th>
            <x-ui.th>Medicine</x-ui.th>
            <x-ui.th align="end">Quantity</x-ui.th>
            <x-ui.th priority="md">Given by</x-ui.th>
        </x-slot:head>
        @foreach($dispensed as $d)
        <tr>
            <x-ui.td label="Time">{{ DisplayFormat::time($d->dispensed_at, '-') }}</x-ui.td>
            <x-ui.td identity>{{ $d->patient?->full_name ?? 'Unknown patient' }} <x-archived-badge :patient="$d->patient" /></x-ui.td>
            <x-ui.td label="Medicine">{{ $d->medicine?->name ?? '-' }}</x-ui.td>
            <x-ui.td label="Quantity" numeric>{{ number_format($d->quantity) }}</x-ui.td>
            <x-ui.td label="Given by" priority="md" muted>{{ $d->dispensedBy?->name ?? 'Deleted user' }}</x-ui.td>
        </tr>
        @endforeach
        <x-slot:empty>
            <x-ui.empty-state compact icon="capsule" title="No medicines dispensed on this day" />
        </x-slot:empty>
    </x-ui.table>
</x-ui.card>

{{-- Printed only: signatures and footer line, as on the PDF --}}
@include('reports.pdf._signatures', ['document' => 'reports', 'screen' => true])

@endsection
