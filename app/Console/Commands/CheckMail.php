<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\BrevoStatus;
use App\Support\MailHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Why email does or does not go out on this server. Prints the mailer, whether
 * the provider key is set (never the key), the From and site domains, which
 * emails are switched on, and the latest send failures with addresses masked,
 * so the output is safe for public CI logs (Actions > Maintenance > mail).
 * With Brevo it also asks Brevo whether the key is accepted, whether the From
 * domain is authenticated (and which DNS records are missing), and what
 * happened to recent emails (delivered, blocked, bounced, with the reason).
 * Read only, unless --send-test: then one test email goes to the first active
 * administrator, and Brevo's delivery result for it is printed.
 *
 *     php artisan mail:check
 *     php artisan mail:check --send-test
 *     php artisan mail:check --send-test --to=someone@example.com
 */
class CheckMail extends Command
{
    protected $signature = 'mail:check
        {--send-test : Also send a test email to the first active administrator}
        {--to= : With --send-test, send the test email to this address instead}';

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

        $brevo = BrevoStatus::report(fresh: true);
        if ($brevo !== null) {
            $this->newLine();
            $this->printBrevo($brevo);
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

        $brevoOk = $brevo === null || collect($brevo['problems'])->doesntContain('level', 'error');

        return MailHealth::ready() && $brevoOk && $sent ? self::SUCCESS : self::FAILURE;
    }

    /** What Brevo says: key, domain authentication with the DNS records, sender, recent deliveries. */
    private function printBrevo(array $brevo): void
    {
        $this->line('== Brevo');
        if (! $brevo['key_ok']) {
            $this->line('API key:       '.($brevo['reachable'] ? 'NOT ACCEPTED ('.$brevo['error'].')' : 'not checked ('.$brevo['error'].')'));
        } else {
            $this->line('API key:       accepted'.($brevo['account'] ? " (account {$brevo['account']})" : ''));

            $domain = $brevo['domain'];
            if ($domain !== null) {
                $state = ! $domain['exists'] ? 'NOT ADDED in Brevo' : ($domain['authenticated'] ? 'authenticated' : 'NOT AUTHENTICATED');
                $this->line("Domain:        {$domain['name']}: {$state}");
                foreach ($domain['records'] as $r) {
                    $this->line(sprintf('  %-8s %-5s %-28s %s  %s', $r['name'], $r['type'], $r['host'], $r['ok'] ? 'ok     ' : 'MISSING', $r['value']));
                }
            }
            if ($brevo['sender'] !== null) {
                $sender = $brevo['sender'];
                $this->line('Sender:        '.MailHealth::mask($sender['email']).': '.($sender['verified']
                    ? 'allowed'.($sender['via_domain'] ? ' (authenticated domain)' : ' (verified sender)')
                    : 'NOT VERIFIED'));
            }

            $this->line('Recent emails (Brevo, last 30 days, newest first):');
            foreach (array_slice($brevo['events'], 0, 10) as $e) {
                $this->line("  [{$e['time']}] {$e['label']}: {$e['email']}"
                    .($e['subject'] !== '' ? ' "'.$e['subject'].'"' : '')
                    .($e['reason'] !== '' ? " ({$e['reason']})" : ''));
            }
            if ($brevo['events'] === []) {
                $this->line('  none');
            }
        }

        foreach ($brevo['problems'] as $p) {
            $p['level'] === 'error' ? $this->error($p['text']) : $this->warn($p['text']);
        }
    }

    /** Brevo's events for one message, waiting up to ~30 s for Brevo to log more than "sent". */
    private function printDelivery(string $messageId): void
    {
        $events = [];
        for ($i = 0; $i < 6; $i++) {
            sleep(app()->runningUnitTests() ? 0 : 5);
            $events = BrevoStatus::eventsFor($messageId);
            if (collect($events)->contains(fn ($e) => $e['event'] !== 'requests')) {
                break;
            }
        }

        if ($events === []) {
            $this->warn('Brevo has not logged this email yet. Run the check again in a minute, or look in Brevo: Transactional > Logs.');

            return;
        }
        $this->line('Brevo log for the test email:');
        foreach (array_reverse($events) as $e) {
            $line = "  [{$e['time']}] {$e['label']}".($e['reason'] !== '' ? ": {$e['reason']}" : '');
            $e['bad'] ? $this->error($line) : $this->line($line);
        }
    }

    /** One test email to the first active administrator (or --to); prints the outcome with the address masked. */
    private function sendTest(): bool
    {
        $address = trim((string) $this->option('to'));
        $name = null;
        if ($address !== '') {
            if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $this->error('Test email not sent: --to is not a valid email address.');

                return false;
            }
        } else {
            $admin = User::query()->where('is_active', true)->orderBy('id')->get()
                ->first(fn (User $u) => $u->isAdmin() && filter_var($u->email, FILTER_VALIDATE_EMAIL));
            if (! $admin) {
                $this->error('Test email not sent: there is no active administrator with an email address.');

                return false;
            }
            [$address, $name] = [$admin->email, $admin->name];
        }

        $to = MailHealth::mask($address);
        $app = settings('app_name') ?: config('app.name');
        try {
            $message = Mail::raw(
                "This is a test email from {$app}, sent from the server (php artisan mail:check --send-test).\n\nIf you received it, outgoing email works.",
                fn ($m) => $m->to($address, $name)->subject("{$app}: test email")
            );
        } catch (\Throwable $e) {
            Log::warning('Test email failed', ['error' => $e->getMessage()]);
            $this->error("Test email to {$to} failed: ".MailHealth::mask($e->getMessage()));

            return false;
        }

        $this->info("Test email to {$to}: accepted by ".MailHealth::transport().'.');
        if (BrevoStatus::applies() && $message?->getMessageId()) {
            $this->printDelivery((string) $message->getMessageId());
        } else {
            $this->line('If it does not arrive, check the provider\'s logs and the spam folder.');
        }

        return true;
    }
}
