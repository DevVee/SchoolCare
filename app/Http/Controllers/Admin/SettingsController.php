<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SavesSettingsGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\AiAssistantService;
use App\Services\SettingsService;
use App\Services\SignInCodes;
use App\Services\SmsService;
use App\Support\MailHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Admin → Settings. One page per group defined in config/settings.php.
 * All routes require the `manage-settings` permission.
 */
class SettingsController extends Controller
{
    use SavesSettingsGroup;

    public function __construct(private readonly SettingsService $settings) {}

    public function index(): RedirectResponse
    {
        $this->authorize('manage-settings');

        $first = array_key_first($this->settings->visibleGroups()) ?? 'general';

        return redirect()->route('admin.settings.edit', $first);
    }

    public function edit(string $group)
    {
        $this->authorize('manage-settings');
        abort_unless($this->groupIsEditable($group), 404);

        return view('admin.settings.edit', [
            'group'    => $group,
            'meta'     => $this->settings->groups()[$group],
            'groups'   => $this->settings->visibleGroups(),
            'fields'   => $this->settings->fields($group),
            'settings' => $this->settings,
            'status'   => $this->providerStatus($group),
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $group): RedirectResponse
    {
        abort_unless($this->groupIsEditable($group), 404);

        $fields = $this->settings->fields($group);

        // Partial save: only these keys of the group (e.g. the SMS on/off switch on
        // the SMS page saves notifications.sms_enabled without touching the others).
        if ($request->filled('only')) {
            $fields = array_intersect_key($fields, array_flip(array_filter((array) $request->input('only'), 'is_string')));
        }

        $changed = $this->saveSettingsFields($request, $this->settings, $group, $fields);

        // Return to the page the change was made from (e.g. SMS), when it is a settings page.
        $returnTo = $request->input('return_to');
        $target = is_string($returnTo) && $this->groupIsEditable($returnTo) ? $returnTo : $group;

        return redirect()
            ->route('admin.settings.edit', $target)
            ->with('success', $changed ? 'Settings saved.' : 'No changes to save.');
    }

    /** Send a test SMS to a number typed by the admin (bypasses the master switch). */
    public function testSms(Request $request, SmsService $sms): RedirectResponse
    {
        $this->authorize('manage-settings');

        $validated = $request->validate([
            'test_number' => ['required', 'string', 'max:20', function ($attr, $value, $fail) use ($sms) {
                if (! $sms->normalizeNumber($value)) {
                    $fail('Enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).');
                }
            }],
        ]);

        $app = settings('app_name') ?: config('app.name');
        $log = $sms->send(
            number: $validated['test_number'],
            message: "Test message from {$app}. SMS delivery is working.",
            recipientName: 'Settings test',
            event: 'test',
            force: true,
        );

        return redirect()
            ->route('admin.settings.edit', 'sms')
            ->with($log->status === 'sent' ? 'success' : 'warning',
                $log->status === 'sent'
                    ? 'Test SMS sent to '.$log->recipient_number.'.'
                    : 'Test SMS not sent: '.($log->error_message ?: 'unknown error').'.');
    }

    /** Send a test email to the signed-in administrator. */
    public function testEmail(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');

        $user = $request->user();
        $app  = settings('app_name') ?: config('app.name');

        try {
            Mail::raw(
                "This is a test email from {$app}.\n\nIf you received it, outgoing email is configured correctly.",
                fn ($m) => $m->to($user->email, $user->name)->subject("{$app}: test email")
            );
        } catch (\Throwable $e) {
            Log::warning('Test email failed', ['error' => $e->getMessage()]);

            return redirect()->route('admin.settings.edit', 'email')
                ->with('warning', 'Test email failed: '.Str::limit($e->getMessage(), 200));
        }

        $mailer = config('mail.default');
        $note = in_array($mailer, ['log', 'array'], true)
            ? ' Email sending is not set up yet, so it was saved to the system log instead of being delivered.'
            : '';

        return redirect()->route('admin.settings.edit', 'email')
            ->with('success', "Test email sent to {$user->email}.{$note}");
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function groupIsEditable(string $group): bool
    {
        return array_key_exists($group, $this->settings->visibleGroups());
    }

    /** Read-only provider status for the SMS / Email / Security / AI pages. Never exposes secrets. */
    private function providerStatus(string $group): array
    {
        return match ($group) {
            'sms' => [
                'api_key_configured' => filled(config('semaphore.api_key')),
                'env_sender'         => (string) config('semaphore.sender_name'),
                'sms_enabled'        => (bool) settings('sms_enabled'),
                'queue'              => (string) config('queue.default'),
            ],
            'email' => $this->mailStatus(),
            'security' => [
                'otp_on'      => (bool) settings('otp_enabled'),
                'applies_to'  => (string) settings('otp_applies_to'),
                'email_ready' => SignInCodes::emailReady(),
                'mailer'      => (string) config('mail.default'),
            ],
            'ai' => [
                'api_key_configured' => AiAssistantService::apiKey() !== '',
                'api_key_source'     => AiAssistantService::apiKeySource(), // 'settings', 'server' or null
                // The model actually used (a retired saved model falls back to the default).
                'model'              => app(AiAssistantService::class)->model(),
                'web_search'         => app(AiAssistantService::class)->webSearchEnabled(),
            ],
            default => [],
        };
    }

    private function mailStatus(): array
    {
        $mailer    = (string) config('mail.default');
        $transport = (string) config("mail.mailers.{$mailer}.transport", $mailer);
        $logLevel  = null;

        if ($transport === 'log') {
            $channel  = config("mail.mailers.{$mailer}.channel") ?: config('logging.default');
            $logLevel = config("logging.channels.{$channel}.level");
            if (config("logging.channels.{$channel}.driver") === 'stack') {
                $first    = config("logging.channels.{$channel}.channels.0");
                $logLevel = config("logging.channels.{$first}.level", $logLevel);
            }
        }

        return [
            'mailer'        => $mailer,
            'transport'     => $transport,
            'host'          => $transport === 'smtp' ? (string) config("mail.mailers.{$mailer}.host") : null,
            'from_address'  => (string) config('mail.from.address'),
            'from_name'     => (string) config('mail.from.name'),
            'log_mode'      => in_array($transport, ['log', 'array'], true),
            'log_level'     => $logLevel,
            'log_hidden'    => $logLevel !== null && ! in_array(strtolower((string) $logLevel), ['debug'], true),
            'queue'         => (string) config('queue.default'),
            'ready'         => MailHealth::ready(),
            'problems'      => MailHealth::problems(),
            'failures'      => MailHealth::recentFailures(3),
            'switches'      => MailHealth::switches(),
        ];
    }
}
