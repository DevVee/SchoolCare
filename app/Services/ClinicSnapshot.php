<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Medicine;
use App\Models\PatientIntakeSubmission;
use App\Models\PatientLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Today's clinic numbers for the AI assistant and the dashboard brief.
 * Counts and medicine names only: never patient names, IDs, contact details
 * or diagnoses. Shared by every user and cached briefly; forUser() keeps
 * only the parts a user may see (the same permissions as the dashboard).
 */
class ClinicSnapshot
{
    private const TTL  = 60;   // Seconds the numbers are cached
    private const ROWS = 10;   // Medicines listed per stock alert

    /** Area => permission needed to see it. */
    public const AREAS = [
        'visits'        => 'view-patient-logs',
        'appointments'  => 'view-appointments',
        'consultations' => 'view-consultations',
        'medicines'     => 'view-medicines',
        'intake'        => 'review-intake',
    ];

    public function __construct(private readonly ReportService $reports) {}

    /** Areas the user may see, in AREAS order. */
    public function areasFor(?User $user): array
    {
        return array_keys(array_filter(self::AREAS, fn (string $ability) => (bool) $user?->can($ability)));
    }

    /** The snapshot limited to the areas the user may see: [area => facts]. */
    public function forUser(?User $user): array
    {
        return array_intersect_key($this->all(), array_flip($this->areasFor($user)));
    }

    /** Every area, cached for TTL seconds. Plain arrays only (safe for any cache store). */
    public function all(): array
    {
        return Cache::remember('clinic_snapshot:'.today()->toDateString(), self::TTL, fn () => [
            'visits'        => $this->visits(),
            'appointments'  => $this->appointments(),
            'consultations' => ['today' => Consultation::whereDate('visit_date', today())->count()],
            'medicines'     => $this->medicines(),
            'intake'        => ['pending' => PatientIntakeSubmission::pending()->count()],
        ]);
    }

    private function visits(): array
    {
        // Same weekday over the last 4 weeks, bucketed in PHP (portable across DBs).
        $days = collect(range(1, 4))->map(fn ($w) => today()->subWeeks($w)->toDateString());
        $past = PatientLog::whereDate('log_date', '>=', $days->last())
            ->whereDate('log_date', '<', today()->toDateString())
            ->pluck('log_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->filter(fn ($d) => $days->contains($d))
            ->count();

        // Top reason this month, only when it is one of the preset reasons
        // (free text "other" reasons could hold anything).
        $presets = array_map('mb_strtolower', PatientLog::reasonOptions());
        $top = $this->reports->topReasons(now()->startOfMonth()->toDateString(), today()->toDateString(), 5)
            ->first(fn ($r) => in_array(mb_strtolower($r->reason), $presets, true));

        return [
            'today'           => PatientLog::today()->count(),
            'in_clinic'       => PatientLog::inClinic()->count(),
            'weekday'         => today()->format('l'),
            'weekday_average' => round($past / 4, 1),
            'top_reason'      => $top ? ['reason' => $top->reason, 'total' => (int) $top->total] : null,
        ];
    }

    private function appointments(): array
    {
        // Online requests without a linked patient are actionable; archived
        // patients are not (same rule as the menu badge).
        $pending = Appointment::where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('patient_id')
                ->orWhereHas('patient', fn ($p) => $p->whereNull('patients.deleted_at')));

        $oldest = (clone $pending)->min('created_at');

        return [
            'today'               => Appointment::today()->selectRaw('status, COUNT(*) as total')
                                                ->groupBy('status')->pluck('total', 'status')
                                                ->map(fn ($n) => (int) $n)->all(),
            'pending'             => $pending->count(),
            'oldest_pending_days' => $oldest ? (int) Carbon::parse($oldest)->startOfDay()->diffInDays(today()) : null,
        ];
    }

    private function medicines(): array
    {
        $row = fn (Medicine $m) => [
            'name'    => $m->generic_name && $m->generic_name !== $m->name ? "{$m->name} ({$m->generic_name})" : $m->name,
            'short'   => $m->name,
            'qty'     => (int) $m->quantity,
            'unit'    => (string) $m->unit,
            'reorder' => (int) $m->low_stock_threshold,
            'expires' => $m->expiration_date?->format('M j, Y'),
        ];

        return [
            'low_stock_total'  => Medicine::active()->lowStock()->count(),
            'out_of_stock'     => Medicine::active()->where('quantity', '<=', 0)->count(),
            'low_stock'        => Medicine::active()->lowStock()->orderBy('quantity')->orderBy('name')
                                          ->limit(self::ROWS)->get()->map($row)->all(),
            'expiring_total'   => Medicine::active()->expiringSoon()->count(),
            'expiring'         => Medicine::active()->expiringSoon()->orderBy('expiration_date')->orderBy('name')
                                          ->limit(self::ROWS)->get()->map($row)->all(),
            'expired_in_stock' => Medicine::active()->expired()->where('quantity', '>', 0)->count(),
            'expiry_days'      => Medicine::expiryWarningDays(),
        ];
    }
}
