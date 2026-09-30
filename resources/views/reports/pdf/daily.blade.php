@extends('reports.pdf._layout')

@use('App\Support\DisplayFormat')

@php
    $reportTitle  = 'Daily report';
    $reportPeriod = \Carbon\Carbon::parse($date)->format('l, F j, Y');
    $dispositions = \App\Models\PatientLog::dispositions();
@endphp

@section('content')
<table class="summary">
    <tr>
        <td><div class="n">{{ number_format($visits->count()) }}</div><div class="l">Clinic visits</div></td>
        <td><div class="n">{{ number_format($consultations->count()) }}</div><div class="l">Consultations</div></td>
        <td><div class="n">{{ number_format($appointments->count()) }}</div><div class="l">Appointments</div></td>
        <td><div class="n">{{ number_format($dispensed->count()) }}</div><div class="l">Medicines dispensed</div></td>
    </tr>
</table>

<h2>Clinic visits</h2>
<table class="data">
    <thead>
        <tr><th>Time in</th><th>Time out</th><th>Patient</th><th>Category</th><th>Complaint</th><th>Treatment</th><th>Outcome</th></tr>
    </thead>
    <tbody>
        @forelse($visits as $v)
        <tr>
            <td class="nowrap">{{ DisplayFormat::time($v->time_in, '-') }}</td>
            <td class="nowrap">{{ DisplayFormat::time($v->time_out, '-') }}</td>
            <td>{{ $v->patient?->full_name ?? 'Unknown patient' }}{{ $v->patient?->trashed() ? ' (archived)' : '' }}</td>
            <td>{{ $v->patient?->category_label ?? '-' }}</td>
            <td>{{ $v->complaint_summary ?: '-' }}</td>
            <td>{{ $v->treatment ?: '-' }}</td>
            <td>{{ $v->disposition_label }}</td>
        </tr>
        @empty
        <tr><td colspan="7" class="empty">No clinic visits on this day.</td></tr>
        @endforelse
    </tbody>
</table>

@if($visits->isNotEmpty())
<table class="cols">
    <tr>
        <td class="col" style="width:48%;">
            <h2>Visits by patient category</h2>
            <table class="data">
                <thead><tr><th>Category</th><th class="num">Visits</th></tr></thead>
                <tbody>
                    @foreach($visitsByCategory as $cat)
                    <tr><td>{{ $cat->label }}</td><td class="num">{{ number_format($cat->total) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </td>
        <td class="gap"></td>
        <td class="col" style="width:48%;">
            <h2>Visits by outcome</h2>
            <table class="data">
                <thead><tr><th>Outcome</th><th class="num">Visits</th></tr></thead>
                <tbody>
                    @foreach($visitsByDisposition as $key => $count)
                    <tr><td>{{ $dispositions[$key] ?? ucwords(str_replace('_', ' ', (string) $key)) }}</td><td class="num">{{ number_format($count) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </td>
    </tr>
</table>
@endif

<h2>Appointments</h2>
<table class="data">
    <thead><tr><th>Time</th><th>Patient</th><th>Purpose</th><th>Status</th></tr></thead>
    <tbody>
        @forelse($appointments as $a)
        <tr>
            <td class="nowrap">{{ DisplayFormat::time($a->appointment_time, '-') }}</td>
            <td>{{ $a->patient?->full_name ?? $a->requester_name ?? 'Unknown patient' }}{{ $a->patient?->trashed() ? ' (archived)' : '' }}</td>
            <td>{{ $a->purpose ?: '-' }}</td>
            <td>{{ \App\Models\Appointment::statusLabels()[$a->status] ?? ucfirst(str_replace('_', ' ', $a->status)) }}</td>
        </tr>
        @empty
        <tr><td colspan="4" class="empty">No appointments on this day.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Consultations</h2>
<table class="data">
    <thead><tr><th>Time</th><th>Patient</th><th>Chief complaint</th><th>Nurse</th></tr></thead>
    <tbody>
        @forelse($consultations as $c)
        <tr>
            <td class="nowrap">{{ DisplayFormat::time($c->visit_time, '-') }}</td>
            <td>{{ $c->patient?->full_name ?? 'Unknown patient' }}{{ $c->patient?->trashed() ? ' (archived)' : '' }}</td>
            <td>{{ $c->chief_complaint ?: '-' }}</td>
            <td>{{ $c->nurse?->name ?? 'Deleted user' }}</td>
        </tr>
        @empty
        <tr><td colspan="4" class="empty">No consultations on this day.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Medicines dispensed</h2>
<table class="data">
    <thead><tr><th>Time</th><th>Patient</th><th>Medicine</th><th class="num">Quantity</th><th>Given by</th></tr></thead>
    <tbody>
        @forelse($dispensed as $d)
        <tr>
            <td class="nowrap">{{ DisplayFormat::time($d->dispensed_at, '-') }}</td>
            <td>{{ $d->patient?->full_name ?? 'Unknown patient' }}{{ $d->patient?->trashed() ? ' (archived)' : '' }}</td>
            <td>{{ $d->medicine?->name ?? '-' }}</td>
            <td class="num">{{ number_format($d->quantity) }}</td>
            <td>{{ $d->dispensedBy?->name ?? 'Deleted user' }}</td>
        </tr>
        @empty
        <tr><td colspan="5" class="empty">No medicines dispensed on this day.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
