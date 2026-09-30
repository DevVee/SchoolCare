<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientLog;
use App\Services\ReportService;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /** Days shown in the visits trend chart. */
    private const TREND_DAYS = 30;

    public function index(ReportService $reports)
    {
        $user = auth()->user();
        $can  = fn (string $ability) => (bool) $user?->can($ability);

        $canLogs     = $can('view-patient-logs');
        $canConsults = $can('view-consultations');
        $canAppts    = $can('view-appointments');
        $canMeds     = $can('view-medicines');

        // Numbers for the stat strip. Only what the user may see is queried.
        $stats = [
            'visits_today'         => $canLogs ? PatientLog::today()->count() : null,
            'visits_month'         => $canLogs ? PatientLog::whereYear('log_date', now()->year)
                                                          ->whereMonth('log_date', now()->month)
                                                          ->count() : null,
            'visits_week'          => $canLogs ? PatientLog::whereDate('log_date', '>=', today()->startOfWeek()->toDateString())->count() : null,
            'in_clinic'            => $canLogs ? PatientLog::inClinic()->count() : null,
            'appointments_today'   => $canAppts ? Appointment::today()->whereIn('status', ['pending', 'approved'])->count() : null,
            // Pending appointments of archived patients are not actionable.
            'appointments_pending' => $canAppts ? Appointment::where('status', 'pending')
                                                             ->whereHas('patient', fn ($q) => $q->whereNull('deleted_at'))
                                                             ->count() : null,
            'low_stock_medicines'  => $canMeds ? Medicine::active()->lowStock()->count() : null,
            'expiring_medicines'   => $canMeds ? Medicine::active()->expiringSoon()->count() : null, // window: Medicine::expiryWarningDays()
        ];

        // Active patients with a sex split (one grouped query).
        if ($can('view-patients')) {
            $bySex = Patient::active()->selectRaw('sex, COUNT(*) as total')->groupBy('sex')->pluck('total', 'sex');
            $stats['patients_active'] = (int) $bySex->sum();
            $stats['patients_male']   = (int) ($bySex['male'] ?? 0);
            $stats['patients_female'] = (int) ($bySex['female'] ?? 0);
        }

        $todayAppointments = $canAppts
            ? Appointment::with('patient')
                ->today()
                ->whereIn('status', ['pending', 'approved'])
                ->orderBy('appointment_time')
                ->limit(6)
                ->get()
            : collect();

        // Inventory alerts: out of stock / low stock first, then expiring soon (one row per medicine).
        $inventoryAlerts = collect();
        if ($canMeds) {
            $low = Medicine::with('category')->active()->lowStock()->orderBy('quantity')->limit(6)->get();
            $expiring = Medicine::with('category')->active()->expiringSoon()->orderBy('expiration_date')->limit(6)->get();
            $inventoryAlerts = $low->concat($expiring)->unique('id')->take(6)->values();
        }

        // Audit log activity is sensitive: only users with view-audit-logs see it.
        $recentActivity = $can('view-audit-logs')
            ? AuditLog::with('user')->latest()->limit(6)->get()
            : collect();

        // Visits trend: clinic visits (logbook) and consultations, per day over the last
        // TREND_DAYS days or per month over the last 12 months (?range=30d|12m). With no
        // range picked and a quiet 30 days, it shows the 12 months so the card is a graph
        // of real history, not an empty message (owner: "can we make this graphs").
        $trend = null;
        if ($canLogs || $canConsults) {
            $picked = in_array(request('range'), ['30d', '12m'], true) ? request('range') : null;
            $trend  = $this->trend($canLogs, $canConsults, $picked ?? '30d');
            if ($picked === null && $trend['total'] === 0) {
                $yearly = $this->trend($canLogs, $canConsults, '12m');
                $trend  = $yearly['total'] > 0 ? $yearly : $trend;
            }
        }

        // Top reasons for visit (horizontal bar): this month, or the last 12 months when
        // nothing was logged this month yet.
        $topReasons = collect();
        $reasonsPeriod = 'This month ('.now()->format('F').')';
        if ($canLogs) {
            $topReasons = $reports->topReasons(now()->startOfMonth()->toDateString(), today()->toDateString(), 6);
            if ($topReasons->isEmpty()) {
                $topReasons = $reports->topReasons(today()->subMonths(12)->toDateString(), today()->toDateString(), 6);
                $reasonsPeriod = 'Last 12 months (none logged this month yet)';
            }
        }

        return view('dashboard.index', compact(
            'stats', 'todayAppointments', 'inventoryAlerts', 'recentActivity', 'trend', 'topReasons', 'reasonsPeriod'
        ));
    }

    /**
     * Visits and consultations bucketed per day (30d) or per month (12m).
     * Two queries, bucketed in PHP (portable across DBs).
     *
     * @return array{categories: list<string>, series: list<array{name: string, data: list<int>}>, range: string, label: string, total: int}
     */
    private function trend(bool $canLogs, bool $canConsults, string $range): array
    {
        $monthly = $range === '12m';
        $start   = $monthly ? today()->startOfMonth()->subMonths(11) : today()->subDays(self::TREND_DAYS - 1);
        $keyOf   = fn ($d) => Carbon::parse($d)->format($monthly ? 'Y-m' : 'Y-m-d');
        $bucket  = fn ($dates) => collect($dates)->countBy($keyOf);

        $visitCounts   = $canLogs ? $bucket(PatientLog::whereDate('log_date', '>=', $start->toDateString())->pluck('log_date')) : collect();
        $consultCounts = $canConsults ? $bucket(Consultation::whereDate('visit_date', '>=', $start->toDateString())->pluck('visit_date')) : collect();

        $categories = $visits = $consults = [];
        $steps = $monthly ? 12 : self::TREND_DAYS;
        for ($i = 0; $i < $steps; $i++) {
            $point = $monthly ? $start->copy()->addMonths($i) : $start->copy()->addDays($i);
            $key = $point->format($monthly ? 'Y-m' : 'Y-m-d');
            $categories[] = $point->format($monthly ? 'M Y' : 'M j');
            $visits[]     = (int) ($visitCounts[$key] ?? 0);
            $consults[]   = (int) ($consultCounts[$key] ?? 0);
        }

        $series = [];
        if ($canLogs) {
            $series[] = ['name' => 'Clinic visits', 'data' => $visits];
        }
        if ($canConsults) {
            $series[] = ['name' => 'Consultations', 'data' => $consults];
        }

        return [
            'categories' => $categories,
            'series'     => $series,
            'range'      => $range,
            'label'      => $monthly ? 'Last 12 months, by month' : 'Last '.self::TREND_DAYS.' days',
            'total'      => array_sum($visits) + array_sum($consults),
        ];
    }
}
