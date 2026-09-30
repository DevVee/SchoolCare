<?php

namespace App\View\Composers;

use App\Models\Appointment;
use App\Models\PatientIntakeSubmission;
use App\Models\PatientLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Resolves config/navigation.php for the signed-in user: permission and
 * feature filtering, active state, and neutral badge counts. The result is
 * memoised for the request, so rendering the sidebar more than once (or from
 * several views) runs the count queries only once.
 *
 * Exposes $navigation to layouts.partials.sidebar:
 *   [['label' => ?string, 'items' => [[key, label, url, icon, module, color, active, badge, badge_label]]]]
 */
class NavigationComposer
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $resolved = null;

    /** @var array<string, int|null> */
    private array $counts = [];

    public function __construct(private readonly Request $request) {}

    public function compose(View $view): void
    {
        $view->with('navigation', $this->navigation());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function navigation(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $user = $this->request->user();
        if (! $user) {
            return $this->resolved = [];
        }

        $groups = [];
        foreach ((array) config('navigation', []) as $group) {
            $items = [];
            foreach ((array) ($group['items'] ?? []) as $item) {
                $resolved = $this->resolveItem($item, $user);
                if ($resolved === null) {
                    continue;
                }
                $items[] = $resolved;
            }
            if ($items !== []) {
                $groups[] = ['label' => $group['label'] ?? null, 'items' => $items];
            }
        }

        return $this->resolved = $groups;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function resolveItem(array $item, Authenticatable $user): ?array
    {
        $route = $item['route'] ?? null;
        if (! $route || ! Route::has($route)) {
            return null;
        }
        if (! $this->allowed($item['can'] ?? null, $user)) {
            return null;
        }
        if (! $this->featureOn($item['feature'] ?? null)) {
            return null;
        }

        $label = (string) ($item['label'] ?? '');
        foreach ((array) ($item['label_settings'] ?? []) as $placeholder => $key) {
            $value = trim((string) $this->setting($key, ''));
            $label = str_replace(':'.$placeholder, $value !== '' ? $value : 'AI assistant', $label);
        }

        $active = $this->request->routeIs(...(array) ($item['active'] ?? [$route]));
        if ($active && ! empty($item['except']) && $this->request->routeIs(...(array) $item['except'])) {
            $active = false;
        }

        $badge = null;
        if (! empty($item['badge']) && $this->allowed($item['badge_can'] ?? null, $user)) {
            $badge = $this->count((string) $item['badge']);
        }

        return [
            'key'         => $route,
            'label'       => $label,
            'url'         => route($route, $item['params'] ?? []),
            'icon'        => $item['icon'] ?? 'circle',
            'module'      => $item['module'] ?? null,
            'active'      => $active,
            'badge'       => $badge,
            'badge_label' => $badge ? str_replace(':count', (string) $badge, (string) ($item['badge_label'] ?? ':count')) : null,
        ];
    }

    /**
     * @param  string|array<int, string>|null  $can
     */
    private function allowed(string|array|null $can, Authenticatable $user): bool
    {
        if ($can === null || $can === '' || $can === []) {
            return true;
        }

        return $user->canAny((array) $can);
    }

    /**
     * @param  string|array<int, string>|null  $feature
     */
    private function featureOn(string|array|null $feature): bool
    {
        foreach ((array) $feature as $key) {
            $on = filter_var($this->setting($key, true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($on === false) {
                return false;
            }
        }

        return true;
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        try {
            return settings($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Badge counts. Each query runs at most once per request and only when a
     * visible item asks for it. Failures never break the page.
     */
    private function count(string $key): ?int
    {
        if (array_key_exists($key, $this->counts)) {
            return $this->counts[$key];
        }

        try {
            $value = match ($key) {
                'todayLogs' => PatientLog::today()->count(),
                // Online requests without a linked patient are actionable; archived patients are not.
                'pendingAppointments' => Appointment::where('status', 'pending')
                    ->where(fn ($q) => $q->whereNull('patient_id')
                        ->orWhereHas('patient', fn ($p) => $p->whereNull('patients.deleted_at')))
                    ->count(),
                'pendingIntake' => PatientIntakeSubmission::pending()->count(),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('Navigation badge count failed', ['key' => $key, 'error' => $e->getMessage()]);
            $value = null;
        }

        return $this->counts[$key] = $value;
    }
}
