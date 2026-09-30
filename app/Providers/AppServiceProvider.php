<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Medicine;
use App\Models\Patient;
use App\Observers\AppointmentObserver;
use App\Observers\ConsultationObserver;
use App\Observers\MedicineObserver;
use App\Observers\PatientObserver;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\PatientRepository;
use Composer\CaBundle\CaBundle;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            PatientRepositoryInterface::class,
            PatientRepository::class
        );

        // Scoped (not singleton) so cached setting values are dropped between
        // requests and between queued jobs in a long-running worker.
        $this->app->scoped(\App\Services\SettingsService::class);

        // One navigation resolver per request, so sidebar badge counts are queried once.
        $this->app->scoped(\App\View\Composers\NavigationComposer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Verify TLS for all outgoing HTTP (Semaphore, Groq) against the system
        // CA store, falling back to Mozilla's bundle when PHP has none configured
        // (common on Windows: "cURL error 60: unable to get local issuer certificate").
        Http::globalOptions(['verify' => CaBundle::getSystemCaRootBundlePath()]);

        // Force HTTPS for all generated URLs when APP_URL is https (e.g. behind
        // a TLS-terminating reverse proxy). Without this, route() returns
        // http:// URLs because the proxy → app connection is plain HTTP.
        if (str_starts_with(config('app.url', ''), 'https://')) {
            URL::forceScheme('https');
        }

        // Branded pagination for every ->links() call (resources/views/vendor/pagination/sscms*.blade.php).
        \Illuminate\Pagination\Paginator::defaultView('pagination::sscms');
        \Illuminate\Pagination\Paginator::defaultSimpleView('pagination::sscms-simple');

        // App shell sidebar: config/navigation.php resolved for the signed-in user.
        \Illuminate\Support\Facades\View::composer('layouts.partials.sidebar', \App\View\Composers\NavigationComposer::class);

        Patient::observe(PatientObserver::class);
        Appointment::observe(AppointmentObserver::class);
        Consultation::observe(ConsultationObserver::class);
        Medicine::observe(MedicineObserver::class);

        $this->bootAuthorization();
        $this->bootSettings();
    }

    /**
     * Apply admin-editable settings to runtime config (app name, timezone,
     * mail sender) and brand the password-reset email. Wrapped in try/catch
     * because the settings table may not exist yet (fresh install, migrate).
     */
    private function bootSettings(): void
    {
        try {
            $settings = settings();

            config(['app.name' => $settings->get('app_name') ?: config('app.name')]);

            $tz = (string) $settings->get('timezone');
            if ($tz !== '' && $tz !== config('app.timezone') && in_array($tz, timezone_identifiers_list(), true)) {
                config(['app.timezone' => $tz]);
                date_default_timezone_set($tz);
            }

            $fromAddress = trim((string) $settings->get('mail_from_address'));
            $fromName    = trim((string) $settings->get('mail_from_name')) ?: config('app.name');
            config([
                'mail.from.address' => $fromAddress !== '' ? $fromAddress : config('mail.from.address'),
                'mail.from.name'    => $fromName,
            ]);
        } catch (\Throwable) {
            // Settings unavailable (no table / no DB yet) — keep .env config.
        }

        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(
            fn ($notifiable, string $token) => \App\Notifications\BrandedResetPassword::mail($notifiable, $token)
        );
    }

    /**
     * Super-admin bypass + application-wide password policy.
     */
    private function bootAuthorization(): void
    {
        // The super-admin role passes every plain permission check (so it can
        // never be locked out by a missing permission row). Checks that carry
        // arguments — model policies such as @can('update', $appointment) —
        // return null here so their state rules (pending-only etc.) still run.
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if ($arguments === []
                && method_exists($user, 'hasRole')
                && $user->hasRole(config('clinovia.super_admin_role', 'administrator'))) {
                return true;
            }

            return null;
        });

        // One password policy for profile change, password reset and
        // admin-created / admin-updated accounts.
        Password::defaults(function () {
            $rule = Password::min(10)->mixedCase()->numbers()->symbols();

            return $this->app->environment('production') ? $rule->uncompromised() : $rule;
        });
    }
}
