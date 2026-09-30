@extends('reports.pdf._layout')

@use('App\Support\DisplayFormat')

@php
    $monthStart   = \Carbon\Carbon::create($year, $month, 1);
    $reportTitle  = 'Monthly report';
    $reportPeriod = $monthStart->format('F Y');
    $sevTotal     = max(1, collect($bySeverity ?? [])->sum('total'));
@endphp

@section('content')
<table class="summary">
    <tr>
        <td><div class="n">{{ number_format($totalVisits) }}</div><div class="l">Clinic visits</div></td>
        <td><div class="n">{{ number_format($totalVisitPatients) }}</div><div class="l">Patients seen</div></td>
        <td><div class="n">{{ number_format($totalConsultations) }}</div><div class="l">Consultations</div></td>
        <td><div class="n">{{ number_format($totalAppointments) }}</div><div class="l">Appointments</div></td>
        <td><div class="n">{{ number_format($totalDispensed) }}</div><div class="l">Units dispensed</div></td>
    </tr>
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
                    <tr><td colspan="3" class="empty">No visits this month.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h2>Visits by severity</h2>
            <table class="data">
                <thead><tr><th>Severity</th><th class="num">Visits</th><th class="num">Share</th></tr></thead>
                <tbody>
                    @forelse($bySeverity ?? [] as $s)
                    <tr><td>{{ $s->label }}</td><td class="num">{{ number_format($s->total) }}</td><td class="num">{{ round($s->total * 100 / $sevTotal) }}%</td></tr>
                    @empty
                    <tr><td colspan="3" class="empty">No visits this month.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h2>Appointment status</h2>
            <table class="data">
                <thead><tr><th>Status</th><th class="num">Appointments</th></tr></thead>
                <tbody>
                    @foreach($appointmentStatus as $s)
                    <tr><td>{{ $s->label }}</td><td class="num">{{ number_format($s->total) }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr><td>Total</td><td class="num">{{ number_format($totalAppointments) }}</td></tr></tfoot>
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
                    <tr><td colspan="3" class="empty">No visits this month.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h2>Medicines given during clinic visits</h2>
            <p>{{ number_format($visitMedicines['units'] ?? 0) }} units given in {{ number_format($visitMedicines['visits'] ?? 0) }} {{ Str::plural('visit', $visitMedicines['visits'] ?? 0) }}. These are included in the most-used medicines below.</p>
        </td>
    </tr>
</table>

<h2>Top 10 patients by visits</h2>
<table class="data">
    <thead><tr><th>#</th><th>Patient</th><th>Patient no.</th><th>Category</th><th class="num">Visits</th></tr></thead>
    <tbody>
        @forelse($topPatients as $i => $p)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $p->patient?->full_name ?? 'Unknown patient' }}{{ $p->patient?->trashed() ? ' (archived)' : '' }}</td>
            <td class="nowrap">{{ $p->patient?->patient_number ?? '-' }}</td>
            <td>{{ $p->patient?->category_label ?? '-' }}</td>
            <td class="num">{{ number_format($p->total) }}</td>
        </tr>
        @empty
        <tr><td colspan="5" class="empty">No visits this month.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Most-used medicines</h2>
<table class="data">
    <thead><tr><th>Medicine</th><th>Category</th><th class="num">Times given</th><th class="num">Quantity</th></tr></thead>
    <tbody>
        @forelse($topMedicines as $u)
        <tr>
            <td>{{ $u->medicine?->name ?? '-' }}</td>
            <td>{{ $u->medicine?->category?->name ?? '-' }}</td>
            <td class="num">{{ number_format($u->times_dispensed) }}</td>
            <td class="num">{{ number_format($u->total_dispensed) }} {{ $u->medicine?->unit }}</td>
        </tr>
        @empty
        <tr><td colspan="4" class="empty">No medicines given this month.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Stock alerts (current stock as of {{ DisplayFormat::date(now()) }})</h2>
<table class="data">
    <thead><tr><th>Alert</th><th>Medicine</th><th class="num">Quantity</th><th class="num">Reorder at</th><th>Expiry</th></tr></thead>
    <tbody>
        @foreach($stockAlerts['expired'] as $m)
        <tr><td>Expired, still in stock</td><td>{{ $m->name }}</td><td class="num">{{ number_format($m->quantity) }}</td><td class="num">{{ number_format($m->low_stock_threshold) }}</td><td class="nowrap">{{ DisplayFormat::date($m->expiration_date, '-') }}</td></tr>
        @endforeach
        @foreach($stockAlerts['expiring'] as $m)
        <tr><td>Expires within {{ $stockAlerts['expiryWarningDays'] }} days</td><td>{{ $m->name }}</td><td class="num">{{ number_format($m->quantity) }}</td><td class="num">{{ number_format($m->low_stock_threshold) }}</td><td class="nowrap">{{ DisplayFormat::date($m->expiration_date, '-') }}</td></tr>
        @endforeach
        @foreach($stockAlerts['lowStock'] as $m)
        <tr><td>{{ $m->quantity == 0 ? 'Out of stock' : 'Low stock' }}</td><td>{{ $m->name }}</td><td class="num">{{ number_format($m->quantity) }}</td><td class="num">{{ number_format($m->low_stock_threshold) }}</td><td class="nowrap">{{ DisplayFormat::date($m->expiration_date, '-') }}</td></tr>
        @endforeach
        @if($stockAlerts['expired']->isEmpty() && $stockAlerts['expiring']->isEmpty() && $stockAlerts['lowStock']->isEmpty())
        <tr><td colspan="5" class="empty">No stock alerts.</td></tr>
        @endif
    </tbody>
</table>

@php
    $activeDays = collect(range(1, $monthStart->daysInMonth))
        ->filter(fn ($d) => ($visitsByDay[$d] ?? 0) || ($consultationsByDay[$d] ?? 0));
@endphp
<h2>Visits per day</h2>
<table class="data">
    <thead><tr><th>Day</th><th class="num">Clinic visits</th><th class="num">Consultations</th></tr></thead>
    <tbody>
        @forelse($activeDays as $d)
        <tr>
            <td>{{ \Carbon\Carbon::create($year, $month, $d)->format('D, M j') }}</td>
            <td class="num">{{ number_format($visitsByDay[$d] ?? 0) }}</td>
            <td class="num">{{ number_format($consultationsByDay[$d] ?? 0) }}</td>
        </tr>
        @empty
        <tr><td colspan="3" class="empty">No visits or consultations this month.</td></tr>
        @endforelse
    </tbody>
    @if($activeDays->isNotEmpty())
    <tfoot><tr><td>Total</td><td class="num">{{ number_format($totalVisits) }}</td><td class="num">{{ number_format($totalConsultations) }}</td></tr></tfoot>
    @endif
</table>
@endsection
