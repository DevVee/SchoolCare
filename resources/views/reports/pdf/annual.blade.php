@extends('reports.pdf._layout')

@php
    $reportTitle  = 'Annual report';
    $reportPeriod = 'January to December '.$year;
@endphp

@section('content')
<table class="summary">
    <tr>
        <td><div class="n">{{ number_format($totalVisits) }}</div><div class="l">Clinic visits</div></td>
        <td><div class="n">{{ number_format($totalVisitPatients) }}</div><div class="l">Patients seen</div></td>
        <td><div class="n">{{ number_format($totalConsultations) }}</div><div class="l">Consultations</div></td>
        <td><div class="n">{{ number_format($totalAppointments) }}</div><div class="l">Appointments</div></td>
    </tr>
</table>

<h2>Month by month</h2>
<table class="data">
    <thead><tr><th>Month</th><th class="num">Clinic visits</th><th class="num">Consultations</th><th class="num">Appointments</th></tr></thead>
    <tbody>
        @foreach($monthlyData as $row)
        <tr>
            <td>{{ $row['month'] }}</td>
            <td class="num">{{ number_format($row['visits']) }}</td>
            <td class="num">{{ number_format($row['consultations']) }}</td>
            <td class="num">{{ number_format($row['appointments']) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td class="num">{{ number_format($totalVisits) }}</td>
            <td class="num">{{ number_format($totalConsultations) }}</td>
            <td class="num">{{ number_format($totalAppointments) }}</td>
        </tr>
    </tfoot>
</table>

<table class="cols">
    <tr>
        <td class="col" style="width:48%;">
            <h2>Visits by patient category</h2>
            <table class="data">
                <thead><tr><th>Category</th><th class="num">Visits</th><th class="num">Patients</th></tr></thead>
                <tbody>
                    @forelse($byCategory as $cat)
                    <tr><td>{{ $cat->label }}</td><td class="num">{{ number_format($cat->total) }}</td><td class="num">{{ number_format($cat->patients) }}</td></tr>
                    @empty
                    <tr><td colspan="3" class="empty">No visits this year.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </td>
        <td class="gap"></td>
        <td class="col" style="width:48%;">
            <h2>Top reasons for visit</h2>
            <table class="data">
                <thead><tr><th>#</th><th>Reason</th><th class="num">Visits</th></tr></thead>
                <tbody>
                    @forelse($topReasons as $i => $r)
                    <tr><td>{{ $i + 1 }}</td><td>{{ $r->reason }}</td><td class="num">{{ number_format($r->total) }}</td></tr>
                    @empty
                    <tr><td colspan="3" class="empty">No visits this year.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </td>
    </tr>
</table>
@endsection
