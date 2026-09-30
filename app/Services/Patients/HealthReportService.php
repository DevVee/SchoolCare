<?php

namespace App\Services\Patients;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\DispensingRecord;
use App\Models\Patient;
use App\Models\PatientLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Patient Health Report Card (SSCMS patients/patient_health_report.php).
 * SSCMS read a health_records table that was never written; everything here
 * is computed live from the clinic log, consultations, dispensing and
 * appointments.
 */
class HealthReportService
{
    public function build(Patient $patient): array
    {
        $logs = PatientLog::query()
            ->where('patient_id', $patient->id)
            ->with('loggedBy')
            ->orderByDesc('log_date')
            ->orderByDesc('time_in')
            ->get();

        $consultations = Consultation::query()
            ->where('patient_id', $patient->id)
            ->with('nurse')
            ->orderByDesc('visit_date')
            ->limit(50)
            ->get();

        $dispensed = DispensingRecord::query()
            ->where('patient_id', $patient->id)
            ->with('medicine')
            ->orderByDesc('dispensed_at')
            ->get();

        $appointments = Appointment::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('appointment_date')
            ->limit(50)
            ->get();

        $reasons  = $this->reasonCounts($logs);
        $severity = $this->severityCounts($logs);
        $total    = $logs->count();
        $top      = $reasons ? array_key_first($reasons) : null;

        return [
            'patient'        => $patient,
            'logs'           => $logs,
            'consultations'  => $consultations,
            'dispensed'      => $dispensed,
            'appointments'   => $appointments,
            'medicineTotals' => $this->medicineTotals($dispensed),
            'summary'        => [
                'total_visits'      => $total,
                'visits_this_year'  => $logs->filter(fn ($l) => $l->log_date?->year === now()->year)->count(),
                'last_visit'        => $logs->first()?->log_date,
                'consultations'     => Consultation::where('patient_id', $patient->id)->count(),
                'medicines_units'   => (int) $dispensed->sum('quantity'),
                'appointments'      => Appointment::where('patient_id', $patient->id)->count(),
            ],
            'reasons'        => $reasons,
            'reasonSource'   => $this->hasStructuredReasons() ? 'reasons' : 'complaints',
            'severity'       => $severity,
            'topReason'      => $top,
            'observation'    => $this->observation($top, $reasons[$top] ?? 0, $total, $severity),
            'monthly'        => $this->monthly($logs, 6),
            'vitals'         => $this->vitals($logs),
        ];
    }

    // ─── Aggregates ──────────────────────────────────────────────────────────

    private function hasStructuredReasons(): bool
    {
        return Schema::hasColumn('patient_logs', 'reasons');
    }

    /** @return array<string,int> reason => visits (most frequent first) */
    public function reasonCounts(Collection $logs): array
    {
        $counts = [];
        $structured = $this->hasStructuredReasons();

        foreach ($logs as $log) {
            $items = [];

            if ($structured) {
                $raw = $log->getAttribute('reasons');
                $list = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
                foreach ($list as $reason) {
                    if (is_scalar($reason) && trim((string) $reason) !== '' && strcasecmp(trim((string) $reason), 'Other') !== 0) {
                        $items[] = trim((string) $reason);
                    }
                }
                $other = trim((string) $log->getAttribute('other_reason'));
                if ($other !== '') {
                    $items[] = $other;
                }
            }

            if ($items === []) {
                $complaint = trim((string) $log->chief_complaint);
                if ($complaint !== '') {
                    // "Headache, Fever" counts as two reasons.
                    foreach (preg_split('/\s*[,;\/]\s*|\s+and\s+/i', $complaint) as $part) {
                        $part = trim($part, " .\t\n\r");
                        if ($part !== '' && mb_strlen($part) <= 60) {
                            $items[] = mb_convert_case($part, MB_CASE_TITLE);
                        }
                    }
                }
            }

            foreach (array_unique($items) as $item) {
                $counts[$item] = ($counts[$item] ?? 0) + 1;
            }
        }

        arsort($counts);

        return $counts;
    }

    /** @return array<string,int> level => visits (in settings order; empty when not recorded) */
    public function severityCounts(Collection $logs): array
    {
        if (! Schema::hasColumn('patient_logs', 'severity')) {
            return [];
        }

        $levels = settings()->list('visit_severity_levels') ?: ['Mild', 'Moderate', 'Severe'];
        $counts = array_fill_keys($levels, 0);
        $any    = false;

        foreach ($logs as $log) {
            $value = trim((string) $log->getAttribute('severity'));
            if ($value === '') {
                continue;
            }
            $any = true;
            $match = collect($levels)->first(fn ($l) => strcasecmp($l, $value) === 0) ?? $value;
            $counts[$match] = ($counts[$match] ?? 0) + 1;
        }

        return $any ? $counts : [];
    }

    /** Visits per month for the last $months months (oldest first). */
    public function monthly(Collection $logs, int $months): array
    {
        $labels = [];
        $data   = [];
        $start  = now()->startOfMonth()->subMonths($months - 1);

        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $labels[] = $m->format('M Y');
            $data[]   = $logs->filter(fn ($l) => $l->log_date && $l->log_date->format('Y-m') === $m->format('Y-m'))->count();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Vital sign series from clinic log entries (oldest first, last 20 readings).
     *
     * @return array{labels: string[], temperature: array, pulse: array, weight: array, systolic: array, diastolic: array, rows: array}
     */
    public function vitals(Collection $logs): array
    {
        $rows = $logs
            ->filter(fn ($l) => is_array($l->vital_signs) && $l->vital_signs !== [])
            ->sortBy(fn ($l) => ($l->log_date?->format('Y-m-d') ?? '').' '.$l->time_in)
            ->values()
            ->slice(-20)
            ->values();

        $out = ['labels' => [], 'temperature' => [], 'pulse' => [], 'weight' => [], 'systolic' => [], 'diastolic' => [], 'rows' => []];

        foreach ($rows as $log) {
            $v = $log->vital_signs;
            $bp = (string) ($v['blood_pressure'] ?? '');
            [$sys, $dia] = preg_match('/^\s*(\d{2,3})\s*\/\s*(\d{2,3})/', $bp, $m) ? [(int) $m[1], (int) $m[2]] : [null, null];

            $out['labels'][]      = $log->log_date?->format('M d, Y') ?? '';
            $out['temperature'][] = isset($v['temperature']) && is_numeric($v['temperature']) ? (float) $v['temperature'] : null;
            $out['pulse'][]       = isset($v['pulse']) && is_numeric($v['pulse']) ? (int) $v['pulse'] : null;
            $out['weight'][]      = isset($v['weight']) && is_numeric($v['weight']) ? (float) $v['weight'] : null;
            $out['systolic'][]    = $sys;
            $out['diastolic'][]   = $dia;
            $out['rows'][] = [
                'date'        => $log->log_date,
                'temperature' => $v['temperature'] ?? null,
                'bp'          => $bp !== '' ? $bp : null,
                'pulse'       => $v['pulse'] ?? null,
                'weight'      => $v['weight'] ?? null,
                'height'      => $v['height'] ?? null,
            ];
        }

        return $out;
    }

    /** @return array<int, array{name:string, unit:?string, quantity:int, times:int}> */
    private function medicineTotals(Collection $dispensed): array
    {
        return $dispensed
            ->groupBy('medicine_id')
            ->map(fn ($group) => [
                'name'     => $group->first()->medicine?->name ?? 'Deleted medicine',
                'unit'     => $group->first()->medicine?->unit,
                'quantity' => (int) $group->sum('quantity'),
                'times'    => $group->count(),
            ])
            ->sortByDesc('quantity')
            ->values()
            ->all();
    }

    /** Plain observation line for the most frequent reason. */
    private function observation(?string $top, int $count, int $total, array $severity): ?string
    {
        if (! $top || $total === 0) {
            return null;
        }

        $pct  = (int) round($count / max(1, $total) * 100);
        $text = "{$top} is the most frequent reason for this patient's clinic visits ({$count} of {$total}, {$pct}%).";

        if ($count >= 3) {
            $text .= ' Repeated visits for the same reason may need a follow-up or a referral.';
        }

        $severe = 0;
        foreach ($severity as $level => $n) {
            if (strcasecmp($level, 'Severe') === 0) {
                $severe = $n;
            }
        }
        if ($severe > 0) {
            $text .= " {$severe} visit(s) were recorded as severe.";
        }

        return $text;
    }
}
