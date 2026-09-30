@use('App\Support\DisplayFormat')
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Health Report Card, {{ $patient->full_name }}</title>
<meta name="author" content="{{ \App\Support\PrintBranding::issuerName() }}">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0F172A; margin: 0; padding: 22px; }
    h1   { font-size: 16px; margin: 0 0 2px; color: #1D4ED8; }
    h2   { font-size: 12px; margin: 14px 0 6px; color: #1E293B; border-bottom: 1px solid #E2E8F0; padding-bottom: 3px; }
    .muted { color: #64748B; }
    .header { border-bottom: 2px solid #2563EB; padding-bottom: 8px; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th { background: #F1F5F9; color: #1E293B; padding: 4px 6px; text-align: left; font-size: 9px; border-bottom: 1px solid #CBD5E1; }
    td { padding: 4px 6px; border-bottom: 1px solid #E2E8F0; font-size: 9px; vertical-align: top; }
    .kv td { border: none; padding: 2px 6px 2px 0; }
    .kv td.k { color: #64748B; width: 90px; }
    .tiles td { border: 1px solid #E2E8F0; text-align: center; padding: 6px; }
    .tile-num { font-size: 15px; font-weight: bold; color: #1D4ED8; }
    .tile-lbl { font-size: 8px; color: #64748B; }
    .note { background: #EFF6FF; border: 1px solid #BFDBFE; padding: 6px 8px; margin: 8px 0; }
    .right { text-align: right; }
    .footer { margin-top: 16px; font-size: 8px; color: #64748B; }
    .confidential-row { text-align: right; margin: 0 0 4px; }
    .confidential { display: inline-block; border: 0.75px solid #0F172A; padding: 1px 6px; font-size: 7.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #0F172A; }
    .pagefoot { position: fixed; bottom: -8mm; right: 22px; font-size: 7.5px; color: #64748B; }
    .pagenum:before { content: counter(page); }
</style>
</head>
<body>
@php
    // Letterhead, signatures, footer line and page numbers: Admin > Settings > Printing.
    $categoryLabels = \App\Models\Patient::categoryLabels();
    $footerNote = \App\Support\PrintBranding::footerText('health');
    $preparedBy = \App\Support\PrintBranding::preparedByShown('health') ? null : auth()->user()?->name;
@endphp

@if (\App\Support\PrintBranding::pageNumbers('health'))
    <div class="pagefoot">Page <span class="pagenum"></span></div>
@endif

<div class="header">
    <div class="confidential-row"><span class="confidential">Confidential</span></div>
    @include('reports.pdf._letterhead', [
        'document' => 'health',
        'title'    => 'Health Report Card',
        'subtitle' => 'Generated '.DisplayFormat::date(now()).' '.DisplayFormat::time(now()),
    ])
</div>

<h2>{{ $patient->full_name }}</h2>
<table class="kv">
    <tr>
        <td class="k">Patient no.</td><td>{{ $patient->patient_number }}</td>
        <td class="k">Student ID</td><td>{{ $patient->student_id ?: 'Not recorded' }}</td>
    </tr>
    <tr>
        <td class="k">Category</td><td>{{ $categoryLabels[$patient->category] ?? $patient->category }}</td>
        <td class="k">Year / section</td><td>{{ collect([$patient->year_level, $patient->section, $patient->program_strand])->filter()->implode(', ') ?: 'Not set' }}</td>
    </tr>
    <tr>
        <td class="k">Sex</td><td>{{ $patient->sex_label }}</td>
        <td class="k">Age</td><td>{{ $patient->age !== null ? $patient->age.' years' : 'Not recorded' }}{{ $patient->birthdate ? ' (born '.DisplayFormat::date($patient->birthdate).')' : '' }}</td>
    </tr>
    <tr>
        <td class="k">Blood type</td><td>{{ $patient->blood_type ?: 'Unknown' }}</td>
        <td class="k">Guardian</td><td>{{ collect([$patient->guardian_name, $patient->guardian_contact])->filter()->implode(', ') ?: 'Not recorded' }}</td>
    </tr>
    <tr><td class="k">Allergies</td><td colspan="3">{{ $patient->allergies ?: 'None recorded' }}</td></tr>
    <tr><td class="k">Conditions</td><td colspan="3">{{ $patient->medical_conditions ?: 'None recorded' }}</td></tr>
    @if ($patient->current_medications)
        <tr><td class="k">Medications</td><td colspan="3">{{ $patient->current_medications }}</td></tr>
    @endif
</table>

<table class="tiles">
    <tr>
        <td><div class="tile-num">{{ $summary['total_visits'] }}</div><div class="tile-lbl">Clinic visits</div></td>
        <td><div class="tile-num">{{ $summary['visits_this_year'] }}</div><div class="tile-lbl">This year</div></td>
        <td><div class="tile-num" style="font-size:11px;">{{ $summary['last_visit'] ? DisplayFormat::date($summary['last_visit']) : 'None' }}</div><div class="tile-lbl">Last visit</div></td>
        <td><div class="tile-num">{{ $summary['consultations'] }}</div><div class="tile-lbl">Consultations</div></td>
        <td><div class="tile-num">{{ $summary['medicines_units'] }}</div><div class="tile-lbl">Medicine units</div></td>
        <td><div class="tile-num">{{ $summary['appointments'] }}</div><div class="tile-lbl">Appointments</div></td>
    </tr>
</table>

@if ($observation)
    <div class="note"><strong>Observation.</strong> {{ $observation }}</div>
@endif

<table style="margin-top:6px;">
    <tr>
        <td style="border:none; padding:0 8px 0 0; width:50%;">
            <h2>Visits by {{ $reasonSource === 'reasons' ? 'reason' : 'complaint' }}</h2>
            @if ($reasons)
                <table><tr><th>{{ $reasonSource === 'reasons' ? 'Reason' : 'Complaint' }}</th><th class="right">Visits</th></tr>
                    @foreach (array_slice($reasons, 0, 10, true) as $reason => $n)
                        <tr><td>{{ $reason }}</td><td class="right">{{ $n }}</td></tr>
                    @endforeach
                </table>
            @else
                <p class="muted">No clinic visits recorded.</p>
            @endif
        </td>
        <td style="border:none; padding:0 0 0 8px; width:50%;">
            <h2>Visits per month (last 6)</h2>
            <table><tr><th>Month</th><th class="right">Visits</th></tr>
                @foreach ($monthly['labels'] as $i => $label)
                    <tr><td>{{ $label }}</td><td class="right">{{ $monthly['data'][$i] }}</td></tr>
                @endforeach
            </table>
            @if ($severity)
                <h2>By severity</h2>
                <table><tr><th>Severity</th><th class="right">Visits</th></tr>
                    @foreach ($severity as $level => $n)
                        <tr><td>{{ $level }}</td><td class="right">{{ $n }}</td></tr>
                    @endforeach
                </table>
            @endif
        </td>
    </tr>
</table>

<h2>Vital signs</h2>
@if ($vitals['rows'])
    <table>
        <tr><th>Date</th><th>Temp (&deg;C)</th><th>Blood pressure</th><th>Pulse</th><th>Weight (kg)</th><th>Height (cm)</th></tr>
        @foreach (array_reverse($vitals['rows']) as $row)
            <tr>
                <td>{{ DisplayFormat::date($row['date']) }}</td>
                <td>{{ $row['temperature'] ?? '' }}</td>
                <td>{{ $row['bp'] ?? '' }}</td>
                <td>{{ $row['pulse'] ?? '' }}</td>
                <td>{{ $row['weight'] ?? '' }}</td>
                <td>{{ $row['height'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@else
    <p class="muted">No vital signs recorded.</p>
@endif

<h2>Clinic visits</h2>
@if ($logs->isNotEmpty())
    <table>
        <tr><th>Date</th><th>Time</th><th>Reason / complaint</th><th>Treatment</th><th>Disposition</th></tr>
        @foreach ($logs->take(40) as $log)
            <tr>
                <td>{{ DisplayFormat::date($log->log_date) }}</td>
                <td>{{ DisplayFormat::time($log->time_in) }}</td>
                <td>{{ \Illuminate\Support\Str::limit($log->complaint_summary, 90) }}{{ $log->getAttribute('severity') ? ' ('.$log->getAttribute('severity').')' : '' }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $log->treatment, 60) }}</td>
                <td>{{ $log->disposition_label }}</td>
            </tr>
        @endforeach
    </table>
@else
    <p class="muted">No clinic visits recorded.</p>
@endif

<h2>Medicines received</h2>
@if ($medicineTotals)
    <table>
        <tr><th>Medicine</th><th class="right">Times</th><th class="right">Total quantity</th></tr>
        @foreach ($medicineTotals as $m)
            <tr><td>{{ $m['name'] }}</td><td class="right">{{ $m['times'] }}</td><td class="right">{{ $m['quantity'] }} {{ $m['unit'] }}</td></tr>
        @endforeach
    </table>
@else
    <p class="muted">No medicines dispensed.</p>
@endif

<h2>Consultations</h2>
@if ($consultations->isNotEmpty())
    <table>
        <tr><th>Date</th><th>Complaint</th><th>Diagnosis</th><th>Treatment</th></tr>
        @foreach ($consultations->take(20) as $c)
            <tr>
                <td>{{ DisplayFormat::date($c->visit_date) }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $c->chief_complaint, 60) }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $c->diagnosis, 60) }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $c->treatment, 60) }}</td>
            </tr>
        @endforeach
    </table>
@else
    <p class="muted">No consultations recorded.</p>
@endif

<h2>Appointments</h2>
@if ($appointments->isNotEmpty())
    <table>
        <tr><th>Date</th><th>Time</th><th>Purpose</th><th>With</th><th>Status</th></tr>
        @foreach ($appointments->take(20) as $a)
            <tr>
                <td>{{ DisplayFormat::date($a->appointment_date) }}</td>
                <td>{{ DisplayFormat::time($a->appointment_time) }}</td>
                <td>{{ $a->purpose }}</td>
                <td>{{ $a->provider }}</td>
                <td>{{ \App\Models\Appointment::statusLabels()[$a->status] ?? $a->status }}</td>
            </tr>
        @endforeach
    </table>
@else
    <p class="muted">No appointments recorded.</p>
@endif

@include('reports.pdf._signatures', ['document' => 'health'])

@if ($footerNote !== '' || $preparedBy)
    <div class="footer">{{ $footerNote }}@if ($preparedBy){{ $footerNote !== '' ? ' ' : '' }}Prepared by {{ $preparedBy }}.@endif</div>
@endif
</body>
</html>
