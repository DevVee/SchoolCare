{{--
    Individual (or student) health record: a plain, official A4 document (dompdf).
    Rendered by PatientHealthReportController::pdf(), which keeps it to one page for a
    typical patient and never more than two: it passes $historyLimit (how many of the
    newest history rows to list) and $keepWithClosing (last rows carried to the final
    page so the certification never stands alone). Rendered without them it lists up
    to 60 rows. Page numbers ("Page 1 of 2") are drawn by the controller after layout.

    Letterhead, signatories and footer text follow Admin > Settings > Printing
    (App\Support\PrintBranding, partials reports/pdf/_letterhead and _signatures).
    Names are the school and clinic settings, never the product name.
--}}
@use('App\Support\DisplayFormat')
@use('App\Support\PrintBranding')
@use('Illuminate\Support\Str')
@php
    // ── Document ─────────────────────────────────────────────────────────────
    $clinic   = PrintBranding::clinicName();
    $school   = PrintBranding::orgName();
    $keeper   = $school !== '' && $school !== $clinic ? $clinic.', '.$school : $clinic;
    $footer   = PrintBranding::footerText('health');
    $preparer = PrintBranding::preparedByShown('health') ? null : auth()->user()?->name;
    $signers  = PrintBranding::signatories('health', auth()->user());

    $category  = (string) $patient->category;
    $students  = ['college', 'senior_high', 'junior_high', 'elementary', 'kinder', 'daycare'];
    $others    = ['teacher', 'employee', 'visitor', 'alumni', 'other'];
    $schooling = collect([$patient->student_id, $patient->year_level, $patient->section, $patient->program_strand])->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty();
    $isStudent = in_array($category, $students, true) || (! in_array($category, $others, true) && $schooling);
    $docTitle  = $isStudent ? 'Student Health Record' : 'Individual Health Record';

    $blank = fn ($v) => trim((string) $v) === '';
    $staff = fn ($user) => $user && $user->exists ? (string) $user->name : '';
    $clean = fn ($v) => trim(preg_replace('/\s+/u', ' ', (string) $v));
    $box   = fn ($v) => $blank($v) ? '&nbsp;' : e($clean($v)); // an empty box keeps its height
    $sentence = function ($text) use ($clean) {
        $text = $clean($text);

        return $text === '' ? '' : (preg_match('/[.!?]$/u', $text) ? $text : $text.'.');
    };

    // ── Medical profile ──────────────────────────────────────────────────────
    $latest = $vitals['rows'] ? end($vitals['rows']) : null;
    $unit   = fn ($v, $suffix) => $blank($v) ? '' : $clean($v).$suffix;

    // ── History: clinic visits, consultations and medicines, newest first ────
    $units   = fn ($unit, $qty) => preg_match('/^[a-z]{4,}$/i', (string) $unit) ? Str::plural((string) $unit, (int) $qty) : (string) $unit; // tablets, capsules; ml stays ml
    $medLine = fn ($records) => $records->map(fn ($d) => $clean(($d->medicine?->name ?? 'Medicine').' '.$d->quantity.' '.$units($d->medicine?->unit, $d->quantity)))->implode(', ');
    $action  = fn (array $parts) => collect($parts)->filter()->unique(fn ($p) => mb_strtolower($p))->implode(' ');
    $byLog     = $dispensed->filter(fn ($d) => $d->patient_log_id)->groupBy('patient_log_id');
    $byConsult = $dispensed->filter(fn ($d) => ! $d->patient_log_id && $d->consultation_id)->groupBy('consultation_id');
    $loose     = $dispensed->filter(fn ($d) => ! $d->patient_log_id && ! $d->consultation_id)
        ->groupBy(fn ($d) => $d->dispensed_at?->format('Y-m-d') ?? '');

    $history = [];
    foreach ($logs as $log) {
        $meds = $byLog->get($log->id);
        $history[] = [
            'sort'      => ($log->log_date?->format('Y-m-d') ?? '').' '.$log->time_in,
            'date'      => $log->log_date,
            'type'      => 'Clinic visit',
            'complaint' => $clean($log->complaint_summary).($blank($log->getAttribute('severity')) ? '' : ' ('.$clean($log->getAttribute('severity')).')'),
            'action'    => $action([
                $sentence($log->assessment),
                $sentence($log->treatment),
                $meds ? $sentence('Given '.$medLine($meds)) : '',
                $blank($log->disposition) ? '' : $sentence($log->disposition_label),
            ]),
            'by'        => $staff($log->loggedBy),
        ];
    }
    foreach ($consultations as $c) {
        $meds = $byConsult->get($c->id);
        $history[] = [
            'sort'      => ($c->visit_date?->format('Y-m-d') ?? '').' '.$c->visit_time,
            'date'      => $c->visit_date,
            'type'      => 'Consultation',
            'complaint' => $clean($c->chief_complaint),
            'action'    => $action([
                $sentence($c->assessment),
                $blank($c->diagnosis) ? '' : $sentence('Diagnosis: '.$clean($c->diagnosis)),
                $sentence($c->treatment),
                $meds ? $sentence('Given '.$medLine($meds)) : '',
            ]),
            'by'        => $staff($c->nurse),
        ];
    }
    foreach ($loose as $day => $records) {
        $first = $records->first();
        $history[] = [
            'sort'      => $day.' '.($first->dispensed_at?->format('H:i') ?? ''),
            'date'      => $first->dispensed_at,
            'type'      => 'Medicine',
            'complaint' => $records->pluck('remarks')->map($clean)->filter()->unique()->implode('; '),
            'action'    => $sentence('Given '.$medLine($records)),
            'by'        => $staff($first->dispensedBy),
        ];
    }
    usort($history, fn ($a, $b) => strcmp($b['sort'], $a['sort']));

    // Consultations past the 50 the report loads still count as earlier entries.
    $entries  = count($history) + max(0, (int) $summary['consultations'] - $consultations->count());
    $rows     = array_slice($history, 0, max(0, (int) ($historyLimit ?? 60)));
    $earlier  = $entries - count($rows);
    $keep     = min(count($rows) - 1, max(0, (int) ($keepWithClosing ?? 0)));
    $showType = collect($history)->contains(fn ($r) => $r['type'] !== 'Clinic visit');
    $columns  = $showType ? 5 : 4;

    // ── Identification ───────────────────────────────────────────────────────
    $guardian = $clean(collect([$patient->guardian_name, $blank($patient->guardian_relationship) ? null : '('.$clean($patient->guardian_relationship).')'])->filter()->implode(' '));
    $showGuardian = $isStudent || ! $blank($patient->guardian_name) || ! $blank($patient->guardian_contact);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $docTitle }}, {{ $patient->full_name }}</title>
<meta name="author" content="{{ PrintBranding::issuerName() }}">
<style>
    @page { margin: 12mm 18mm 19mm 18mm; }
    body { font-family: 'DejaVu Serif', serif; font-size: 9.5pt; line-height: 1.25; color: #000; margin: 0; }
    table { border-collapse: collapse; }
    p { margin: 0; }

    /* Letterhead and title */
    .letterhead-rule { border-bottom: 0.75pt solid #000; margin: 2mm 0 3mm; }
    .doc-title { text-align: center; font-size: 14pt; font-weight: bold; letter-spacing: 1.5pt; text-transform: uppercase; line-height: 1.2; }
    .doc-meta { width: 100%; margin: 1.5mm 0 3.5mm; font-size: 9pt; }
    .doc-meta td { padding: 0; vertical-align: middle; }
    .doc-meta .k { color: #444; }
    .mark { font-size: 7.5pt; font-weight: bold; letter-spacing: 2pt; text-transform: uppercase; border: 0.75pt solid #000; padding: 0.4mm 1.4mm 0.2mm 2mm; }

    /* Sections */
    .section { page-break-inside: avoid; margin: 0 0 3.5mm; }
    .section-title { font-size: 8.5pt; font-weight: bold; letter-spacing: 0.8pt; text-transform: uppercase; line-height: 1.2; margin: 0 0 1mm; }

    /* Form grid: one table per row, so each row sets its own boxes */
    .grid { width: 100%; }
    .grid td { border: 0.5pt solid #555; border-top: none; padding: 0.5mm 1.6mm 0.7mm; vertical-align: top; font-size: 6.5pt; line-height: 1.1; }
    .grid.first td { border-top: 0.5pt solid #555; }
    .lbl { font-size: 6.5pt; line-height: 1.15; color: #444; text-transform: uppercase; letter-spacing: 0.3pt; }
    .val { font-size: 9.5pt; line-height: 1.2; margin-top: 0.3mm; }
    .val.strong { font-weight: bold; }
    .val.none { color: #444; font-style: italic; }

    /* Visit history */
    .history { width: 100%; margin: 0; }
    .history th.head { text-align: left; padding: 0 0 1mm; border: none; }
    .history th.col { font-size: 6.5pt; font-weight: normal; line-height: 1.15; color: #444; text-transform: uppercase; letter-spacing: 0.3pt; text-align: left; border: 0.5pt solid #555; padding: 0.7mm 1.6mm; }
    .history td { font-size: 8.5pt; line-height: 1.2; border: 0.5pt solid #555; padding: 0.6mm 1.6mm 0.8mm; vertical-align: top; }
    .history tr { page-break-inside: avoid; }
    .history td.date { white-space: nowrap; }
    .history td.empty { text-align: center; font-style: italic; color: #444; padding: 1.5mm; }
    .more { font-size: 8.5pt; font-style: italic; margin: 1mm 0 0; }

    /* Certification */
    .closing { page-break-inside: avoid; margin: 4.5mm 0 0; }
    .cert { font-size: 9.5pt; line-height: 1.3; }

    /* Footer on every page, growing upward from 7 mm above the paper edge
       (page numbers are drawn by the controller on its first line) */
    .footer { position: fixed; left: 0; right: 0; bottom: -12mm; border-top: 0.5pt solid #777; padding-top: 1.2mm; font-size: 7.5pt; line-height: 1.25; color: #333; }
    .footer table { width: 100%; }
    .footer td { padding: 0; vertical-align: top; }
</style>
</head>
<body>

<div class="footer" data-block="footer">
    <table>
        <tr>
            <td>{{ $docTitle }} &nbsp;&middot;&nbsp; Record No. {{ $patient->patient_number }}@if ($preparer) &nbsp;&middot;&nbsp; Prepared by {{ $preparer }}@endif</td>
            <td style="width:24mm;">{{-- "Page 1 of 2" --}}</td>
        </tr>
        @if ($footer !== '')
            <tr><td colspan="2">{{ $footer }}</td></tr>
        @endif
    </table>
</div>

{{-- Letterhead: the banner from Admin > Settings > Printing, else the school logo and names --}}
@include('reports.pdf._letterhead', ['document' => 'health'])
<div class="letterhead-rule"></div>

<div class="doc-title">{{ $docTitle }}</div>
<table class="doc-meta">
    <tr>
        <td style="width:38%;"><span class="k">Record No.</span> <strong>{{ $patient->patient_number }}</strong></td>
        <td style="width:24%; text-align:center;"><span class="mark">Confidential</span></td>
        <td style="width:38%; text-align:right;"><span class="k">Date issued</span> <strong>{{ DisplayFormat::date(now()) }}</strong></td>
    </tr>
</table>

{{-- I. Identification --}}
<div class="section">
    <div class="section-title">I. Identification</div>
    <table class="grid first">
        <tr>
            <td style="width:30%;"><div class="lbl">Last name</div><div class="val">{!! $box($patient->last_name) !!}</div></td>
            <td style="width:30%;"><div class="lbl">First name</div><div class="val">{!! $box($patient->first_name) !!}</div></td>
            <td style="width:28%;"><div class="lbl">Middle name</div><div class="val">{!! $box($patient->middle_name) !!}</div></td>
            <td style="width:12%;"><div class="lbl">Suffix</div><div class="val">{!! $box($patient->suffix) !!}</div></td>
        </tr>
    </table>
    <table class="grid">
        <tr>
            <td style="width:22%;"><div class="lbl">{{ $isStudent ? 'Student ID' : 'ID No.' }}</div><div class="val">{!! $box($patient->student_id) !!}</div></td>
            <td style="width:14%;"><div class="lbl">Sex</div><div class="val">{!! $box($patient->sex ? $patient->sex_label : '') !!}</div></td>
            <td style="width:24%;"><div class="lbl">Date of birth</div><div class="val">{!! $box(DisplayFormat::date($patient->birthdate)) !!}</div></td>
            <td style="width:10%;"><div class="lbl">Age</div><div class="val">{!! $box($patient->age) !!}</div></td>
            <td style="width:30%;"><div class="lbl">Category</div><div class="val">{!! $box($category !== '' ? $patient->category_label : '') !!}</div></td>
        </tr>
    </table>
    @if ($isStudent)
        <table class="grid">
            <tr>
                <td style="width:46%;"><div class="lbl">Course, program or strand</div><div class="val">{!! $box($patient->program_strand) !!}</div></td>
                <td style="width:27%;"><div class="lbl">Year or grade level</div><div class="val">{!! $box($patient->year_level) !!}</div></td>
                <td style="width:27%;"><div class="lbl">Section</div><div class="val">{!! $box($patient->section) !!}</div></td>
            </tr>
        </table>
    @endif
    <table class="grid">
        <tr>
            <td style="width:52%;"><div class="lbl">Home address</div><div class="val">{!! $box($patient->address) !!}</div></td>
            <td style="width:20%;"><div class="lbl">Contact no.</div><div class="val">{!! $box($patient->contact_number) !!}</div></td>
            <td style="width:28%;"><div class="lbl">Email</div><div class="val">{!! $box($patient->email) !!}</div></td>
        </tr>
    </table>
    <table class="grid">
        <tr>
            @if ($showGuardian)
                <td style="width:30%;"><div class="lbl">Parent or guardian</div><div class="val">{!! $box($guardian) !!}</div></td>
                <td style="width:18%;"><div class="lbl">Contact no.</div><div class="val">{!! $box($patient->guardian_contact) !!}</div></td>
                <td style="width:32%;"><div class="lbl">In case of emergency, notify</div><div class="val">{!! $box($patient->emergency_contact_name) !!}</div></td>
                <td style="width:20%;"><div class="lbl">Contact no.</div><div class="val">{!! $box($patient->emergency_contact_number) !!}</div></td>
            @else
                <td style="width:52%;"><div class="lbl">In case of emergency, notify</div><div class="val">{!! $box($patient->emergency_contact_name) !!}</div></td>
                <td style="width:48%;"><div class="lbl">Contact no.</div><div class="val">{!! $box($patient->emergency_contact_number) !!}</div></td>
            @endif
        </tr>
    </table>
</div>

{{-- II. Medical profile --}}
<div class="section">
    <div class="section-title">II. Medical Profile</div>
    <table class="grid first">
        <tr>
            <td style="width:14%;"><div class="lbl">Blood type</div><div class="val">{!! $box($patient->blood_type) !!}</div></td>
            <td style="width:86%;">
                <div class="lbl"><strong>Allergies</strong></div>
                @if ($blank($patient->allergies))
                    <div class="val none">None recorded</div>
                @else
                    <div class="val strong">{{ $clean($patient->allergies) }}</div>
                @endif
            </td>
        </tr>
    </table>
    <table class="grid">
        <tr>
            <td style="width:50%;">
                <div class="lbl">Existing medical conditions</div>
                @if ($blank($patient->medical_conditions))<div class="val none">None recorded</div>@else<div class="val">{{ $clean($patient->medical_conditions) }}</div>@endif
            </td>
            <td style="width:50%;">
                <div class="lbl">Maintenance medicines</div>
                @if ($blank($patient->current_medications))<div class="val none">None recorded</div>@else<div class="val">{{ $clean($patient->current_medications) }}</div>@endif
            </td>
        </tr>
    </table>
    <table class="grid">
        <tr>
            <td style="width:20%;"><div class="lbl">Latest vital signs</div><div class="val">{!! $box($latest ? DisplayFormat::date($latest['date']) : '') !!}</div></td>
            <td style="width:16%;"><div class="lbl">Temperature</div><div class="val">{!! $box($latest ? $unit($latest['temperature'], ' °C') : '') !!}</div></td>
            <td style="width:18%;"><div class="lbl">Blood pressure</div><div class="val">{!! $box($latest ? $unit($latest['bp'], ' mmHg') : '') !!}</div></td>
            <td style="width:14%;"><div class="lbl">Pulse rate</div><div class="val">{!! $box($latest ? $unit($latest['pulse'], '/min') : '') !!}</div></td>
            <td style="width:16%;"><div class="lbl">Weight</div><div class="val">{!! $box($latest ? $unit($latest['weight'], ' kg') : '') !!}</div></td>
            <td style="width:16%;"><div class="lbl">Height</div><div class="val">{!! $box($latest ? $unit($latest['height'], ' cm') : '') !!}</div></td>
        </tr>
    </table>
    @if (! $blank($patient->pediatrician_name) || ! $blank($patient->pediatrician_contact))
        <table class="grid">
            <tr>
                <td style="width:52%;"><div class="lbl">Attending physician or pediatrician</div><div class="val">{!! $box($patient->pediatrician_name) !!}</div></td>
                <td style="width:48%;"><div class="lbl">Contact no.</div><div class="val">{!! $box($patient->pediatrician_contact) !!}</div></td>
            </tr>
        </table>
    @endif
</div>

{{-- III. Clinic visit history (both header rows repeat on the next page) --}}
<table class="history">
    <thead>
        <tr><th colspan="{{ $columns }}" class="head"><div class="section-title" style="margin:0;">III. Clinic Visit History</div></th></tr>
        <tr>
            <th class="col" style="width:14%;">Date</th>
            @if ($showType)<th class="col" style="width:12%;">Type</th>@endif
            <th class="col" style="width:{{ $showType ? 19 : 22 }}%;">Complaint</th>
            <th class="col">Assessment and action taken</th>
            <th class="col" style="width:15%;">Attended by</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $i => $row)
            <tr data-row="{{ $i }}" @if ($keep > 0 && $i === count($rows) - $keep) style="page-break-before: always;" @endif>
                <td class="date">{{ DisplayFormat::date($row['date']) }}</td>
                @if ($showType)<td>{{ $row['type'] }}</td>@endif
                <td>{{ Str::limit($row['complaint'], 90) }}</td>
                <td>{{ Str::limit($row['action'], 200) }}</td>
                <td>{{ Str::limit($row['by'], 40) }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $columns }}" class="empty">{{ $entries === 0 ? 'No clinic visits on record.' : 'Visits on file are counted below.' }}</td></tr>
        @endforelse
    </tbody>
</table>

{{-- IV. Certification and signatures (kept together, never split) --}}
<div class="closing" data-block="closing" data-earlier="{{ $earlier }}">
    @if ($earlier > 0)
        <p class="more" style="margin:0 0 3.5mm;">and {{ $earlier }} earlier {{ Str::plural('visit', $earlier) }} on file.</p>
    @endif
    @if ($signers)
        <div class="section-title">IV. Certification</div>
        <p class="cert">This is a true copy of the health record kept by {{ $keeper }}.</p>
        @include('reports.pdf._signatures', ['document' => 'health'])
    @else
        {{-- No signatory set in Admin > Settings > Printing: a line to sign by hand --}}
        <table style="width:100%;">
            <tr>
                <td style="width:54%; padding:0 6mm 0 0; vertical-align:top;">
                    <div class="section-title">IV. Certification</div>
                    <p class="cert">This is a true copy of the health record kept by {{ $keeper }}.</p>
                </td>
                <td style="width:46%; padding:0; vertical-align:bottom; text-align:center;">
                    <div style="height:{{ PrintBranding::BLANK_SIGNATURE_MM }}mm;"></div>
                    <div style="border-top:0.75pt solid #000; padding-top:0.8mm; font-size:8pt;">Signature over printed name</div>
                    <div style="font-size:7.5pt; color:#444;">School nurse or physician</div>
                </td>
            </tr>
        </table>
    @endif
</div>

</body>
</html>
