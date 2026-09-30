@extends('layouts.app')

@section('title', 'Health report: ' . $patient->full_name)

@use('App\Support\DisplayFormat')
@php
    $categoryLabels = \App\Models\Patient::categoryLabels();
    $brandLogo  = settings()->imageUrl('brand_logo', '/schoolcare-icon.svg');
    $schoolLogo = settings()->imageUrl('school_logo', '');
    $orgName    = trim((string) settings('org_name', ''));
    $clinicName = settings('clinic_name') ?: settings('app_name');
    $hasCharts  = view()->exists('components.ui.chart');
    $academic   = collect([$patient->year_level, $patient->section, $patient->program_strand])->filter()->implode(', ');
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Health report" class="no-print"
        :description="'Summary of the clinic records of '.$patient->full_name.', ready to print or share with the family.'"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => route('patients.index'), $patient->full_name => route('patients.show', $patient->id), 'Health report' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="printer" onclick="window.print()">Print</x-ui.button>
            <x-ui.button icon="file-earmark-pdf" :href="route('patients.health-report.pdf', $patient->id)">Download PDF</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

<article class="health-report vstack gap-3" aria-labelledby="hr-title">

    {{-- Letterhead --}}
    <x-ui.card>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <img src="{{ $brandLogo }}" alt="" width="48" height="48" class="hr-logo">
            <div class="flex-grow-1 min-w-0">
                <div class="small text-muted">{{ $clinicName }}@if ($orgName !== ''), {{ $orgName }}@endif</div>
                <h1 class="h4 mb-0 font-display" id="hr-title">Health Report Card</h1>
                <div class="small text-muted">Prepared {{ DisplayFormat::date(now()) }} by {{ auth()->user()->name }}</div>
            </div>
            @if ($schoolLogo !== '')
                <img src="{{ $schoolLogo }}" alt="{{ $orgName !== '' ? $orgName.' seal' : 'School seal' }}" width="48" height="48" class="hr-logo">
            @endif
        </div>
    </x-ui.card>

    {{-- Patient summary --}}
    <x-ui.card>
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <x-ui.avatar :name="$patient->full_name" size="lg" class="no-print" />
                <div class="min-w-0">
                    <h2 class="h5 mb-1" id="hr-patient">{{ $patient->full_name }}</h2>
                    <div class="small text-muted">
                        <span class="tabular">{{ $patient->patient_number }}</span>@if ($patient->student_id), ID {{ $patient->student_id }}@endif,
                        {{ $categoryLabels[$patient->category] ?? $patient->category }}@if ($academic), {{ $academic }}@endif
                    </div>
                </div>
            </div>
            @if ($patient->trashed())
                <x-ui.badge color="neutral" icon="archive" :dot="false">Archived</x-ui.badge>
            @endif
        </div>

        <x-ui.description-list>
            <x-ui.description-item label="Sex">{{ $patient->sex_label }}</x-ui.description-item>
            <x-ui.description-item label="Age">{{ $patient->age !== null ? $patient->age.' years' : '' }}</x-ui.description-item>
            <x-ui.description-item label="Blood type" empty="Unknown">{{ $patient->blood_type }}</x-ui.description-item>
            <x-ui.description-item label="Allergies" empty="None recorded" :class="$patient->allergies ? 'text-danger fw-semibold' : ''">{{ $patient->allergies }}</x-ui.description-item>
            <x-ui.description-item label="Conditions" empty="None recorded">{{ $patient->medical_conditions }}</x-ui.description-item>
            @if ($patient->current_medications)
                <x-ui.description-item label="Medications">{{ $patient->current_medications }}</x-ui.description-item>
            @endif
            @if ($patient->guardian_name || $patient->guardian_contact)
                <x-ui.description-item label="Guardian">{{ collect([$patient->guardian_name, $patient->guardian_relationship ? '('.$patient->guardian_relationship.')' : null, $patient->guardian_contact])->filter()->implode(' ') }}</x-ui.description-item>
            @endif
            @if ($patient->pediatrician_name)
                <x-ui.description-item label="Pediatrician">{{ $patient->pediatrician_name }}@if ($patient->pediatrician_contact), {{ $patient->pediatrician_contact }}@endif</x-ui.description-item>
            @endif
        </x-ui.description-list>
    </x-ui.card>

    {{-- Overview numbers --}}
    <section aria-label="Overview">
        <x-ui.stat-cards cols="6">
            <x-ui.stat-card label="Clinic visits" :value="$summary['total_visits']" tone="logbook" />
            <x-ui.stat-card label="Visits this year" :value="$summary['visits_this_year']" tone="logbook" icon="calendar3" />
            <x-ui.stat-card label="Last visit" :value="$summary['last_visit'] ? DisplayFormat::date($summary['last_visit']) : 'None'" tone="logbook" icon="clock-history" />
            <x-ui.stat-card label="Consultations" :value="$summary['consultations']" tone="consultations" />
            <x-ui.stat-card label="Medicine units given" :value="$summary['medicines_units']" tone="dispensing" icon="capsule" />
            <x-ui.stat-card label="Appointments" :value="$summary['appointments']" tone="appointments" />
        </x-ui.stat-cards>
    </section>

    @if ($observation)
        <x-ui.alert variant="info" icon="lightbulb" title="Observation">{{ $observation }}</x-ui.alert>
    @endif

    {{-- Charts --}}
    @if ($hasCharts)
        @include('patients.partials.health-report-charts')
    @endif

    {{-- Visit reasons + severity as tables (also what prints when charts cannot) --}}
    <div class="row g-3">
        <section class="col-lg-6" aria-labelledby="hr-reasons">
            <x-ui.card flush class="h-100" :title="'Visits by '.($reasonSource === 'reasons' ? 'reason' : 'complaint')" id="hr-reasons">
                <x-ui.table dense :caption="'Visits by '.($reasonSource === 'reasons' ? 'reason' : 'complaint')">
                    <x-slot:head><x-ui.th>{{ $reasonSource === 'reasons' ? 'Reason' : 'Complaint' }}</x-ui.th><x-ui.th align="end">Visits</x-ui.th></x-slot:head>
                    @foreach (array_slice($reasons, 0, 10, true) as $reason => $n)
                        <tr><x-ui.td truncate>{{ $reason }}</x-ui.td><x-ui.td numeric>{{ $n }}</x-ui.td></tr>
                    @endforeach
                    <x-slot:empty><x-ui.empty-state quiet icon="journal-x" title="No clinic visits recorded." /></x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </section>
        <section class="col-lg-6" aria-labelledby="hr-severity">
            <x-ui.card flush class="h-100" title="Visits by severity" id="hr-severity">
                <x-ui.table dense caption="Visits by severity">
                    <x-slot:head><x-ui.th>Severity</x-ui.th><x-ui.th align="end">Visits</x-ui.th></x-slot:head>
                    @foreach ($severity as $level => $n)
                        <tr><x-ui.td>{{ $level }}</x-ui.td><x-ui.td numeric>{{ $n }}</x-ui.td></tr>
                    @endforeach
                    <x-slot:empty><x-ui.empty-state quiet icon="activity" title="Severity was not recorded for these visits." /></x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </section>
    </div>

    {{-- Vital signs --}}
    <x-ui.card flush title="Vital signs from clinic visits" id="hr-vitals">
        <x-ui.table dense caption="Vital signs">
            <x-slot:head>
                <x-ui.th>Date</x-ui.th><x-ui.th align="end">Temp (°C)</x-ui.th><x-ui.th align="end">Blood pressure</x-ui.th>
                <x-ui.th align="end">Pulse</x-ui.th><x-ui.th align="end">Weight (kg)</x-ui.th><x-ui.th align="end">Height (cm)</x-ui.th>
            </x-slot:head>
            @foreach (array_reverse($vitals['rows']) as $row)
                <tr>
                    <x-ui.td>{{ DisplayFormat::date($row['date']) }}</x-ui.td>
                    <x-ui.td numeric>{{ $row['temperature'] ?? '-' }}</x-ui.td>
                    <x-ui.td numeric>{{ $row['bp'] ?? '-' }}</x-ui.td>
                    <x-ui.td numeric>{{ $row['pulse'] ?? '-' }}</x-ui.td>
                    <x-ui.td numeric>{{ $row['weight'] ?? '-' }}</x-ui.td>
                    <x-ui.td numeric>{{ $row['height'] ?? '-' }}</x-ui.td>
                </tr>
            @endforeach
            <x-slot:empty><x-ui.empty-state quiet icon="heart-pulse" title="No vital signs were recorded during clinic visits." /></x-slot:empty>
        </x-ui.table>
    </x-ui.card>

    {{-- Clinic visits --}}
    <x-ui.card flush :title="'Clinic visits ('.$logs->count().')'" id="hr-visits">
        <x-ui.table dense caption="Clinic visits">
            <x-slot:head>
                <x-ui.th>Date</x-ui.th><x-ui.th>Time</x-ui.th><x-ui.th>Reason or complaint</x-ui.th><x-ui.th>Treatment</x-ui.th><x-ui.th>Outcome</x-ui.th>
            </x-slot:head>
            @foreach ($logs->take(30) as $log)
                <tr>
                    <x-ui.td>{{ DisplayFormat::date($log->log_date) }}</x-ui.td>
                    <x-ui.td muted>{{ DisplayFormat::time($log->time_in) }}</x-ui.td>
                    <x-ui.td truncate>{{ $log->complaint_summary }}@if ($log->severity) ({{ $log->severity }})@endif</x-ui.td>
                    <x-ui.td truncate>{{ $log->treatment ?: '-' }}</x-ui.td>
                    <x-ui.td>{{ $log->disposition_label }}</x-ui.td>
                </tr>
            @endforeach
            <x-slot:empty><x-ui.empty-state quiet icon="journal-x" title="No clinic visits recorded." /></x-slot:empty>
            @if ($logs->count() > 30)
                <x-slot:footer>
                    <span>Showing the latest 30 of {{ $logs->count() }} visits.</span>
                    <a href="{{ route('patients.history', $patient->id) }}" class="no-print">See the full history</a>
                </x-slot:footer>
            @endif
        </x-ui.table>
    </x-ui.card>

    <div class="row g-3">
        {{-- Medicines received --}}
        <section class="col-lg-6" aria-labelledby="hr-meds">
            <x-ui.card flush class="h-100" title="Medicines received" id="hr-meds">
                <x-ui.table dense caption="Medicines received">
                    <x-slot:head><x-ui.th>Medicine</x-ui.th><x-ui.th align="end">Times</x-ui.th><x-ui.th align="end">Total qty</x-ui.th></x-slot:head>
                    @foreach ($medicineTotals as $m)
                        <tr>
                            <x-ui.td truncate>{{ $m['name'] }}</x-ui.td>
                            <x-ui.td numeric>{{ $m['times'] }}</x-ui.td>
                            <x-ui.td numeric>{{ $m['quantity'] }} {{ $m['unit'] }}</x-ui.td>
                        </tr>
                    @endforeach
                    <x-slot:empty><x-ui.empty-state quiet icon="capsule" title="No medicines dispensed." /></x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </section>

        {{-- Consultations --}}
        <section class="col-lg-6" aria-labelledby="hr-consults">
            <x-ui.card flush class="h-100" title="Consultations" id="hr-consults">
                <x-ui.table dense caption="Consultations">
                    <x-slot:head><x-ui.th>Date</x-ui.th><x-ui.th>Complaint</x-ui.th><x-ui.th>Diagnosis</x-ui.th></x-slot:head>
                    @foreach ($consultations->take(15) as $c)
                        <tr>
                            <x-ui.td>{{ DisplayFormat::date($c->visit_date) }}</x-ui.td>
                            <x-ui.td truncate>{{ (string) $c->chief_complaint }}</x-ui.td>
                            <x-ui.td truncate>{{ $c->diagnosis ?: '-' }}</x-ui.td>
                        </tr>
                    @endforeach
                    <x-slot:empty><x-ui.empty-state quiet icon="clipboard2-pulse" title="No consultations recorded." /></x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </section>
    </div>

    {{-- Appointments --}}
    <x-ui.card flush title="Appointments" id="hr-appts">
        <x-ui.table dense caption="Appointments">
            <x-slot:head><x-ui.th>Date</x-ui.th><x-ui.th>Time</x-ui.th><x-ui.th>Purpose</x-ui.th><x-ui.th>With</x-ui.th><x-ui.th>Status</x-ui.th></x-slot:head>
            @foreach ($appointments->take(15) as $a)
                <tr>
                    <x-ui.td>{{ DisplayFormat::date($a->appointment_date) }}</x-ui.td>
                    <x-ui.td muted>{{ DisplayFormat::time($a->appointment_time) }}</x-ui.td>
                    <x-ui.td truncate>{{ $a->purpose }}</x-ui.td>
                    <x-ui.td truncate>{{ $a->provider ?: '-' }}</x-ui.td>
                    <x-ui.td><x-ui.status-badge :status="$a->status" type="appointment" :label="\App\Models\Appointment::statusLabels()[$a->status] ?? null" /></x-ui.td>
                </tr>
            @endforeach
            <x-slot:empty><x-ui.empty-state quiet icon="calendar-x" title="No appointments recorded." /></x-slot:empty>
        </x-ui.table>
    </x-ui.card>

    <p class="small text-muted print-only">This report was generated from the clinic records on {{ DisplayFormat::date(now()) }}. It is confidential health information.</p>
</article>
</div>
@endsection

@push('styles')
<style>
.hr-logo { object-fit: contain; border-radius: 8px; }
.print-only { display: none; }
@media print {
    .no-print, footer, .flash-toasts, .c-toasts { display: none !important; }
    .print-only { display: block; }
    .health-report .card, .health-report .stat-tile { border: 1px solid #E2E8F0 !important; box-shadow: none !important; break-inside: avoid; }
    .health-report .card-header { background: #F8FAFC !important; }
    .health-report a { color: inherit; text-decoration: none; }
    .health-report .cell-truncate { max-width: none; white-space: normal; }
    @page { margin: 14mm; }
}
</style>
@endpush
