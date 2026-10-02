<?php

namespace App\Console\Commands;

use App\Support\MailHealth;
use Illuminate\Console\Command;

/**
 * Read only: why email does or does not go out on this server. Prints the mailer,
 * whether the provider key is set (never the key), the From and site domains, and
 * the latest send failures from the log with addresses masked, so the output is
 * safe for public CI logs (Actions > Maintenance > mail).
 *
 *     php artisan mail:check
 */
class CheckMail extends Command
{
    protected $signature = 'mail:check';

    protected $description = 'Show whether outgoing email is set up, and the latest send failures (read only)';

    public function handle(): int
    {
        $transport = MailHealth::transport();

        $this->line('Mailer:        '.config('mail.default')." (transport {$transport})");
        if ($transport === 'brevo') {
            $this->line('Brevo API key: '.(filled(config('services.brevo.key')) ? 'set' : 'MISSING'));
        }
        $this->line('From domain:   '.(MailHealth::fromDomain() !== '' ? '@'.MailHealth::fromDomain() : 'none'));
        $this->line('Site domain:   '.(MailHealth::siteDomain() ?: 'none'));
        $this->line('Queue:         '.config('queue.default').' (emails are sent right away, not queued)');
        $this->newLine();

        $problems = MailHealth::problems();
        if ($problems === []) {
            $this->info('The set-up looks right.');
        }
        foreach ($problems as $p) {
            $p['level'] === 'error' ? $this->error($p['text']) : $this->warn($p['text']);
        }

        $this->newLine();
        $failures = MailHealth::recentFailures(8);
        if ($failures === []) {
            $this->line('No email failures in the log.');
        } else {
            $this->line('Latest email failures (newest first):');
            foreach ($failures as $f) {
                $this->line("  [{$f['time']}] {$f['text']}");
            }
        }

        return MailHealth::ready() ? self::SUCCESS : self::FAILURE;
    }
}
