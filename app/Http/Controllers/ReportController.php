<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\PatientLog;
use App\Services\AuditLogService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service) {}

    /* ------------------------------------------------------------------ */
    /*  MENU                                                                */
    /* ------------------------------------------------------------------ */
    public function index()
    {
        $this->authorize('view-reports');

        $stats = [
            'visits_today'  => PatientLog::today()->count(),
            'visits_month'  => PatientLog::whereMonth('log_date', now()->month)
                                         ->whereYear('log_date', now()->year)->count(),
            'visits_year'   => PatientLog::whereYear('log_date', now()->year)->count(),
        ];

        return view('reports.index', compact('stats'));
    }

    /* ------------------------------------------------------------------ */
    /*  DAILY                                                               */
    /* ------------------------------------------------------------------ */
    public function daily(Request $request)
    {
        $this->authorize('view-reports');
        $request->validate(['date' => ['nullable', 'date']]);

        $date = $request->get('date') ?: today()->toDateString();
        $data = $this->service->dailyReport($date);
        return view('reports.daily', $data);
    }

    /* ------------------------------------------------------------------ */
    /*  MONTHLY                                                             */
    /* ------------------------------------------------------------------ */
    public function monthly(Request $request)
    {
        $this->authorize('view-reports');
        $request->validate([
            'year'  => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $year  = (int) ($request->get('year')  ?: now()->year);
        $month = (int) ($request->get('month') ?: now()->month);
        $data  = $this->service->monthlyReport($year, $month);
        return view('reports.monthly', $data);
    }

    /* ------------------------------------------------------------------ */
    /*  ANNUAL                                                              */
    /* ------------------------------------------------------------------ */
    public function annual(Request $request)
    {
        $this->authorize('view-reports');
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);

        $year = (int) ($request->get('year') ?: now()->year);
        $data = $this->service->annualReport($year);
        return view('reports.annual', $data);
    }

    /* ------------------------------------------------------------------ */
    /*  MEDICINE USAGE                                                      */
    /* ------------------------------------------------------------------ */
    public function medicineUsage(Request $request)
    {
        $this->authorize('view-reports');
        [$from, $to] = $this->range($request);
        $data = $this->service->medicineUsageReport($from, $to);
        return view('reports.medicine-usage', $data);
    }

    /* ------------------------------------------------------------------ */
    /*  INVENTORY SNAPSHOT                                                  */
    /* ------------------------------------------------------------------ */
    public function inventory()
    {
        $this->authorize('view-reports');
        $data = $this->service->inventorySnapshot();
        return view('reports.inventory', $data);
    }

    /* ------------------------------------------------------------------ */
    /*  APPOINTMENTS                                                        */
    /* ------------------------------------------------------------------ */
    public function appointments(Request $request)
    {
        $this->authorize('view-reports');
        [$from, $to] = $this->range($request);
        $data = $this->service->appointmentsReport($from, $to);
        return view('reports.appointments', $data);
    }

    /* ------------------------------------------------------------------ */
    /*  EXPORT — PDF or CSV                                                 */
    /* ------------------------------------------------------------------ */
    public function export(Request $request, string $type)
    {
        $this->authorize('export-reports');

        // MED-5 FIX: Validate all query parameters to prevent unexpected behavior
        // from crafted inputs (e.g. out-of-range years, future-only date ranges).
        $request->validate([
            'format' => ['nullable', 'in:pdf,csv'],
            'date'   => ['nullable', 'date'],
            'year'   => ['nullable', 'integer', 'between:2000,2100'],
            'month'  => ['nullable', 'integer', 'between:1,12'],
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $format = $request->get('format', 'pdf');

        $response = match ($type) {
            'daily'          => $this->exportDaily($request, $format),
            'monthly'        => $this->exportMonthly($request, $format),
            'annual'         => $this->exportAnnual($request, $format),
            'medicine-usage' => $this->exportMedicineUsage($request, $format),
            'inventory'      => $this->exportInventory($format),
            'appointments'   => $this->exportAppointments($request, $format),
            default          => null,
        };

        if ($response === null) {
            return back()->with('error', 'Unknown report type.');
        }

        // Exports contain PHI — record who exported what.
        $params = collect($request->only(['date', 'year', 'month', 'from', 'to']))->filter()->map(
            fn ($v, $k) => "{$k}={$v}"
        )->implode(', ');
        AuditLogService::log(
            action: 'exported',
            module: 'reports',
            description: "Exported {$type} report as " . strtoupper($format) . ($params ? " ({$params})" : ''),
            newValues: ['type' => $type, 'format' => $format] + $request->only(['date', 'year', 'month', 'from', 'to']),
        );

        return $response;
    }

    /* ── Private helpers ─────────────────────────────────────────────── */

    /** Validated, ordered [from, to] date range (defaults: month-to-date). */
    private function range(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ]);

        $from = $request->get('from') ?: now()->startOfMonth()->toDateString();
        $to   = $request->get('to')   ?: now()->toDateString();

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    private function patientName($patient): string
    {
        if (! $patient) {
            return '';
        }

        return $patient->full_name . ($patient->trashed() ? ' (archived)' : '');
    }

    private function exportDaily(Request $request, string $format)
    {
        $date = $request->get('date') ?: today()->toDateString();
        $data = $this->service->dailyReport($date);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf.daily', $data)->setPaper('a4', 'portrait');
            return $pdf->download("daily-report-{$date}.pdf");
        }

        $rows   = [];
        $rows[] = ["Daily Report: {$date}"];
        $rows[] = ['Clinic visits', $data['visits']->count(), 'Consultations', $data['consultations']->count(),
                   'Appointments', $data['appointments']->count(), 'Dispensed', $data['dispensed']->count()];
        $rows[] = [];
        $rows[] = ['CLINIC VISITS'];
        $rows[] = ['Time In', 'Time Out', 'Patient', 'Patient No.', 'Category', 'Chief Complaint', 'Treatment', 'Disposition', 'Logged By'];
        foreach ($data['visits'] as $v) {
            $rows[] = [
                $v->time_in ? \Carbon\Carbon::parse($v->time_in)->format('h:i A') : '',
                $v->time_out ? \Carbon\Carbon::parse($v->time_out)->format('h:i A') : '',
                $this->patientName($v->patient),
                $v->patient?->patient_number ?? '',
                $v->patient?->category_label ?? '',
                $v->complaint_summary,
                $v->treatment ?? '',
                $v->disposition_label,
                $v->loggedBy?->name ?? 'Deleted user',
            ];
        }
        $rows[] = [];
        $rows[] = ['CONSULTATIONS'];
        $rows[] = ['Time', 'Patient', 'Chief Complaint', 'Diagnosis', 'Nurse'];
        foreach ($data['consultations'] as $c) {
            $rows[] = [
                $c->visit_time ? \Carbon\Carbon::parse($c->visit_time)->format('h:i A') : '',
                $this->patientName($c->patient),
                $c->chief_complaint ?? '',
                $c->diagnosis ?? '',
                $c->nurse?->name ?? 'Deleted user',
            ];
        }
        $rows[] = [];
        $rows[] = ['MEDICINES DISPENSED'];
        $rows[] = ['Time', 'Patient', 'Medicine', 'Quantity', 'Dispensed By'];
        foreach ($data['dispensed'] as $d) {
            $rows[] = [
                $d->dispensed_at?->format('h:i A') ?? '',
                $this->patientName($d->patient),
                $d->medicine?->name ?? '',
                $d->quantity,
                $d->dispensedBy?->name ?? 'Deleted user',
            ];
        }

        return $this->csvDownload("daily-report-{$date}.csv", $rows);
    }

    private function exportMonthly(Request $request, string $format)
    {
        $year  = (int) ($request->get('year')  ?: now()->year);
        $month = (int) ($request->get('month') ?: now()->month);
        $data  = $this->service->monthlyReport($year, $month);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf.monthly', $data)->setPaper('a4', 'portrait');
            return $pdf->download("monthly-report-{$year}-{$month}.pdf");
        }

        $days   = \Carbon\Carbon::create($year, $month, 1)->daysInMonth;
        $rows   = [];
        $rows[] = ['Monthly Report: ' . \Carbon\Carbon::create($year, $month, 1)->format('F Y')];
        $rows[] = ['Clinic visits', $data['totalVisits'], 'Patients seen', $data['totalVisitPatients'],
                   'Consultations', $data['totalConsultations'], 'Appointments', $data['totalAppointments'],
                   'Units dispensed', $data['totalDispensed']];
        $rows[] = [];
        $rows[] = ['VISITS PER DAY'];
        $rows[] = ['Day', 'Clinic Visits', 'Consultations'];
        for ($d = 1; $d <= $days; $d++) {
            $rows[] = [$d, $data['visitsByDay'][$d] ?? 0, $data['consultationsByDay'][$d] ?? 0];
        }
        $rows[] = [];
        $rows[] = ['VISITS BY PATIENT CATEGORY'];
        $rows[] = ['Category', 'Visits', 'Patients'];
        foreach ($data['byCategory'] as $c) {
            $rows[] = [$c->label, $c->total, $c->patients];
        }
        $rows[] = [];
        $rows[] = ['TOP REASONS FOR VISIT'];
        $rows[] = ['Reason / Chief Complaint', 'Visits'];
        foreach ($data['topReasons'] as $r) {
            $rows[] = [$r->reason, $r->total];
        }
        $rows[] = [];
        $rows[] = ['VISITS BY SEVERITY'];
        $rows[] = ['Severity', 'Visits'];
        foreach ($data['bySeverity'] ?? [] as $s) {
            $rows[] = [$s->label, $s->total];
        }
        $rows[] = [];
        $rows[] = ['TOP 10 PATIENTS BY VISITS'];
        $rows[] = ['Patient', 'Patient No.', 'Category', 'Visits'];
        foreach ($data['topPatients'] as $p) {
            $rows[] = [$this->patientName($p->patient), $p->patient?->patient_number ?? '', $p->patient?->category_label ?? '', $p->total];
        }
        $rows[] = [];
        $rows[] = ['MOST-USED MEDICINES'];
        $rows[] = ['Medicine', 'Category', 'Times Dispensed', 'Total Qty'];
        foreach ($data['topMedicines'] as $u) {
            $rows[] = [$u->medicine?->name ?? '', $u->medicine?->category?->name ?? '', $u->times_dispensed, $u->total_dispensed];
        }
        $rows[] = [];
        $rows[] = ['APPOINTMENT STATUS'];
        $rows[] = ['Status', 'Count'];
        foreach ($data['appointmentStatus'] as $s) {
            $rows[] = [$s->label, $s->total];
        }
        $rows[] = [];
        $rows[] = ['STOCK ALERTS (as of ' . now()->format('Y-m-d') . ')'];
        $rows[] = ['Alert', 'Medicine', 'Quantity', 'Threshold', 'Expiry Date'];
        foreach ($data['stockAlerts']['lowStock'] as $m) {
            $rows[] = ['Low stock', $m->name, $m->quantity, $m->low_stock_threshold, $m->expiration_date?->format('Y-m-d') ?? ''];
        }
        foreach ($data['stockAlerts']['expiring'] as $m) {
            $rows[] = ['Expiring soon', $m->name, $m->quantity, $m->low_stock_threshold, $m->expiration_date?->format('Y-m-d') ?? ''];
        }
        foreach ($data['stockAlerts']['expired'] as $m) {
            $rows[] = ['Expired (in stock)', $m->name, $m->quantity, $m->low_stock_threshold, $m->expiration_date?->format('Y-m-d') ?? ''];
        }

        return $this->csvDownload("monthly-report-{$year}-{$month}.csv", $rows);
    }

    private function exportAnnual(Request $request, string $format)
    {
        $year = (int) ($request->get('year') ?: now()->year);
        $data = $this->service->annualReport($year);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf.annual', $data)->setPaper('a4', 'landscape');
            return $pdf->download("annual-report-{$year}.pdf");
        }

        $rows[] = ['Month', 'Clinic Visits', 'Consultations', 'Appointments'];
        foreach ($data['monthlyData'] as $row) {
            $rows[] = [$row['month'], $row['visits'], $row['consultations'], $row['appointments']];
        }
        $rows[] = ['Total', $data['totalVisits'], $data['totalConsultations'], $data['totalAppointments']];
        $rows[] = [];
        $rows[] = ['VISITS BY PATIENT CATEGORY'];
        $rows[] = ['Category', 'Visits', 'Patients'];
        foreach ($data['byCategory'] as $c) {
            $rows[] = [$c->label, $c->total, $c->patients];
        }
        $rows[] = [];
        $rows[] = ['TOP REASONS FOR VISIT'];
        foreach ($data['topReasons'] as $r) {
            $rows[] = [$r->reason, $r->total];
        }

        return $this->csvDownload("annual-report-{$year}.csv", $rows);
    }

    private function exportMedicineUsage(Request $request, string $format)
    {
        $from = $request->get('from') ?: now()->startOfMonth()->toDateString();
        $to   = $request->get('to')   ?: now()->toDateString();
        $data = $this->service->medicineUsageReport($from, $to);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf.medicine-usage', $data)->setPaper('a4', 'portrait');
            return $pdf->download("medicine-usage-{$from}-{$to}.pdf");
        }

        $rows[] = ['Medicine', 'Category', 'Times Dispensed', 'Total Qty'];
        foreach ($data['usage'] as $u) {
            $rows[] = [
                $u->medicine->name             ?? '',
                $u->medicine->category->name   ?? '',
                $u->times_dispensed,
                $u->total_dispensed,
            ];
        }
        return $this->csvDownload("medicine-usage-{$from}-{$to}.csv", $rows);
    }

    private function exportInventory(string $format)
    {
        $data = $this->service->inventorySnapshot();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf.inventory', $data)->setPaper('a4', 'portrait');
            return $pdf->download('inventory-snapshot-' . now()->format('Y-m-d') . '.pdf');
        }

        $rows[] = ['Medicine', 'Category', 'Quantity', 'Unit', 'Threshold', 'Expiry Date', 'Status', 'Expiry Status'];
        foreach ($data['medicines'] as $m) {
            $status = $m->quantity === 0
                ? 'Out of Stock'
                : ($m->is_low_stock ? 'Low Stock' : 'In Stock');
            $expiry = $m->is_expired ? 'Expired' : ($m->is_expiring_soon ? 'Expiring Soon' : '');
            $rows[] = [
                $m->name,
                $m->category->name               ?? '',
                $m->quantity,
                $m->unit,
                $m->low_stock_threshold,
                $m->expiration_date ? $m->expiration_date->format('Y-m-d') : '',
                $status,
                $expiry,
            ];
        }
        return $this->csvDownload('inventory-snapshot-' . now()->format('Y-m-d') . '.csv', $rows);
    }

    private function exportAppointments(Request $request, string $format)
    {
        $from = $request->get('from') ?: now()->startOfMonth()->toDateString();
        $to   = $request->get('to')   ?: now()->toDateString();
        $data = $this->service->appointmentsReport($from, $to);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf.appointments', $data)->setPaper('a4', 'portrait');
            return $pdf->download("appointments-{$from}-{$to}.pdf");
        }

        $rows[] = ['Date', 'Time', 'Patient', 'Purpose', 'Status'];
        foreach ($data['appointments'] as $a) {
            $rows[] = [
                $a->appointment_date->format('Y-m-d'),
                $a->appointment_time ? \Carbon\Carbon::parse($a->appointment_time)->format('h:i A') : '',
                $this->patientName($a->patient),
                $a->purpose            ?? '',
                Appointment::statusLabels()[$a->status] ?? $a->status,
            ];
        }
        return $this->csvDownload("appointments-{$from}-{$to}.csv", $rows);
    }

    /* ------------------------------------------------------------------ */
    /*  CSV Helpers                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * HIGH-6 FIX: Sanitize a cell value to prevent CSV formula injection.
     * Excel/Google Sheets execute cells starting with =, +, -, @, tab, or CR
     * as formulas. Prefix with a single quote to force plain-text treatment.
     */
    private function sanitizeCsvCell(mixed $value): string
    {
        $str = (string) ($value ?? '');

        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
            return "'" . $str;
        }

        return $str;
    }

    /**
     * Stream a CSV response using fputcsv with formula-injection sanitization.
     */
    private function csvDownload(string $filename, array $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the file with correct encoding
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                // Sanitize every cell before writing
                fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row));
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
