<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * What Brevo itself says about our email, for Settings > Email and
 * `php artisan mail:check`: whether the API key is accepted, whether the From
 * domain is authenticated (and the DNS records still missing), whether the
 * From address is a verified sender, and what happened to recent emails
 * (delivered, blocked, bounced, with Brevo's reason).
 *
 * "The set-up looks right" only means the key is set. Brevo can still accept an
 * email and then block it or let it land in spam, because the domain is not
 * authenticated. These calls show that. Never returns the key; email addresses
 * in events are masked. Results are cached briefly so the settings page stays fast.
 */
class BrevoStatus
{
    private const API = 'https://api.brevo.com/v3';
    private const CACHE_SECONDS = 300;

    /** Brevo event names, in plain words. */
    private const EVENT_LABELS = [
        'requests'      => 'Sent to Brevo',
        'delivered'     => 'Delivered',
        'opened'        => 'Opened',
        'uniqueOpened'  => 'Opened',
        'loadedByProxy' => 'Opened',
        'clicks'        => 'Link clicked',
        'deferred'      => 'Delayed, Brevo is retrying',
        'softBounces'   => 'Soft bounce (mailbox full or server busy)',
        'hardBounces'   => 'Hard bounce (address does not exist)',
        'bounces'       => 'Bounced',
        'blocked'       => 'Blocked by Brevo',
        'invalid'       => 'Invalid address',
        'spam'          => 'Marked as spam',
        'unsubscribed'  => 'Unsubscribed',
        'error'         => 'Error',
    ];

    /** Events that mean the email did not reach the inbox. */
    public const BAD_EVENTS = ['softBounces', 'hardBounces', 'bounces', 'blocked', 'invalid', 'spam', 'error'];

    /** Whether Brevo is the mailer and its key is set (only then are these checks possible). */
    public static function applies(): bool
    {
        return MailHealth::transport() === 'brevo' && filled(config('services.brevo.key'));
    }

    /**
     * Everything Brevo says, in one array (cached).
     *
     * @return array{
     *   checked_at: string,
     *   reachable: bool,
     *   key_ok: bool,
     *   error: ?string,
     *   account: ?string,
     *   domain: ?array{name: string, exists: bool, authenticated: bool, verified: bool, records: list<array{name: string, type: string, host: string, value: string, ok: bool}>},
     *   sender: ?array{email: string, verified: bool, via_domain: bool},
     *   events: list<array{time: string, event: string, label: string, bad: bool, email: string, subject: string, reason: string, message_id: string}>,
     *   emails: list<array{time: string, event: string, label: string, bad: bool, email: string, subject: string, reason: string, message_id: string}>,
     *   problems: list<array{level: 'error'|'warning', kind: string, text: string}>
     * }|null  null when Brevo is not the mailer
     */
    public static function report(bool $fresh = false): ?array
    {
        if (! self::applies()) {
            return null;
        }

        $key = 'brevo-status:'.md5(config('services.brevo.key').'|'.MailHealth::fromAddress());
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::CACHE_SECONDS, fn () => self::build());
    }

    public static function forget(): void
    {
        Cache::forget('brevo-status:'.md5(config('services.brevo.key').'|'.MailHealth::fromAddress()));
    }

    /**
     * What happened to one email, by the message id Brevo returned when it was
     * accepted (newest first). Empty while Brevo has not logged it yet.
     *
     * @return list<array{time: string, event: string, label: string, bad: bool, email: string, subject: string, reason: string}>
     */
    public static function eventsFor(string $messageId): array
    {
        if (! self::applies() || $messageId === '') {
            return [];
        }

        $response = self::get('/smtp/statistics/events', ['messageId' => $messageId, 'limit' => 20, 'sort' => 'desc']);

        return $response?->successful() ? self::events($response->json('events') ?? []) : [];
    }

    /** The plain-words label of a Brevo event name. */
    public static function label(string $event): string
    {
        return self::EVENT_LABELS[$event] ?? ucfirst($event);
    }

    private static function build(): array
    {
        $out = [
            'checked_at' => now()->format('Y-m-d H:i'),
            'reachable'  => false,
            'key_ok'     => false,
            'error'      => null,
            'account'    => null,
            'domain'     => null,
            'sender'     => null,
            'events'     => [],
            'emails'     => [],
            'problems'   => [],
        ];

        $account = self::get('/account');
        if ($account === null) {
            $out['error'] = 'Could not reach Brevo from the server.';
            $out['problems'][] = ['level' => 'warning', 'kind' => 'reach', 'text' => 'Could not reach Brevo from the server to check the account. Try again in a minute.'];

            return $out;
        }
        $out['reachable'] = true;

        if (in_array($account->status(), [401, 403], true)) {
            $message = (string) ($account->json('message') ?: 'Key not accepted');
            $out['error'] = $message;
            $out['problems'][] = ['level' => 'error', 'kind' => 'key', 'text' => "Brevo does not accept the API key on the server ({$message}). Every email fails. Create a new key in Brevo (SMTP & API > API keys), put it in BREVO_API_KEY on the server, and if Brevo limits keys to authorised IPs, add the server's IP there."];

            return $out;
        }
        if (! $account->successful()) {
            $out['error'] = 'Brevo answered '.$account->status().'.';
            $out['problems'][] = ['level' => 'warning', 'kind' => 'reach', 'text' => 'Brevo answered '.$account->status().' when checking the account. Try again in a minute.'];

            return $out;
        }

        $out['key_ok'] = true;
        $out['account'] = MailHealth::mask((string) ($account->json('companyName') ?: $account->json('email') ?: ''));

        $out['domain'] = self::domain(MailHealth::fromDomain());
        $out['sender'] = self::sender(MailHealth::fromAddress(), $out['domain']);

        $events = self::get('/smtp/statistics/events', ['limit' => 15, 'sort' => 'desc', 'days' => 30]);
        $out['events'] = $events?->successful() ? self::events($events->json('events') ?? []) : [];
        $out['emails'] = self::latestPerEmail($out['events']);

        $out['problems'] = self::problemsFrom($out);

        return $out;
    }

    /** The From domain as Brevo sees it, with each DNS record and whether Brevo found it. */
    private static function domain(string $name): ?array
    {
        if ($name === '') {
            return null;
        }

        $response = self::get('/senders/domains/'.rawurlencode($name));
        if ($response === null || (! $response->successful() && $response->status() !== 404)) {
            return null;
        }

        $domain = ['name' => $name, 'exists' => false, 'authenticated' => false, 'verified' => false, 'records' => []];
        if ($response->status() === 404) {
            return $domain;
        }

        $domain['exists'] = true;
        $domain['authenticated'] = (bool) $response->json('authenticated');
        $domain['verified'] = (bool) $response->json('verified');

        foreach ((array) $response->json('dns_records') as $recordName => $record) {
            if (! is_array($record) || blank($record['value'] ?? null)) {
                continue;
            }
            $domain['records'][] = [
                'name'  => self::recordName((string) $recordName),
                'type'  => strtoupper((string) ($record['type'] ?? 'TXT')),
                'host'  => (string) ($record['host_name'] ?? '@'),
                'value' => (string) $record['value'],
                'ok'    => (bool) ($record['status'] ?? false),
            ];
        }

        return $domain;
    }

    /** Whether the From address can send: a verified sender, or any address on an authenticated domain. */
    private static function sender(string $email, ?array $domain): ?array
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $viaDomain = (bool) ($domain['authenticated'] ?? false);
        $verified = $viaDomain;

        if (! $viaDomain) {
            $response = self::get('/senders');
            foreach ((array) ($response?->successful() ? $response->json('senders') : []) as $sender) {
                if (strcasecmp((string) ($sender['email'] ?? ''), $email) === 0 && ($sender['active'] ?? false)) {
                    $verified = true;
                }
            }
        }

        return ['email' => $email, 'verified' => $verified, 'via_domain' => $viaDomain];
    }

    /** @return list<array{level: 'error'|'warning', kind: string, text: string}> */
    private static function problemsFrom(array $report): array
    {
        $out = [];
        $domain = $report['domain'];
        $sender = $report['sender'];

        if ($sender !== null && ! $sender['verified']) {
            $out[] = ['level' => 'error', 'kind' => 'sender', 'text' => "Brevo is rejecting emails from {$sender['email']}: the address is not a verified sender and its domain is not authenticated."];
        }

        if ($domain !== null && ! $domain['exists']) {
            $out[] = ['level' => 'warning', 'kind' => 'domain', 'text' => "The domain {$domain['name']} is not added in Brevo, so emails land in spam or are rejected. Add it in Brevo (Senders, Domains & Dedicated IPs > Domains), then add the DNS records it shows at your domain host."];
        } elseif ($domain !== null && ! $domain['authenticated']) {
            $missing = collect($domain['records'])->where('ok', false)->pluck('name')->implode(', ');
            $out[] = ['level' => 'warning', 'kind' => 'domain', 'text' => "The domain {$domain['name']} is not authenticated in Brevo yet".($missing !== '' ? " (missing: {$missing})" : '').', so emails land in spam or are rejected.'];
        }

        $bad = collect($report['emails'])->where('bad', true)->first();
        if ($bad !== null) {
            $out[] = ['level' => 'warning', 'kind' => 'event', 'text' => "Latest problem Brevo logged ({$bad['time']}): {$bad['label']} for {$bad['email']}".($bad['reason'] !== '' ? ": {$bad['reason']}" : '').'.'];
        }

        return $out;
    }

    /**
     * One row per email: Brevo logs "Sent to Brevo" and then the outcome as two
     * events with the same message id; keep only the newest (events are newest first).
     */
    private static function latestPerEmail(array $events): array
    {
        return collect($events)
            ->unique(fn ($e) => $e['message_id'] !== '' ? $e['message_id'] : $e['time'].$e['email'].$e['subject'])
            ->values()
            ->all();
    }

    /** @return list<array{time: string, event: string, label: string, bad: bool, email: string, subject: string, reason: string, message_id: string}> */
    private static function events(array $events): array
    {
        return collect($events)
            ->filter(fn ($e) => is_array($e) && isset($e['event']))
            // "Sent to Brevo" and opens add noise to a short list; keep what tells delivery.
            ->reject(fn ($e) => in_array($e['event'], ['opened', 'uniqueOpened', 'loadedByProxy', 'clicks'], true))
            ->map(fn ($e) => [
                'time'    => self::time((string) ($e['date'] ?? '')),
                'event'   => (string) $e['event'],
                'label'   => self::label((string) $e['event']),
                'bad'     => in_array($e['event'], self::BAD_EVENTS, true),
                'email'   => MailHealth::mask((string) ($e['email'] ?? '')),
                'subject' => mb_strimwidth((string) ($e['subject'] ?? ''), 0, 80, '...'),
                'reason'  => MailHealth::mask(mb_strimwidth(trim((string) ($e['reason'] ?? '')), 0, 200, '...')),
                'message_id' => (string) ($e['messageId'] ?? ''),
            ])
            ->values()
            ->all();
    }

    private static function time(string $date): string
    {
        try {
            return \Carbon\Carbon::parse($date)->setTimezone(config('app.timezone'))->format('Y-m-d H:i');
        } catch (\Throwable) {
            return $date;
        }
    }

    private static function recordName(string $key): string
    {
        return match (true) {
            str_contains($key, 'brevo_code') => 'Brevo code',
            str_contains($key, 'dkim')       => 'DKIM'.(preg_match('/(\d)/', $key, $m) ? " {$m[1]}" : ''),
            str_contains($key, 'dmarc')      => 'DMARC',
            str_contains($key, 'spf')        => 'SPF',
            default                          => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    /** One GET to the Brevo API; null when Brevo cannot be reached. */
    private static function get(string $path, array $query = []): ?Response
    {
        try {
            return Http::withHeaders(['api-key' => (string) config('services.brevo.key')])
                ->acceptJson()
                ->timeout(6)
                ->get(self::API.$path, $query);
        } catch (\Throwable) {
            return null;
        }
    }
}
