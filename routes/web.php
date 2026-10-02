<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispensingController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MedicineCategoryController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SmsController;
use Illuminate\Support\Facades\Route;

// ─── Keep-alive ping (no session/auth/DB overhead) ──────────────────────────
// Lightweight liveness probe for load balancers / uptime monitors.
// Returns plain text 'pong' — no middleware stack, no session creation.
Route::get('/ping', fn () => response('pong', 200)->header('Content-Type', 'text/plain'));

// ─── Session keep-alive + CSRF token refresh ──────────────────────────────────
// Called by the frontend every 20 minutes from active browser tabs.
// Two purposes:
//   1. Extends the session lifetime (any auth request resets the session TTL)
//   2. Returns a fresh CSRF token so forms on long-lived tabs don't 419
// Must be auth-protected so it reads/touches the real session.
Route::middleware(['auth'])->get('/session/token', function () {
    return response()->json(['token' => csrf_token()]);
})->name('session.token');

// ─── Rich health check endpoint ───────────────────────────────────────────────
// Checks DB connectivity, cache, and storage writability.
// Excluded from session middleware to avoid creating ghost sessions.
// Use this URL for container health checks and uptime monitoring.
Route::get('/health', function () {
    $checks   = ['status' => 'ok'];
    $httpCode = 200;

    // Database connectivity
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Exception $e) {
        // Never expose raw driver/DSN messages publicly — log them instead.
        \Illuminate\Support\Facades\Log::error('Health check: database unavailable', ['error' => $e->getMessage()]);
        $checks['database'] = 'error';
        $httpCode = 503;
    }

    // Cache read/write
    try {
        \Illuminate\Support\Facades\Cache::put('_health_check', 1, 10);
        $checks['cache'] = \Illuminate\Support\Facades\Cache::get('_health_check') ? 'ok' : 'miss';
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Health check: cache unavailable', ['error' => $e->getMessage()]);
        $checks['cache'] = 'error';
        $httpCode = 503;
    }

    // Storage writable
    $storageOk = is_writable(storage_path('framework'));
    $checks['storage_writable'] = $storageOk ? 'ok' : 'error';
    if (! $storageOk) {
        $httpCode = 503;
    }

    // Runtime info (safe to expose — no secrets)
    $checks['session_driver'] = config('session.driver');
    $checks['cache_driver']   = config('cache.default');
    $checks['app_env']        = config('app.env');
    $checks['timestamp']      = now()->toIso8601String();

    if ($checks['status'] === 'ok' && $httpCode !== 200) {
        $checks['status'] = 'degraded';
    }

    return response()->json($checks, $httpCode);
})->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class]);

// ─── Public website ───────────────────────────────────────────────────────────
// "/" is the product page for guests (signed-in staff go straight to the
// dashboard; copy: App\Support\ProductSite). "/clinic" is the school clinic's
// own page and "/privacy" its privacy notice (content: Administration → Website).
Route::get('/', [\App\Http\Controllers\LandingController::class, 'show'])->name('home');
Route::get('/clinic', [\App\Http\Controllers\LandingController::class, 'clinic'])->name('clinic');
Route::get('/privacy', [\App\Http\Controllers\LandingController::class, 'privacy'])->name('privacy');

// ─── Public: online appointment requests + clinic schedule board ─────────────
// 404 unless Settings → Appointments → "Accept online appointment requests".
Route::get('/request-appointment', [\App\Http\Controllers\PublicAppointmentController::class, 'create'])
     ->name('public.appointments.create');
Route::post('/request-appointment', [\App\Http\Controllers\PublicAppointmentController::class, 'store'])
     ->name('public.appointments.store')
     ->middleware('throttle:5,60,public-appointment');
Route::get('/request-appointment/thanks', [\App\Http\Controllers\PublicAppointmentController::class, 'thanks'])
     ->name('public.appointments.thanks');
Route::get('/request-appointment/slots', [\App\Http\Controllers\PublicAppointmentController::class, 'slots'])
     ->name('public.appointments.slots')
     ->middleware('throttle:60,1');
Route::get('/clinic-schedule', [\App\Http\Controllers\PublicAppointmentController::class, 'schedule'])
     ->name('public.schedule');

// ─── Public: Student Health Information Form ──────────────────────────────────
// 404 unless Settings → Online Health Form is on. Stored for staff review only.
Route::get('/health-form', [\App\Http\Controllers\PublicHealthFormController::class, 'create'])
     ->name('public.health-form.create');
Route::post('/health-form', [\App\Http\Controllers\PublicHealthFormController::class, 'store'])
     ->name('public.health-form.store')
     ->middleware('throttle:5,60,public-health-form');
Route::get('/health-form/thanks', [\App\Http\Controllers\PublicHealthFormController::class, 'thanks'])
     ->name('public.health-form.thanks');

// ─── Authenticated Routes ─────────────────────────────────────────────────────
Route::middleware(['auth', 'check.active', 'password.changed'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/brief', \App\Http\Controllers\DashboardBriefController::class)
         ->name('dashboard.brief')
         ->middleware('throttle:20,1');

    // ─── Profile ──────────────────────────────────────────────────────────────
    Route::get('/profile',              [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile',              [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar',      [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar',    [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::put('/profile/password',     [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profile/sessions',  [ProfileController::class, 'destroyOtherSessions'])->name('profile.sessions.destroy')
         ->middleware('throttle:10,1');
    Route::delete('/profile',           [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ─── Patient Log (Clinic Logbook) ─────────────────────────────────────────
    // Clinic visits calendar (SSCMS calendar/calendar.php); before the resource.
    Route::get('patient-logs/calendar', [\App\Http\Controllers\PatientLogCalendarController::class, 'month'])
         ->name('patient-logs.calendar');

    Route::resource('patient-logs', PatientLogController::class)
         ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

    // Discharge ("Out" button): permission checked in the controller.
    Route::patch('patient-logs/{patient_log}/discharge', [PatientLogController::class, 'discharge'])
         ->name('patient-logs.discharge');

    // Visit photos: private disk, served only through this authorized route.
    Route::post('patient-logs/{patient_log}/attachments', [\App\Http\Controllers\PatientLogAttachmentController::class, 'store'])
         ->name('patient-logs.attachments.store')
         ->middleware('throttle:30,1');
    Route::get('patient-logs/{patient_log}/attachments/{attachment}', [\App\Http\Controllers\PatientLogAttachmentController::class, 'show'])
         ->name('patient-logs.attachments.show')
         ->scopeBindings();
    Route::delete('patient-logs/{patient_log}/attachments/{attachment}', [\App\Http\Controllers\PatientLogAttachmentController::class, 'destroy'])
         ->name('patient-logs.attachments.destroy')
         ->scopeBindings();

    // ─── Patients ─────────────────────────────────────────────────────────────
    // Static patient routes are declared BEFORE the resource so they are not
    // captured by patients/{patient}. Permissions are checked in the controllers.
    Route::prefix('patients')->name('patients.')->group(function () {
        // Type-to-search for patient pickers (JSON; x-ui.patient-picker)
        Route::get('lookup', \App\Http\Controllers\PatientLookupController::class)->name('lookup')
             ->middleware('throttle:240,1');

        // Import (.xlsx / .csv): upload -> preview -> confirm
        Route::get('import',           [\App\Http\Controllers\PatientImportController::class, 'create'])->name('import.create');
        Route::get('import/template',  [\App\Http\Controllers\PatientImportController::class, 'template'])->name('import.template');
        Route::post('import/preview',  [\App\Http\Controllers\PatientImportController::class, 'preview'])->name('import.preview')
             ->middleware('throttle:20,1');
        Route::post('import',          [\App\Http\Controllers\PatientImportController::class, 'store'])->name('import.store')
             ->middleware('throttle:10,1');
        Route::delete('import',        [\App\Http\Controllers\PatientImportController::class, 'destroy'])->name('import.destroy');

        // Export with the list filters, and bulk actions on selected rows
        Route::get('export', [\App\Http\Controllers\PatientBulkController::class, 'export'])->name('export')
             ->middleware('throttle:20,1');
        Route::post('bulk',  [\App\Http\Controllers\PatientBulkController::class, 'bulk'])->name('bulk')
             ->middleware('throttle:30,1');

        // Year-end promotion wizard
        Route::get('promote',          [\App\Http\Controllers\PatientBulkController::class, 'promoteForm'])->name('promote.form');
        Route::post('promote/preview', [\App\Http\Controllers\PatientBulkController::class, 'promotePreview'])->name('promote.preview');
        Route::post('promote',         [\App\Http\Controllers\PatientBulkController::class, 'promote'])->name('promote');

        // Online health information form review queue
        Route::get('intake',                         [\App\Http\Controllers\PatientIntakeController::class, 'index'])->name('intake.index');
        Route::get('intake/{submission}',            [\App\Http\Controllers\PatientIntakeController::class, 'show'])->name('intake.show');
        Route::post('intake/{submission}/approve',   [\App\Http\Controllers\PatientIntakeController::class, 'approve'])->name('intake.approve');
        Route::post('intake/{submission}/merge',     [\App\Http\Controllers\PatientIntakeController::class, 'merge'])->name('intake.merge');
        Route::post('intake/{submission}/reject',    [\App\Http\Controllers\PatientIntakeController::class, 'reject'])->name('intake.reject');
    });

    // show resolves archived (soft-deleted) patients so clinical history links keep working
    Route::resource('patients', PatientController::class)->withTrashed(['show']);

    Route::get('patients/{patient}/history', [PatientController::class, 'history'])
         ->name('patients.history')
         ->withTrashed();

    // Health Report Card (SSCMS patient_health_report.php): page + PDF
    Route::get('patients/{patient}/health-report', [\App\Http\Controllers\PatientHealthReportController::class, 'show'])
         ->name('patients.health-report')
         ->withTrashed();
    Route::get('patients/{patient}/health-report/pdf', [\App\Http\Controllers\PatientHealthReportController::class, 'pdf'])
         ->name('patients.health-report.pdf')
         ->middleware('throttle:20,1')
         ->withTrashed();

    // MED-7 FIX: Restore soft-deleted patient (withTrashed binding)
    Route::patch('patients/{patient}/restore', [PatientController::class, 'restore'])
         ->name('patients.restore')
         ->middleware('can:restore-patients')
         ->withTrashed();

    // ─── Appointments ─────────────────────────────────────────────────────────
    // Static routes before the resource (appointments/{appointment}).
    Route::get('appointments/calendar',     [\App\Http\Controllers\AppointmentCalendarController::class, 'month'])->name('appointments.calendar');
    Route::get('appointments/today',        [\App\Http\Controllers\AppointmentCalendarController::class, 'today'])->name('appointments.today');
    Route::get('appointments/availability', [AppointmentController::class, 'availability'])->name('appointments.availability')
         ->middleware('throttle:120,1');

    Route::resource('appointments', AppointmentController::class);
    Route::patch('appointments/{appointment}/link-patient', [AppointmentController::class, 'linkPatient'])
         ->name('appointments.link-patient');
    Route::patch('appointments/{appointment}/approve',  [AppointmentController::class, 'approve'])
         ->name('appointments.approve');
    Route::patch('appointments/{appointment}/cancel',   [AppointmentController::class, 'cancel'])
         ->name('appointments.cancel');
    Route::patch('appointments/{appointment}/no-show',  [AppointmentController::class, 'noShow'])
         ->name('appointments.no-show')
         ->middleware('can:complete-appointments');
    Route::patch('appointments/{appointment}/complete', [AppointmentController::class, 'complete'])
         ->name('appointments.complete')
         ->middleware('can:complete-appointments');

    // ─── Specialist visits (doctor / dentist clinic days) ─────────────────────
    Route::resource('specialist-visits', \App\Http\Controllers\SpecialistVisitController::class);

    // ─── Consultations ────────────────────────────────────────────────────────
    Route::resource('consultations', ConsultationController::class);

    // ─── Medicines ────────────────────────────────────────────────────────────
    // Named routes BEFORE resource() to avoid route collision with {medicine} param
    Route::get('medicines/low-stock', [MedicineController::class, 'lowStock'])->name('medicines.low-stock');
    Route::get('medicines/expiring',  [MedicineController::class, 'expiring'])->name('medicines.expiring');
    Route::get('medicines/lookup',    [MedicineController::class, 'lookup'])
         ->name('medicines.lookup')
         ->middleware(['can:view-medicines', 'throttle:120,1']);
    Route::resource('medicines', MedicineController::class);
    Route::patch('medicines/{medicine}/batches/{batch}', [MedicineController::class, 'updateBatch'])
         ->name('medicines.batches.update')
         ->middleware('can:update-medicines')
         ->scopeBindings();

    // ─── Expired batch disposal ───────────────────────────────────────────────
    Route::post('inventory/batches/{batch}/dispose', [\App\Http\Controllers\MedicineDisposalController::class, 'store'])
         ->name('disposals.store')
         ->middleware('can:dispose-medicines');
    Route::get('inventory/disposals', [\App\Http\Controllers\MedicineDisposalController::class, 'index'])
         ->name('disposals.index')
         ->middleware('can:view-inventory');
    Route::get('inventory/disposals/export', [\App\Http\Controllers\MedicineDisposalController::class, 'export'])
         ->name('disposals.export')
         ->middleware(['can:export-reports', 'throttle:20,1']);

    // ─── Assets & equipment ───────────────────────────────────────────────────
    Route::get('assets/export', [\App\Http\Controllers\AssetController::class, 'export'])
         ->name('assets.export')
         ->middleware(['can:view-assets', 'throttle:20,1']);
    Route::resource('assets', \App\Http\Controllers\AssetController::class);

    // ─── Medicine Categories ──────────────────────────────────────────────────
    Route::resource('medicine-categories', MedicineCategoryController::class)
         ->only(['index', 'store', 'edit', 'update', 'destroy']);

    // ─── Inventory ────────────────────────────────────────────────────────────
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/',             [InventoryController::class, 'index'])->name('index');
        Route::get('/transactions', [InventoryController::class, 'transactions'])->name('transactions');
        Route::get('/stock-in',     [InventoryController::class, 'stockInForm'])->name('stock-in.form');
        Route::post('/stock-in',    [InventoryController::class, 'stockIn'])->name('stock-in');
        Route::get('/stock-out',    [InventoryController::class, 'stockOutForm'])->name('stock-out.form');
        Route::post('/stock-out',   [InventoryController::class, 'stockOut'])->name('stock-out');
    });

    // ─── Dispensing ───────────────────────────────────────────────────────────
    Route::resource('dispensing', DispensingController::class)
         ->only(['index', 'create', 'store', 'show']);

    // ─── Reports ──────────────────────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/',               [ReportController::class, 'index'])->name('index');
        Route::get('/daily',          [ReportController::class, 'daily'])->name('daily');
        Route::get('/monthly',        [ReportController::class, 'monthly'])->name('monthly');
        Route::get('/annual',         [ReportController::class, 'annual'])->name('annual');
        Route::get('/medicine-usage', [ReportController::class, 'medicineUsage'])->name('medicine-usage');
        Route::get('/inventory',      [ReportController::class, 'inventory'])->name('inventory');
        Route::get('/appointments',   [ReportController::class, 'appointments'])->name('appointments');

        // HIGH-3 FIX: Rate-limit exports to prevent DoS / report-scraping.
        // 20 exports per minute per authenticated user is generous for legitimate use.
        Route::get('/export/{type}', [ReportController::class, 'export'])
             ->name('export')
             ->middleware('throttle:20,1');
    });

    // ─── SMS ──────────────────────────────────────────────────────────────────
    Route::prefix('sms')->name('sms.')->group(function () {
        Route::get('/',    [SmsController::class, 'index'])->name('index');
        Route::get('/send', [SmsController::class, 'create'])->name('create');

        // HIGH-3 FIX: Limit manual SMS sends to prevent API credit abuse / spam.
        Route::post('/send', [SmsController::class, 'send'])
             ->name('send')
             ->middleware('throttle:15,1');
    });

    // ─── AI Assistant ─────────────────────────────────────────────────────────
    Route::prefix('ai-assistant')->name('ai-assistant.')->group(function () {
        Route::get('/',      [AiAssistantController::class, 'index'])->name('index');
        Route::delete('/clear', [AiAssistantController::class, 'clear'])->name('clear');
        // One question and its answer (own conversations only, checked in the controller).
        Route::delete('/{conversation}', [AiAssistantController::class, 'destroy'])
             ->whereNumber('conversation')
             ->name('destroy');

        // HIGH-3 FIX: Limit AI chat to 30 messages/minute to protect Groq API quota.
        Route::post('/chat', [AiAssistantController::class, 'chat'])
             ->name('chat')
             ->middleware('throttle:30,1');

        // Cards the assistant prepared: nothing runs until the user confirms here
        // (also limited to 20 confirmed actions an hour per user, in CocoActions).
        Route::post('/actions/{action}/confirm', [\App\Http\Controllers\AiAssistantActionController::class, 'confirm'])
             ->whereUuid('action')
             ->name('actions.confirm')
             ->middleware('throttle:30,1');
        Route::post('/actions/{action}/cancel', [\App\Http\Controllers\AiAssistantActionController::class, 'cancel'])
             ->whereUuid('action')
             ->name('actions.cancel')
             ->middleware('throttle:30,1');
    });

    // ─── Admin ────────────────────────────────────────────────────────────────
    // Access is per permission (not per role) so custom roles can be granted
    // individual admin areas. Mutating user actions additionally require
    // manage-users (checked in UserController / FormRequests).
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:view-users')->group(function () {
            Route::resource('users', UserController::class);
            Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
                 ->name('users.toggle-active');
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
                 ->name('users.reset-password')
                 ->middleware('throttle:10,1');
            Route::post('users/{user}/resend-invitation', [UserController::class, 'resendInvitation'])
                 ->name('users.resend-invitation')
                 ->middleware('throttle:10,1');
            Route::post('users/{user}/forget-devices', [UserController::class, 'forgetDevices'])
                 ->name('users.forget-devices');
            Route::post('users/{user}/sign-out', [UserController::class, 'signOut'])
                 ->name('users.sign-out');
        });

        Route::resource('roles', RoleController::class)
             ->except('show')
             ->middleware('can:manage-roles');

        Route::middleware('can:manage-settings')->group(function () {
            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::post('settings/test-sms', [SettingsController::class, 'testSms'])
                 ->name('settings.test-sms')
                 ->middleware('throttle:5,1');
            Route::post('settings/test-email', [SettingsController::class, 'testEmail'])
                 ->name('settings.test-email')
                 ->middleware('throttle:5,1');
            Route::get('settings/{group}', [SettingsController::class, 'edit'])
                 ->where('group', '[a-z_]+')
                 ->name('settings.edit');
            Route::put('settings/{group}', [SettingsController::class, 'update'])
                 ->where('group', '[a-z_]+')
                 ->name('settings.update');
        });

        // Appointment time slots (label, times, capacity, weekdays)
        Route::middleware('can:manage-appointment-slots')->group(function () {
            Route::resource('appointment-slots', \App\Http\Controllers\Admin\AppointmentSlotController::class)->except('show');
            Route::patch('appointment-slots/{appointment_slot}/toggle', [\App\Http\Controllers\Admin\AppointmentSlotController::class, 'toggle'])
                 ->name('appointment-slots.toggle');
        });

        Route::middleware('can:view-audit-logs')->group(function () {
            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('audit-logs/export', [AuditLogController::class, 'export'])
                 ->name('audit-logs.export')
                 ->middleware('throttle:10,1');
        });

        // Public website content: page text, services, FAQs, team, advisories.
        Route::middleware('can:manage-landing')->prefix('website')->name('website.')->group(function () {
            $items = \App\Http\Controllers\Admin\LandingItemController::class;
            $ads   = \App\Http\Controllers\Admin\AnnouncementController::class;

            Route::get('/', [\App\Http\Controllers\Admin\WebsiteController::class, 'edit'])->name('edit');
            Route::put('/', [\App\Http\Controllers\Admin\WebsiteController::class, 'update'])->name('update');

            Route::get('advisories',                   [$ads, 'index'])->name('advisories.index');
            Route::get('advisories/create',            [$ads, 'create'])->name('advisories.create');
            Route::post('advisories',                  [$ads, 'store'])->name('advisories.store');
            Route::get('advisories/{advisory}/edit',   [$ads, 'edit'])->name('advisories.edit');
            Route::put('advisories/{advisory}',        [$ads, 'update'])->name('advisories.update');
            Route::patch('advisories/{advisory}/toggle', [$ads, 'toggle'])->name('advisories.toggle');
            Route::patch('advisories/{advisory}/move', [$ads, 'move'])->name('advisories.move');
            Route::delete('advisories/{advisory}',     [$ads, 'destroy'])->name('advisories.destroy');

            Route::whereIn('segment', ['services', 'faqs', 'team'])->group(function () use ($items) {
                Route::get('{segment}',                 [$items, 'index'])->name('items.index');
                Route::get('{segment}/create',          [$items, 'create'])->name('items.create');
                Route::post('{segment}',                [$items, 'store'])->name('items.store');
                Route::get('{segment}/{item}/edit',     [$items, 'edit'])->name('items.edit');
                Route::put('{segment}/{item}',          [$items, 'update'])->name('items.update');
                Route::patch('{segment}/{item}/toggle', [$items, 'toggle'])->name('items.toggle');
                Route::patch('{segment}/{item}/move',   [$items, 'move'])->name('items.move');
                Route::delete('{segment}/{item}',       [$items, 'destroy'])->name('items.destroy');
            });
        });
    });

    // ─── UI kit (living style guide for x-ui.* components) ────────────────────
    Route::view('/ui-kit', 'ui-kit')->middleware('can:manage-settings')->name('ui.kit');
});

require __DIR__.'/auth.php';
