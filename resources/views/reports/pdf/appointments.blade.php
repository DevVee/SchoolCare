@extends('reports.pdf._layout')

@use('App\Support\DisplayFormat')

@php
    $reportTitle  = 'Appointments report';
    $reportPeriod = DisplayFormat::date($from).' to '.DisplayFormat::date($to);
    $statusLabels = \App\Models\Appointment::statusLabels();
@endphp

@section('content')
<table class="summary">
    <tr>
        @foreach($statusLabels as $status => $label)
        <td><div class="n">{{ number_format($byStatus[$status] ?? 0) }}</div><div class="l">{{ $label }}</div></td>
        @endforeach
        <td><div class="n">{{ number_format($appointments->count()) }}</div><div class="l">Total</div></td>
    </tr>
</table>

<h2>Appointment list</h2>
<table class="data">
    <thead>
        <tr><th>Date</th><th>Time</th><th>Patient</th><th>Purpose</th><th>Status</th></tr>
    </thead>
    <tbody>
        @forelse($appointments as $a)
        <tr>
            <td class="nowrap">{{ DisplayFormat::date($a->appointment_date, '-') }}</td>
            <td class="nowrap">{{ DisplayFormat::time($a->appointment_time, '-') }}</td>
            <td>{{ $a->patient?->full_name ?? $a->requester_name ?? 'Unknown patient' }}{{ $a->patient?->trashed() ? ' (archived)' : '' }}</td>
            <td>{{ $a->purpose ?: '-' }}</td>
            <td>{{ $statusLabels[$a->status] ?? ucfirst(str_replace('_', ' ', $a->status)) }}</td>
        </tr>
        @empty
        <tr><td colspan="5" class="empty">No appointments in this period.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
