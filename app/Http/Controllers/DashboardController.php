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

        // Visits trend: clinic visits (logbook) and consultations per day over the
        // last TREND_DAYS days. Two queries, bucketed in PHP (portable across DBs).
        $trend = null;
        if ($canLogs || $canConsults) {
            $start  = today()->subDays(self::TREND_DAYS - 1);
            $bucket = fn ($dates) => collect($dates)->countBy(fn ($d) => Carbon::parse($d)->toDateString());
            $visitCounts   = $canLogs ? $bucket(PatientLog::whereDate('log_date', '>=', $start->toDateString())->pluck('log_date')) : collect();
            $consultCounts = $canConsults ? $bucket(Consultation::whereDate('visit_date', '>=', $start->toDateString())->pluck('visit_date')) : collect();

            $categories = $visits = $consults = [];
            for ($i = 0; $i < self::TREND_DAYS; $i++) {
                $day = $start->copy()->addDays($i);
                $key = $day->toDateString();
                $categories[] = $day->format('M j');
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
            $trend = ['categories' => $categories, 'series' => $series, 'days' => self::TREND_DAYS];
        }

        // Top reasons for visit this month (horizontal bar).
        $topReasons = $canLogs
            ? $reports->topReasons(now()->startOfMonth()->toDateString(), today()->toDateString(), 6)
            : collect();

        return view('dashboard.index', compact(
            'stats', 'todayAppointments', 'inventoryAlerts', 'recentActivity', 'trend', 'topReasons'
        ));
    }
}
