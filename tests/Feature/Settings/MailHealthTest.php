<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Support\MailHealth;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * App\Support\MailHealth, `php artisan mail:check` and the Settings > Email card:
 * a Brevo mailer without its key, a From address on another domain, and the
 * latest failures read from the log with addresses masked.
 */
class MailHealthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('administrator');

        // A storage folder of our own, so the real log is never read or written.
        $this->storage = sys_get_temp_dir().'/mail-health-'.uniqid();
        File::ensureDirectoryExists($this->storage.'/logs');
        $this->app->useStoragePath($this->storage);

        config([
            'app.url'             => 'https://schoolcare.online',
            'mail.default'        => 'brevo',
            'mail.from.address'   => 'no-reply@schoolcare.online',
            'services.brevo.key'  => 'xkeysib-test',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    public function test_brevo_with_its_key_on_the_site_domain_is_ready(): void
    {
        $this->assertSame([], MailHealth::problems());
        $this->assertTrue(MailHealth::ready());

        $this->artisan('mail:check')
            ->expectsOutputToContain('Brevo API key: set')
            ->expectsOutputToContain('The set-up looks right.')
            ->assertExitCode(0);

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'email'))
            ->assertOk()
            ->assertSee('Email is set up')
            ->assertDontSee('Email cannot be sent');
    }

    public function test_brevo_without_a_key_is_reported_as_not_working(): void
    {
        config(['services.brevo.key' => '']);

        $this->assertFalse(MailHealth::ready());

        $this->artisan('mail:check')
            ->expectsOutputToContain('Brevo API key: MISSING')
            ->expectsOutputToContain('BREVO_API_KEY is empty')
            ->assertExitCode(1);

        // Used to say "Email is set up" while every email failed.
        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'email'))
            ->assertOk()
            ->assertSee('Email cannot be sent')
            ->assertSee('BREVO_API_KEY is empty')
            ->assertDontSee('Email is set up');
    }

    public function test_from_address_on_another_domain_is_a_warning(): void
    {
        config(['mail.from.address' => 'no-reply@iccbiclinic.site']);

        $problems = MailHealth::problems();
        $this->assertCount(1, $problems);
        $this->assertSame('warning', $problems[0]['level']);
        $this->assertStringContainsString('@iccbiclinic.site', $problems[0]['text']);
        $this->assertStringContainsString('schoolcare.online', $problems[0]['text']);
        $this->assertTrue(MailHealth::ready());

        // A subdomain of the site is the same sender domain.
        config(['mail.from.address' => 'no-reply@mail.schoolcare.online']);
        $this->assertSame([], MailHealth::problems());
    }

    public function test_log_mailer_is_not_ready(): void
    {
        config(['mail.default' => 'log']);

        $this->assertFalse(MailHealth::ready());
        $this->artisan('mail:check')->expectsOutputToContain('MAIL_MAILER is log')->assertExitCode(1);
    }

    public function test_latest_failures_come_from_the_log_newest_first_with_addresses_masked(): void
    {
        File::put($this->storage.'/logs/laravel.log', implode("\n", [
            '[2026-10-01 08:00:00] production.WARNING: Invitation email could not be sent {"user_id":3,"error":"Unable to send an email: Key not found (code 401)."} ',
            '[2026-10-01 08:05:00] production.INFO: Something else entirely {"to":"nurse@school.edu"} ',
            '[2026-10-01 09:00:00] production.WARNING: Appointment notification [approved] failed {"appointment_id":7,"error":"Sender juan.dela.cruz@iccbiclinic.site is not valid"} ',
            '[2026-10-01 09:30:00] production.WARNING: Test email failed {"error":"Unable to send an email: unrecognised IP address 1.2.3.4"} ',
            '',
        ]));

        $failures = MailHealth::recentFailures();

        $this->assertCount(3, $failures);
        $this->assertSame('2026-10-01 09:30:00', $failures[0]['time']);
        $this->assertSame('Test email failed: Unable to send an email: unrecognised IP address 1.2.3.4', $failures[0]['text']);
        $this->assertSame('Appointment notification [approved] failed: Sender ***@iccbiclinic.site is not valid', $failures[1]['text']);
        $this->assertStringContainsString('Key not found (code 401)', $failures[2]['text']);
        $this->assertStringNotContainsString('juan.dela.cruz', json_encode($failures));

        $this->artisan('mail:check')
            ->expectsOutputToContain('Latest email failures (newest first):')
            ->expectsOutputToContain('unrecognised IP address')
            ->doesntExpectOutputToContain('juan.dela.cruz')
            ->assertExitCode(0);

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'email'))
            ->assertOk()
            ->assertSee('Latest email failures')
            ->assertSee('unrecognised IP address')
            ->assertDontSee('juan.dela.cruz');
    }
}
