<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\AuditLogService;
use App\Services\Patients\HealthReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Patient Health Report Card (SSCMS patients/patient_health_report.php):
 * printable page and PDF download.
 */
class PatientHealthReportController extends Controller
{
    public function __construct(private readonly HealthReportService $reports) {}

    public function show(Patient $patient)
    {
        $this->authorize('view-patients');

        return view('patients.health-report', $this->reports->build($patient));
    }

    public function pdf(Patient $patient)
    {
        $this->authorize('view-patients');

        $data = $this->reports->build($patient);

        AuditLogService::log(
            action: 'exported',
            module: 'patients',
            description: "Downloaded the health report card PDF of {$patient->full_name} ({$patient->patient_number})",
        );

        $name = 'health-report-'.Str::slug($patient->last_name.'-'.$patient->first_name).'-'.now()->format('Ymd').'.pdf';

        return Pdf::loadView('patients.pdf.health-report', $data)
            ->setPaper('a4', 'portrait')
            ->download($name);
    }
}
