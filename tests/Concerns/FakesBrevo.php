<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Http;

/**
 * Fakes the Brevo API that App\Support\BrevoStatus calls, so no test reaches
 * api.brevo.com. Defaults to a healthy account: key accepted, schoolcare.online
 * authenticated, no events. Pass overrides per endpoint.
 */
trait FakesBrevo
{
    /**
     * @param  array<string, mixed>  $overrides  keys: account, domain, senders, events (a response or a closure)
     */
    protected function fakeBrevo(array $overrides = []): void
    {
        $responses = array_merge([
            'account' => Http::response(['email' => 'owner@schoolcare.online', 'companyName' => 'SchoolCare']),
            'domain'  => Http::response([
                'domain' => 'schoolcare.online', 'verified' => true, 'authenticated' => true,
                'dns_records' => [
                    'dkim_record' => ['type' => 'TXT', 'value' => 'k=rsa;p=ABC', 'host_name' => 'mail._domainkey', 'status' => true],
                    'brevo_code'  => ['type' => 'TXT', 'value' => 'brevo-code:123', 'host_name' => '@', 'status' => true],
                ],
            ]),
            'senders' => Http::response(['senders' => []]),
            'events'  => Http::response(['events' => []]),
        ], $overrides);

        Http::preventStrayRequests();
        Http::fake([
            'api.brevo.com/v3/account*'                => $responses['account'],
            'api.brevo.com/v3/senders/domains/*'       => $responses['domain'],
            'api.brevo.com/v3/senders*'                => $responses['senders'],
            'api.brevo.com/v3/smtp/statistics/events*' => $responses['events'],
        ]);
    }
}
