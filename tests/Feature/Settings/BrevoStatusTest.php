<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Support\BrevoStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\FakesBrevo;
use Tests\TestCase;

/**
 * App\Support\BrevoStatus: what Brevo itself says, on Settings > Email and in
 * `php artisan mail:check`. "Key set" used to read as "email works" while Brevo
 * accepted emails from an unauthenticated domain and they never arrived.
 */
class BrevoStatusTest extends TestCase
{
    use FakesBrevo;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['is_active' => true, 'email' => 'nurse.admin@school.test']);
        $this->admin->assignRole('administrator');

        config([
            'app.url'            => 'https://schoolcare.online',
            'mail.default'       => 'brevo',
            'mail.from.address'  => 'no-reply@schoolcare.online',
            'services.brevo.key' => 'xkeysib-test',
        ]);
    }

    public function test_not_checked_when_brevo_is_not_the_mailer(): void
    {
        Http::preventStrayRequests();
        config(['mail.default' => 'log']);

        $this->assertNull(BrevoStatus::report());
    }

    public function test_a_healthy_account_has_no_problems(): void
    {
        $this->fakeBrevo();

        $report = BrevoStatus::report();

        $this->assertTrue($report['key_ok']);
        $this->assertTrue($report['domain']['authenticated']);
        $this->assertTrue($report['sender']['verified']);
        $this->assertSame([], $report['problems']);
        Http::assertSent(fn ($r) => $r->hasHeader('api-key', 'xkeysib-test'));
    }

    public function test_an_unauthenticated_domain_lists_the_missing_dns_records(): void
    {
        $this->fakeBrevo([
            'domain' => Http::response([
                'domain' => 'schoolcare.online', 'verified' => false, 'authenticated' => false,
                'dns_records' => [
                    'dkim1Record' => ['type' => 'CNAME', 'value' => 'b1.schoolcare-online.dkim.brevo.com', 'host_name' => 'brevo1._domainkey', 'status' => false],
                    'brevo_code'  => ['type' => 'TXT', 'value' => 'brevo-code:9f8e7d', 'host_name' => '@', 'status' => true],
                ],
            ]),
            'senders' => Http::response(['senders' => [['email' => 'no-reply@schoolcare.online', 'active' => true]]]),
        ]);

        $report = BrevoStatus::report();

        $this->assertFalse($report['domain']['authenticated']);
        $this->assertTrue($report['sender']['verified']);
        $this->assertSame('DKIM 1', $report['domain']['records'][0]['name']);
        $this->assertStringContainsString('not authenticated in Brevo yet (missing: DKIM 1)', $report['problems'][0]['text']);
        $this->assertSame('warning', $report['problems'][0]['level']);

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'email'))
            ->assertOk()
            ->assertSee('Email is set up, but may not reach inboxes')
            ->assertSee('DNS records to add at your domain host')
            ->assertSee('b1.schoolcare-online.dkim.brevo.com')
            ->assertSee('brevo1._domainkey');

        $this->artisan('mail:check')
            ->expectsOutputToContain('Domain:        schoolcare.online: NOT AUTHENTICATED')
            ->expectsOutputToContain('b1.schoolcare-online.dkim.brevo.com')
            ->assertExitCode(0);
    }

    public function test_a_domain_missing_in_brevo_and_an_unverified_sender_is_an_error(): void
    {
        $this->fakeBrevo(['domain' => Http::response(['code' => 'document_not_found'], 404)]);

        $report = BrevoStatus::report();

        $this->assertFalse($report['domain']['exists']);
        $this->assertFalse($report['sender']['verified']);
        $this->assertSame('error', $report['problems'][0]['level']);

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'email'))
            ->assertOk()
            ->assertSee('Email cannot be sent')
            ->assertSee('schoolcare.online not added in Brevo');

        $this->artisan('mail:check')
            ->expectsOutputToContain('NOT ADDED in Brevo')
            ->expectsOutputToContain('NOT VERIFIED')
            ->assertExitCode(1);
    }

    public function test_a_rejected_key_says_every_email_fails(): void
    {
        $this->fakeBrevo(['account' => Http::response(['code' => 'unauthorized', 'message' => 'Key not found'], 401)]);

        $report = BrevoStatus::report();

        $this->assertFalse($report['key_ok']);
        $this->assertStringContainsString('does not accept the API key on the server (Key not found)', $report['problems'][0]['text']);

        $this->artisan('mail:check')
            ->expectsOutputToContain('API key:       NOT ACCEPTED (Key not found)')
            ->assertExitCode(1);
    }

    public function test_recent_blocked_emails_show_brevos_reason_with_addresses_masked(): void
    {
        $this->fakeBrevo(['events' => Http::response(['events' => [
            ['email' => 'parent.juan@gmail.com', 'date' => '2026-10-03T09:00:00+00:00', 'subject' => 'Appointment approved', 'messageId' => '<a@relay>', 'event' => 'blocked', 'reason' => 'sender domain not authenticated'],
            ['email' => 'parent.juan@gmail.com', 'date' => '2026-10-03T08:59:59+00:00', 'subject' => 'Appointment approved', 'messageId' => '<a@relay>', 'event' => 'requests'],
            ['email' => 'parent.juan@gmail.com', 'date' => '2026-10-03T08:00:00+00:00', 'subject' => 'Hi', 'messageId' => '<b@relay>', 'event' => 'opened'],
        ]])]);

        $report = BrevoStatus::report();

        $this->assertCount(2, $report['events']); // opens are left out
        $this->assertSame('Blocked by Brevo', $report['events'][0]['label']);
        $this->assertSame('***@gmail.com', $report['events'][0]['email']);
        $this->assertStringContainsString('Blocked by Brevo for ***@gmail.com: sender domain not authenticated', $report['problems'][0]['text']);
        $this->assertStringNotContainsString('parent.juan', json_encode($report));

        $this->artisan('mail:check')
            ->expectsOutputToContain('Blocked by Brevo: ***@gmail.com "Appointment approved" (sender domain not authenticated)')
            ->doesntExpectOutputToContain('parent.juan')
            ->assertExitCode(0);
    }

    public function test_the_test_email_is_followed_through_brevo(): void
    {
        $this->fakeBrevo(['events' => function ($request) {
            return str_contains($request->url(), 'messageId')
                ? Http::response(['events' => [
                    ['email' => 'nurse.admin@school.test', 'date' => '2026-10-03T09:00:05+00:00', 'subject' => 'test', 'messageId' => '<t1@relay>', 'event' => 'delivered'],
                    ['email' => 'nurse.admin@school.test', 'date' => '2026-10-03T09:00:00+00:00', 'subject' => 'test', 'messageId' => '<t1@relay>', 'event' => 'requests'],
                ]])
                : Http::response(['events' => []]);
        }]);

        Mail::shouldReceive('raw')->once()->andReturn(new class
        {
            public function getMessageId(): string
            {
                return '<t1@relay>';
            }
        });

        $this->actingAs($this->admin)->post(route('admin.settings.test-email'))
            ->assertRedirect(route('admin.settings.edit', 'email'))
            ->assertSessionHas('mail_test.id', '<t1@relay>');

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'email'))
            ->assertOk()
            ->assertSee('Test email to nurse.admin@school.test')
            ->assertSee('Delivered')
            ->assertSee('look in Spam or Promotions');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'messageId=%3Ct1%40relay%3E'));
    }

    public function test_the_test_email_can_go_to_a_typed_address(): void
    {
        $this->fakeBrevo();
        Mail::shouldReceive('raw')->once()->andReturn(new class
        {
            public function getMessageId(): string
            {
                return '<t3@relay>';
            }
        });

        $this->actingAs($this->admin)->post(route('admin.settings.test-email'), ['test_to' => 'someone@gmail.com'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Test email sent to someone@gmail.com.'))
            ->assertSessionHas('mail_test.to', 'someone@gmail.com');

        $this->actingAs($this->admin)->post(route('admin.settings.test-email'), ['test_to' => 'not-an-email'])
            ->assertSessionHasErrors('test_to');
    }

    public function test_send_test_prints_brevos_delivery_result(): void
    {
        $this->fakeBrevo(['events' => function ($request) {
            return str_contains($request->url(), 'messageId')
                ? Http::response(['events' => [
                    ['email' => 'nurse.admin@school.test', 'date' => '2026-10-03T09:00:05+00:00', 'messageId' => '<t2@relay>', 'event' => 'hardBounces', 'reason' => '550 5.1.1 user unknown'],
                ]])
                : Http::response(['events' => []]);
        }]);

        Mail::shouldReceive('raw')->once()->andReturn(new class
        {
            public function getMessageId(): string
            {
                return '<t2@relay>';
            }
        });

        $this->artisan('mail:check --send-test')
            ->expectsOutputToContain('accepted by brevo')
            ->expectsOutputToContain('Hard bounce (address does not exist): 550 5.1.1 user unknown')
            ->assertExitCode(0);
    }
}
