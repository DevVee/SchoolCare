<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\DispensingRecord;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Report data.
 *
 * Clinic visits (PatientLog, the clinic logbook) are the primary visit
 * metric, matching the dashboard and the legacy SSCMS "visits" reports;
 * consultations are reported alongside them.
 *
 * All date filtering uses whereDate()/inclusive day bounds so a date column
 * stored as "Y-m-d 00:00:00" (Eloquent's date cast) never drops the last day.
 */
class ReportService
{
    // --- Helpers -------------------------------------------------------------

    /** Inclusive [from, to] day range on a date/datetime column. */
    private function inRange(Builder $query, string $column, string $from, string $to): Builder
    {
        return $query->whereDate($column, '>=', Carbon::parse($from)->toDateString())
                     ->whereDate($column, '<=', Carbon::parse($to)->toDateString());
    }

    /** Count values of a date column per key (e.g. day-of-month or Y-m) in PHP (DB-portable). */
    private function bucket(Collection $dates, string $format): Collection
    {
        return $dates->countBy(fn ($d) => Carbon::parse($d)->format($format));
    }

    /** Visits (patient logs) grouped by the patient's category, archived patients included. */
    private function visitsByCategory(string $from, string $to): Collection
    {
        $labels = Patient::categoryLabels();

        return PatientLog::query()
            ->join('patients', 'patients.id', '=', 'patient_logs.patient_id')
            ->whereNull('patient_logs.deleted_at')
            ->whereDate('patient_logs.log_date', '>=', $from)
            ->whereDate('patient_logs.log_date', '<=', $to)
            ->selectRaw('patients.category as category, COUNT(*) as total, COUNT(DISTINCT patient_logs.patient_id) as patients')
            ->groupBy('patients.category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => (object) [
                'category' => $row->category,
                'label'    => $labels[$row->category] ?? ucfirst(str_replace('_', ' ', (string) $row->category)),
                'total'    => (int) $row->total,
                'patients' => (int) $row->patients,
            ]);
    }

    /**
     * Top reasons for visit (clinic logs). Uses the structured reasons plus
     * the "Other" text; older entries without structured reasons fall back to
     * the free-text chief complaint. Each reason counts once per visit;
     * case- and whitespace-insensitive. Returns [{reason, total}].
     */
    public function topReasons(string $from, string $to, int $limit = 10): Collection
    {
        $norm = fn ($c) => trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $c)));

        $counts = [];
        $this->inRange(PatientLog::query(), 'log_date', $from, $to)
            ->select(['id', 'reasons', 'other_reason', 'chief_complaint'])
            ->chunkById(1000, function ($logs) use (&$counts, $norm) {
                foreach ($logs as $log) {
                    $reasons = array_merge((array) ($log->reasons ?? []), filled($log->other_reason) ? [$log->other_reason] : []);
                    if ($reasons === []) {
                        $reasons = [$log->chief_complaint];
                    }
                    foreach (array_unique(array_filter(array_map($norm, $reasons))) as $r) {
                        $counts[$r] = ($counts[$r] ?? 0) + 1;
                    }
                }
            });

        return collect($counts)
            ->sortDesc()
            ->take($limit)
            ->map(fn ($total, $reason) => (object) ['reason' => mb_convert_case($reason, MB_CASE_TITLE), 'total' => $total])
            ->values();
    }

    /**
     * Visits per severity level (settings order first, then any other stored
     * value, then "Not recorded" for older entries). Returns [{severity, label, total}].
     */
    private function severityBreakdown(string $from, string $to): Collection
    {
        $counts = $this->inRange(PatientLog::query(), 'log_date', $from, $to)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $levels = collect(PatientLog::severities());
        $rows   = $levels->map(fn ($s) => (object) ['severity' => $s, 'label' => $s, 'total' => (int) ($counts[$s] ?? 0)]);

        foreach ($counts as $sev => $total) {
            if ($sev !== '' && $sev !== null && ! $levels->contains($sev)) {
                $rows->push((object) ['severity' => $sev, 'label' => $sev, 'total' => (int) $total]);
            }
        }

        $unrecorded = (int) $this->inRange(PatientLog::query(), 'log_date', $from, $to)->whereNull('severity')->count();
        if ($unrecorded > 0) {
            $rows->push((object) ['severity' => null, 'label' => 'Not recorded', 'total' => $unrecorded]);
        }

        return $rows->values();
    }

    /** Top N patients by number of clinic visits. Returns [{patient, total}]. */
    private function topPatients(string $from, string $to, int $limit = 10): Collection
    {
        $rows = $this->inRange(PatientLog::query(), 'log_date', $from, $to)
            ->selectRaw('patient_id, COUNT(*) as total')
            ->groupBy('patient_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $patients = Patient::withTrashed()->whereIn('id', $rows->pluck('patient_id'))->get()->keyBy('id');

        return $rows->map(fn ($r) => (object) [
            'patient' => $patients->get($r->patient_id),
            'total'   => (int) $r->total,
        ]);
    }

    /** Most-dispensed medicines. Returns DispensingRecord aggregates with ->medicine loaded. */
    private function topMedicines(string $from, string $to, ?int $limit = 10): Collection
    {
        return DispensingRecord::selectRaw('medicine_id, SUM(quantity) as total_dispensed, COUNT(*) as times_dispensed')
            ->with('medicine.category')
            ->whereDate('dispensed_at', '>=', $from)
            ->whereDate('dispensed_at', '<=', $to)
            ->groupBy('medicine_id')
            ->orderByDesc('total_dispensed')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();
    }

    /**
     * Medicines given during clinic visits (logbook), as opposed to the
     * separate dispensing module. Both are already included in topMedicines
     * because visit medicines are dispensing records linked to the visit.
     * Returns ['units' => int, 'visits' => int].
     */
    private function visitMedicines(string $from, string $to): array
    {
        $q = DispensingRecord::whereNotNull('patient_log_id')
            ->whereDate('dispensed_at', '>=', $from)
            ->whereDate('dispensed_at', '<=', $to);

        return [
            'units'  => (int) (clone $q)->sum('quantity'),
            'visits' => (int) (clone $q)->distinct()->count('patient_log_id'),
        ];
    }

    /** Appointment counts per status for appointments dated in the range (all statuses present). */
    private function appointmentStatus(string $from, string $to): Collection
    {
        $counts = $this->inRange(Appointment::query(), 'appointment_date', $from, $to)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(Appointment::statusLabels())
            ->map(fn ($label, $status) => (object) [
                'status' => $status,
                'label'  => $label,
                'total'  => (int) ($counts[$status] ?? 0),
            ])
            ->values();
    }

    /** Current stock alerts (active medicines): low stock, expiring soon, expired. */
    public function stockAlerts(): array
    {
        return [
            'lowStock' => Medicine::with('category')->active()->lowStock()->orderBy('quantity')->get(),
            'expiring' => Medicine::with('category')->active()->expiringSoon()->orderBy('expiration_date')->get(),
            'expired'  => Medicine::with('category')->active()->expired()->where('quantity', '>', 0)->orderBy('expiration_date')->get(),
            'expiryWarningDays' => Medicine::expiryWarningDays(),
        ];
    }

    // --- Daily ---------------------------------------------------------------

    public function dailyReport(string $date): array
    {
        $date = Carbon::parse($date)->toDateString();

        $visits = PatientLog::with('patient', 'loggedBy')
            ->whereDate('log_date', $date)
            ->orderBy('time_in')
            ->get();

        $consultations = Consultation::with('patient', 'nurse')
            ->whereDate('visit_date', $date)
            ->orderBy('visit_time')
            ->get();

        $appointments = Appointment::with('patient')
            ->whereDate('appointment_date', $date)
            ->orderBy('appointment_time')
            ->get();

        $dispensed = DispensingRecord::with('patient', 'medicine', 'dispensedBy')
            ->whereDate('dispensed_at', $date)
            ->orderBy('dispensed_at')
            ->get();

        $visitsByCategory    = $this->visitsByCategory($date, $date);
        $visitsByDisposition = $visits->countBy('disposition');

        return compact(
            'date', 'visits', 'consultations', 'appointments', 'dispensed',
            'visitsByCategory', 'visitsByDisposition'
        );
    }

    // --- Monthly -------------------------------------------------------------

    public function monthlyReport(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $from  = $start->toDateString();
        $to    = $start->copy()->endOfMonth()->toDateString();

        // Per-day series (keyed by day-of-month, 1..31), grouped in PHP for portability.
        $visitsByDay = $this->bucket(
            $this->inRange(PatientLog::query(), 'log_date', $from, $to)->pluck('log_date'), 'j'
        );
        $consultationsByDay = $this->bucket(
            $this->inRange(Consultation::query(), 'visit_date', $from, $to)->pluck('visit_date'), 'j'
        );

        $totalVisits        = (int) $visitsByDay->sum();
        $totalVisitPatients = $this->inRange(PatientLog::query(), 'log_date', $from, $to)->distinct()->count('patient_id');
        $totalConsultations = (int) $consultationsByDay->sum();
        $totalPatients      = $this->inRange(Consultation::query(), 'visit_date', $from, $to)->distinct()->count('patient_id');
        $totalAppointments  = $this->inRange(Appointment::query(), 'appointment_date', $from, $to)->count();
        $totalDispensed     = (int) DispensingRecord::whereDate('dispensed_at', '>=', $from)
                                  ->whereDate('dispensed_at', '<=', $to)->sum('quantity');

        // SSCMS monthly sections
        $byCategory        = $this->visitsByCategory($from, $to);
        $topReasons        = $this->topReasons($from, $to);
        $topPatients       = $this->topPatients($from, $to);
        $topMedicines      = $this->topMedicines($from, $to);
        $appointmentStatus = $this->appointmentStatus($from, $to);
        $stockAlerts       = $this->stockAlerts();
        $bySeverity        = $this->severityBreakdown($from, $to);
        $visitMedicines    = $this->visitMedicines($from, $to);

        return compact(
            'year', 'month', 'from', 'to',
            'visitsByDay', 'consultationsByDay',
            'totalVisits', 'totalVisitPatients', 'totalConsultations', 'totalPatients',
            'totalAppointments', 'totalDispensed',
            'byCategory', 'topReasons', 'topPatients', 'topMedicines',
            'appointmentStatus', 'stockAlerts', 'bySeverity', 'visitMedicines'
        );
    }

    // --- Annual --------------------------------------------------------------

    public function annualReport(int $year): array
    {
        $from = Carbon::create($year, 1, 1)->toDateString();
        $to   = Carbon::create($year, 12, 31)->toDateString();

        // Three queries total (instead of 24+), bucketed by month number.
        $visits        = $this->bucket($this->inRange(PatientLog::query(), 'log_date', $from, $to)->pluck('log_date'), 'n');
        $consultations = $this->bucket($this->inRange(Consultation::query(), 'visit_date', $from, $to)->pluck('visit_date'), 'n');
        $appointments  = $this->bucket($this->inRange(Appointment::query(), 'appointment_date', $from, $to)->pluck('appointment_date'), 'n');

        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[] = [
                'month'         => Carbon::create($year, $m, 1)->format('M'),
                'visits'        => (int) ($visits[$m] ?? 0),
                'consultations' => (int) ($consultations[$m] ?? 0),
                'appointments'  => (int) ($appointments[$m] ?? 0),
            ];
        }

        $totalVisits        = array_sum(array_column($monthlyData, 'visits'));
        $totalConsultations = array_sum(array_column($monthlyData, 'consultations'));
        $totalAppointments  = array_sum(array_column($monthlyData, 'appointments'));
        $totalVisitPatients = $this->inRange(PatientLog::query(), 'log_date', $from, $to)->distinct()->count('patient_id');
        $totalPatients      = $this->inRange(Consultation::query(), 'visit_date', $from, $to)->distinct()->count('patient_id');

        $byCategory   = $this->visitsByCategory($from, $to);
        $topReasons   = $this->topReasons($from, $to);
        $topMedicines = $this->topMedicines($from, $to);
        $bySeverity   = $this->severityBreakdown($from, $to);

        return compact(
            'year', 'monthlyData',
            'totalVisits', 'totalVisitPatients', 'totalConsultations', 'totalAppointments', 'totalPatients',
            'byCategory', 'topReasons', 'topMedicines', 'bySeverity'
        );
    }

    // --- Medicine Usage -------------------------------------------------------

    public function medicineUsageReport(string $from, string $to): array
    {
        $usage          = $this->topMedicines($from, $to, null);
        $totalDispensed = $usage->sum('total_dispensed');

        return compact('usage', 'totalDispensed', 'from', 'to');
    }

    // --- Inventory Snapshot ---------------------------------------------------

    public function inventorySnapshot(): array
    {
        $medicines  = Medicine::with('category')->active()->orderBy('name')->get();
        $lowStock   = $medicines->filter(fn ($m) => $m->is_low_stock && $m->quantity > 0)->count();
        $outOfStock = $medicines->filter(fn ($m) => $m->quantity === 0)->count();
        $expiring   = $medicines->filter(fn ($m) => $m->is_expiring_soon)->count();
        $expired    = $medicines->filter(fn ($m) => $m->is_expired)->count();

        return compact('medicines', 'lowStock', 'outOfStock', 'expiring', 'expired');
    }

    // --- Appointments Summary -------------------------------------------------

    public function appointmentsReport(string $from, string $to): array
    {
        // whereDate on both bounds: appointment_date is stored as
        // "Y-m-d 00:00:00", so a string BETWEEN dropped the last day.
        $appointments = $this->inRange(Appointment::with('patient'), 'appointment_date', $from, $to)
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get();

        $byStatus = $appointments->groupBy('status')->map->count();

        return compact('appointments', 'byStatus', 'from', 'to');
    }
}
