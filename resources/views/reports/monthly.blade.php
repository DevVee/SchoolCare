@extends('layouts.app')

@section('title', 'Monthly report')

@use('App\Support\DisplayFormat')

@php
    $monthStart = \Carbon\Carbon::create($year, $month, 1);
    $daysInMonth = $monthStart->daysInMonth;

    // Visits per day chart (x-ui.chart): one category per day of the month.
    $dayLabels = $dayVisits = $dayConsults = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dayLabels[]   = $monthStart->format('M').' '.$d;
        $dayVisits[]   = (int) ($visitsByDay[$d] ?? 0);
        $dayConsults[] = (int) ($consultationsByDay[$d] ?? 0);
    }

    $sevTotal = max(1, collect($bySeverity ?? [])->sum('total'));
@endphp

@section('content')
{{-- Printed only: letterhead banner (Admin > Settings > Printing), as on the PDF --}}
@include('reports.pdf._letterhead', ['document' => 'reports', 'screen' => true, 'printOnly' => true])

<x-ui.page-header title="Monthly report" :description="$monthStart->format('F Y')"
    :breadcrumbs="['Reports' => route('reports.index'), 'Monthly report' => null]">
    <x-slot:actions>
        <form method="GET" action="{{ route('reports.monthly') }}" class="report-period" data-autosubmit>
            <x-ui.select name="month" aria-label="Month" :selected="$month"
                :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')])->all()" />
            <x-ui.select name="year" aria-label="Year" :selected="$year"
                :options="collect(range(now()->year, now()->year - 5))->mapWithKeys(fn ($y) => [$y => $y])->all()" />
            <noscript><x-ui.button type="submit" variant="secondary">Show</x-ui.button></noscript>
        </form>
        @include('reports.partials.export', ['type' => 'monthly', 'params' => ['year' => $year, 'month' => $month]])
    </x-slot:actions>
</x-ui.page-header>

@include('reports.partials.stat-cards', ['cards' => [
    ['label' => 'Clinic visits', 'value' => $totalVisits, 'module' => 'logbook', 'tone' => 'teal', 'icon' => 'journal-medical'],
    ['label' => 'Patients seen', 'value' => $totalVisitPatients, 'module' => 'patients', 'tone' => 'info', 'icon' => 'people'],
    ['label' => 'Consultations', 'value' => $totalConsultations, 'module' => 'consultations', 'tone' => 'info', 'icon' => 'clipboard2-pulse'],
    ['label' => 'Appointments', 'value' => $totalAppointments, 'module' => 'appointments', 'tone' => 'brand', 'icon' => 'calendar-check'],
    ['label' => 'Units dispensed', 'value' => $totalDispensed, 'module' => 'dispensing', 'tone' => 'success', 'icon' => 'prescription2'],
]])

<x-ui.card class="mb-4" module="logbook" icon="bar-chart" title="Visits per day" :subtitle="$monthStart->format('F Y')">
    <x-ui.chart type="line"
        :series="[['name' => 'Clinic visits', 'data' => $dayVisits], ['name' => 'Consultations', 'data' => $dayConsults]]"
        :categories="$dayLabels" height="280" empty="No visits or consultations this month." />
</x-ui.card>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <x-ui.card class="h-100" module="logbook" icon="clipboard2-pulse" title="Top reasons for visit">
            <x-ui.chart type="line"
                :series="[['name' => 'Visits', 'data' => $topReasons->pluck('total')->all()]]"
                :categories="$topReasons->pluck('reason')->all()" category-label="Reason"
                height="260"
                empty="No visits this month." />
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
                    <x-ui.empty-state compact icon="people" title="No visits this month" />
                </x-slot:empty>
            </x-ui.table>
        </x-ui.card>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <x-ui.card flush module="logbook" icon="activity" title="Visits by severity" class="h-100">
            <x-ui.table dense caption="Visits by severity">
                <x-slot:head>
                    <x-ui.th>Severity</x-ui.th>
                    <x-ui.th align="end">Visits</x-ui.th>
                    <x-ui.th align="end">Share</x-ui.th>
                </x-slot:head>
                @foreach($bySeverity ?? [] as $s)
                <tr>
                    <x-ui.td>{{ $s->label }}</x-ui.td>
                    <x-ui.td numeric>{{ number_format($s->total) }}</x-ui.td>
                    <x-ui.td numeric muted>{{ round($s->total * 100 / $sevTotal) }}%</x-ui.td>
                </tr>
                @endforeach
                <x-slot:empty>
                    <x-ui.empty-state compact icon="activity" title="No visits this month" />
                </x-slot:empty>
            </x-ui.table>
        </x-ui.card>
    </div>
    <div class="col-lg-6">
        <x-ui.card flush module="appointments" title="Appointment status" class="h-100">
            <x-ui.table dense caption="Appointment status">
                <x-slot:head>
                    <x-ui.th>Status</x-ui.th>
                    <x-ui.th align="end">Appointments</x-ui.th>
                </x-slot:head>
                @foreach($appointmentStatus as $s)
                <tr>
                    <x-ui.td><x-ui.status-badge :status="$s->status" type="appointment" :label="$s->label" size="sm" /></x-ui.td>
                    <x-ui.td numeric>{{ number_format($s->total) }}</x-ui.td>
                </tr>
                @endforeach
                <x-slot:foot>
                    <tr>
                        <th scope="row">Total</th>
                        <td class="cell-numeric fw-semibold">{{ number_format($totalAppointments) }}</td>
                    </tr>
                </x-slot:foot>
            </x-ui.table>
        </x-ui.card>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <x-ui.card flush module="patients" title="Top 10 patients by visits" class="h-100">
            <x-ui.table dense caption="Top 10 patients by visits">
                <x-slot:head>
                    <x-ui.th width="3rem">#</x-ui.th>
                    <x-ui.th>Patient</x-ui.th>
                    <x-ui.th priority="md">Category</x-ui.th>
                    <x-ui.th align="end">Visits</x-ui.th>
                </x-slot:head>
                @foreach($topPatients as $i => $p)
                <tr>
                    <x-ui.td muted>{{ $i + 1 }}</x-ui.td>
                    <x-ui.td><x-patient-link :patient="$p->patient" /></x-ui.td>
                    <x-ui.td priority="md" muted>{{ $p->patient?->category_label ?? '-' }}</x-ui.td>
                    <x-ui.td numeric>{{ number_format($p->total) }}</x-ui.td>
                </tr>
                @endforeach
                <x-slot:empty>
                    <x-ui.empty-state compact icon="people" title="No visits this month" />
                </x-slot:empty>
            </x-ui.table>
        </x-ui.card>
    </div>
    <div class="col-lg-6">
        <x-ui.card flush module="medicines" title="Most-used medicines" description="Includes medicines given during clinic visits." class="h-100">
            <x-ui.table dense caption="Most-used medicines">
                <x-slot:head>
                    <x-ui.th>Medicine</x-ui.th>
                    <x-ui.th align="end">Times given</x-ui.th>
                    <x-ui.th align="end">Quantity</x-ui.th>
                </x-slot:head>
                @foreach($topMedicines as $u)
                <tr>
                    <x-ui.td truncate>{{ $u->medicine?->name ?? '-' }}</x-ui.td>
                    <x-ui.td numeric muted>{{ number_format($u->times_dispensed) }}</x-ui.td>
                    <x-ui.td numeric>{{ number_format($u->total_dispensed) }} <span class="text-muted fs-xs">{{ $u->medicine?->unit }}</span></x-ui.td>
                </tr>
                @endforeach
                <x-slot:empty>
                    <x-ui.empty-state compact icon="capsule" title="No medicines given this month" />
                </x-slot:empty>
            </x-ui.table>
            <x-slot:footer class="justify-content-start">
                <div class="report-figures">
                    <div>
                        <div class="report-figure-value">{{ number_format($visitMedicines['units'] ?? 0) }}</div>
                        <div class="report-figure-label">units given during clinic visits</div>
                    </div>
                    <div>
                        <div class="report-figure-value">{{ number_format($visitMedicines['visits'] ?? 0) }}</div>
                        <div class="report-figure-label">visits with medicine</div>
                    </div>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </div>
</div>

{{-- Stock alerts (current snapshot, not limited to the month) --}}
<x-ui.card flush module="inventory" icon="exclamation-triangle" title="Stock alerts" :description="'Current stock as of '.DisplayFormat::date(now())">
    <x-ui.table responsive="stack" caption="Stock alerts">
        <x-slot:head>
            <x-ui.th>Alert</x-ui.th>
            <x-ui.th>Medicine</x-ui.th>
            <x-ui.th align="end">Quantity</x-ui.th>
            <x-ui.th align="end" priority="md">Reorder at</x-ui.th>
            <x-ui.th>Expiry</x-ui.th>
        </x-slot:head>
        @foreach($stockAlerts['expired'] as $m)
        <tr>
            <x-ui.td label="Alert"><x-ui.status-badge status="expired" type="stock" label="Expired, still in stock" size="sm" /></x-ui.td>
            <x-ui.td identity>{{ $m->name }}</x-ui.td>
            <x-ui.td label="Quantity" numeric>{{ number_format($m->quantity) }}</x-ui.td>
            <x-ui.td label="Reorder at" numeric priority="md" muted>{{ number_format($m->low_stock_threshold) }}</x-ui.td>
            <x-ui.td label="Expiry">{{ DisplayFormat::date($m->expiration_date, '-') }}</x-ui.td>
        </tr>
        @endforeach
        @foreach($stockAlerts['expiring'] as $m)
        <tr>
            <x-ui.td label="Alert"><x-ui.status-badge status="expiring" type="stock" :label="'Expires within '.$stockAlerts['expiryWarningDays'].' days'" size="sm" /></x-ui.td>
            <x-ui.td identity>{{ $m->name }}</x-ui.td>
            <x-ui.td label="Quantity" numeric>{{ number_format($m->quantity) }}</x-ui.td>
            <x-ui.td label="Reorder at" numeric priority="md" muted>{{ number_format($m->low_stock_threshold) }}</x-ui.td>
            <x-ui.td label="Expiry">{{ DisplayFormat::date($m->expiration_date, '-') }}</x-ui.td>
        </tr>
        @endforeach
        @foreach($stockAlerts['lowStock'] as $m)
        <tr>
            <x-ui.td label="Alert">
                @if($m->quantity == 0)
                    <x-ui.status-badge status="out_of_stock" type="stock" size="sm" />
                @else
                    <x-ui.status-badge status="low_stock" type="stock" size="sm" />
                @endif
            </x-ui.td>
            <x-ui.td identity>{{ $m->name }}</x-ui.td>
            <x-ui.td label="Quantity" numeric>{{ number_format($m->quantity) }}</x-ui.td>
            <x-ui.td label="Reorder at" numeric priority="md" muted>{{ number_format($m->low_stock_threshold) }}</x-ui.td>
            <x-ui.td label="Expiry">{{ DisplayFormat::date($m->expiration_date, '-') }}</x-ui.td>
        </tr>
        @endforeach
        <x-slot:empty>
            <x-ui.empty-state compact icon="check2-circle" tone="success" title="No stock alerts" />
        </x-slot:empty>
    </x-ui.table>
</x-ui.card>

{{-- Printed only: signatures and footer line, as on the PDF --}}
@include('reports.pdf._signatures', ['document' => 'reports', 'screen' => true])

@endsection
