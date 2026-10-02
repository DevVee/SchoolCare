<?php

namespace App\Support;

use App\Services\SignInCodes;
use Illuminate\Log\Events\MessageLogged;

/**
 * Whether outgoing email can work, in plain words, for Admin > Settings > Email
 * and `php artisan mail:check`. Never returns a secret: only whether a key is set,
 * and email addresses in log lines are masked (***@domain).
 */
class MailHealth
{
    /** Log messages written when a send fails (see the Log::warning / Log::error calls around each send). */
    private const FAILURE_MARKERS = [
        'Test email failed',
        'Appointment notification [',
        'Invitation email could not be sent',
        'Password reset email could not be sent',
        'Online request email to the clinic failed',
        'Sign-in code email could not be sent',
        'Assistant email failed',
    ];

    /** The mail transport in use: brevo, smtp, log, array, failover, ... */
    public static function transport(): string
    {
        $mailer = (string) config('mail.default');

        return (string) config("mail.mailers.{$mailer}.transport", $mailer);
    }

    public static function fromAddress(): string
    {
        return trim((string) config('mail.from.address'));
    }

    /** Domain of the From address, lower case, or '' when there is none. */
    public static function fromDomain(): string
    {
        $address = self::fromAddress();

        return str_contains($address, '@') ? strtolower(substr(strrchr($address, '@'), 1)) : '';
    }

    /** The site's domain from APP_URL, without "www.". */
    public static function siteDomain(): string
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    /**
     * Problems that stop email, or are likely to, in plain words. Empty when the
     * set-up looks right (the provider can still refuse: see recentFailures()).
     *
     * @return list<array{level: 'error'|'warning', text: string}>
     */
    public static function problems(): array
    {
        $out = [];
        $transport = self::transport();

        if (in_array($transport, ['log', 'array'], true)) {
            $out[] = ['level' => 'error', 'text' => "Email sending is not set up: MAIL_MAILER is {$transport}, so emails are only written to the log."];
        } elseif ($transport === 'brevo' && blank(config('services.brevo.key'))) {
            $out[] = ['level' => 'error', 'text' => 'MAIL_MAILER is brevo but BREVO_API_KEY is empty on the server, so every email fails. Add the key from Brevo (SMTP & API > API keys) to the server .env.'];
        } elseif (! SignInCodes::emailReady()) {
            $out[] = ['level' => 'error', 'text' => 'The configured mailer cannot deliver email.'];
        }

        $from = self::fromAddress();
        if (! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $out[] = ['level' => 'error', 'text' => 'There is no valid From address. Set MAIL_FROM_ADDRESS on the server or the From Address below.'];
        } elseif (self::siteDomain() !== '' && ! self::sameDomain(self::fromDomain(), self::siteDomain())) {
            $out[] = ['level' => 'warning', 'text' => 'Emails are sent from @'.self::fromDomain().' but the site is '.self::siteDomain().'. The email provider only sends from a domain or sender verified in its account (Brevo: Senders, Domains & Dedicated IPs). Verify that domain there, or use a From address on a verified domain.'];
        }

        return $out;
    }

    /** True when nothing in problems() is an error. */
    public static function ready(): bool
    {
        return collect(self::problems())->doesntContain('level', 'error');
    }

    /**
     * The latest email failures from the application log, newest first, with
     * email addresses masked.
     *
     * @return list<array{time: string, text: string}>
     */
    public static function recentFailures(int $limit = 5): array
    {
        $files = glob(storage_path('logs/*.log')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        // A failure can be in both the app log and mail-failures.log: list it once.
        $found = [];
        foreach (array_slice($files, 0, 4) as $file) {
            foreach (array_reverse(self::tail($file, 400_000)) as $line) {
                if (! self::isFailure($line)) {
                    continue;
                }
                preg_match('/^\[([^\]]+)\]/', $line, $m);
                $item = ['time' => $m[1] ?? '', 'text' => self::mask(self::message($line))];
                $found[$item['time'].'|'.$item['text']] = $item;
            }
        }

        usort($found, fn ($a, $b) => strcmp($b['time'], $a['time']));

        return array_slice($found, 0, $limit);
    }

    /**
     * Keeps every email failure in storage/logs/mail-failures.log, whatever the
     * app log channel and level are (production logs to stderr, which never
     * reaches storage/logs), so recentFailures() and mail:check can show them.
     * Listens to MessageLogged, which fires for every log call.
     */
    public static function record(MessageLogged $event): void
    {
        if (! in_array($event->level, ['warning', 'error', 'critical', 'alert', 'emergency'], true)) {
            return;
        }
        $message = (string) $event->message;
        if (! collect(self::FAILURE_MARKERS)->contains(fn ($marker) => str_starts_with($message, $marker))) {
            return;
        }

        try {
            $file = storage_path('logs/mail-failures.log');
            $error = isset($event->context['error']) ? (string) $event->context['error'] : '';
            $line = sprintf('[%s] %s.%s: %s %s', now()->format('Y-m-d H:i:s'), app()->environment(), strtoupper($event->level),
                self::mask($message), json_encode(['error' => self::mask($error)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            // Keep the file small: past 256 KB, only the newer half stays.
            if (is_file($file) && filesize($file) > 256_000) {
                file_put_contents($file, implode("\n", array_slice(self::tail($file, 128_000), 1)), LOCK_EX);
            }
            file_put_contents($file, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Never let failure bookkeeping break the request that failed to send.
        }
    }

    /**
     * Which emails are switched on, in plain words (the ones that always send are
     * listed too), for mail:check and the Email settings page.
     *
     * @return list<string>
     */
    public static function switches(): array
    {
        $on = fn (string $key, bool $default = false) => filter_var(settings($key, $default), FILTER_VALIDATE_BOOLEAN) ? 'on' : 'off';

        return [
            'Invitations and password resets: always on',
            'Appointment emails to patients: '.$on('notify_email_appointments').' (Settings > Notifications)',
            'New online request emails to the clinic: '.$on('notify_email_online_request').' (Settings > Notifications)',
            'Sign-in codes by email: '.$on('otp_enabled').' (Settings > Security)',
        ];
    }

    /** "juan@example.com" -> "***@example.com". */
    public static function mask(string $text): string
    {
        return preg_replace('/[A-Za-z0-9._%+\-]+@([A-Za-z0-9.\-]+\.[A-Za-z]{2,})/', '***@$1', $text) ?? $text;
    }

    private static function sameDomain(string $a, string $b): bool
    {
        // mail.example.com sends for example.com and the other way round.
        return $a === $b || str_ends_with($a, '.'.$b) || str_ends_with($b, '.'.$a);
    }

    private static function isFailure(string $line): bool
    {
        if (! preg_match('/^\[[^\]]+\] \w+\.(WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):/', $line)) {
            return false;
        }
        foreach (self::FAILURE_MARKERS as $marker) {
            if (str_contains($line, $marker)) {
                return true;
            }
        }

        return false;
    }

    /** "Test email failed" plus the error text from the JSON context, one line, at most 300 characters. */
    private static function message(string $line): string
    {
        $text = preg_replace('/^\[[^\]]+\] \w+\.\w+: /', '', $line) ?? $line;
        if (preg_match('/^(.*?) (\{.*\})\s*(\[\])?\s*$/', $text, $m)) {
            $context = json_decode($m[2], true);
            $text = $m[1].(is_array($context) && isset($context['error']) ? ': '.$context['error'] : '');
        }

        return mb_strimwidth(trim(preg_replace('/\s+/', ' ', $text) ?? $text), 0, 300, '...');
    }

    /** The last $bytes of a file as lines (log files can be large). */
    private static function tail(string $file, int $bytes): array
    {
        $size = @filesize($file);
        $handle = @fopen($file, 'rb');
        if ($handle === false || $size === false) {
            return [];
        }
        if ($size > $bytes) {
            fseek($handle, -$bytes, SEEK_END);
        }
        $data = (string) stream_get_contents($handle);
        fclose($handle);

        return preg_split('/\r?\n/', $data) ?: [];
    }
}
