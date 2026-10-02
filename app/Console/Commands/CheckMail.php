<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\MailHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Why email does or does not go out on this server. Prints the mailer, whether
 * the provider key is set (never the key), the From and site domains, which
 * emails are switched on, and the latest send failures with addresses masked,
 * so the output is safe for public CI logs (Actions > Maintenance > mail).
 * Read only, unless --send-test: then one test email goes to the first active
 * administrator and the provider's answer is printed.
 *
 *     php artisan mail:check
 *     php artisan mail:check --send-test
 */
class CheckMail extends Command
{
    protected $signature = 'mail:check {--send-test : Also send a test email to the first active administrator}';

    protected $description = 'Show whether outgoing email is set up, and the latest send failures';

    public function handle(): int
    {
        $transport = MailHealth::transport();
        $channel = (string) config('logging.default');

        $this->line('Mailer:        '.config('mail.default')." (transport {$transport})");
        if ($transport === 'brevo') {
            $this->line('Brevo API key: '.(filled(config('services.brevo.key')) ? 'set' : 'MISSING'));
        }
        $this->line('From domain:   '.(MailHealth::fromDomain() !== '' ? '@'.MailHealth::fromDomain() : 'none'));
        $this->line('Site domain:   '.(MailHealth::siteDomain() ?: 'none'));
        $this->line('Queue:         '.config('queue.default').' (emails are sent right away, not queued)');
        $this->line("App log:       {$channel} (email failures are also kept in storage/logs/mail-failures.log)");
        $this->newLine();
        foreach (MailHealth::switches() as $switch) {
            $this->line($switch);
        }
        $this->newLine();

        $problems = MailHealth::problems();
        if ($problems === []) {
            $this->info('The set-up looks right.');
        }
        foreach ($problems as $p) {
            $p['level'] === 'error' ? $this->error($p['text']) : $this->warn($p['text']);
        }

        $sent = true;
        if ($this->option('send-test')) {
            $this->newLine();
            $sent = $this->sendTest();
        }

        $this->newLine();
        $failures = MailHealth::recentFailures(8);
        if ($failures === []) {
            $this->line('No email failures recorded.');
        } else {
            $this->line('Latest email failures (newest first):');
            foreach ($failures as $f) {
                $this->line("  [{$f['time']}] {$f['text']}");
            }
        }

        return MailHealth::ready() && $sent ? self::SUCCESS : self::FAILURE;
    }

    /** One test email to the first active administrator; prints the outcome with the address masked. */
    private function sendTest(): bool
    {
        $admin = User::query()->where('is_active', true)->orderBy('id')->get()
            ->first(fn (User $u) => $u->isAdmin() && filter_var($u->email, FILTER_VALIDATE_EMAIL));
        if (! $admin) {
            $this->error('Test email not sent: there is no active administrator with an email address.');

            return false;
        }

        $to = MailHealth::mask($admin->email);
        $app = settings('app_name') ?: config('app.name');
        try {
            Mail::raw(
                "This is a test email from {$app}, sent from the server (php artisan mail:check --send-test).\n\nIf you received it, outgoing email works.",
                fn ($m) => $m->to($admin->email, $admin->name)->subject("{$app}: test email")
            );
        } catch (\Throwable $e) {
            Log::warning('Test email failed', ['error' => $e->getMessage()]);
            $this->error("Test email to {$to} failed: ".MailHealth::mask($e->getMessage()));

            return false;
        }

        $this->info("Test email to {$to}: accepted by ".MailHealth::transport().'. If it does not arrive, check the provider\'s logs (Brevo: Transactional > Logs) and the spam folder.');

        return true;
    }
}
