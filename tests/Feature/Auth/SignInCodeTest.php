<?php

namespace Tests\Feature\Auth;

use App\Mail\SignInCodeMail;
use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\SignInCodes;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Email sign-in codes (Settings > Security, App\Services\SignInCodes).
 */
class SignInCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // A mailer that delivers (phpunit.xml uses "array", which cannot); Mail::fake() keeps every email.
        config(['mail.default' => 'smtp']);
        Mail::fake();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function turnOn(array $settings = []): void
    {
        app(SettingsService::class)->setMany(['otp_enabled' => true] + $settings);
    }

    private function userWithRole(string $role = 'nurse', array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function signIn(User $user, array $extra = []): TestResponse
    {
        return $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'password'] + $extra);
    }

    private function sentCodes(): int
    {
        return Mail::sent(SignInCodeMail::class)->count();
    }

    private function lastCode(): string
    {
        return Mail::sent(SignInCodeMail::class)->last()->code;
    }

    private function wrongCode(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }

    private function verify(string $code, array $extra = []): TestResponse
    {
        return $this->from(route('login.code'))->post(route('login.code.verify'), ['code' => $code] + $extra);
    }

    private function assertCodeNeverStored(string $code): void
    {
        foreach (AuditLog::all() as $log) {
            $this->assertStringNotContainsString($code, (string) $log->description);
            $this->assertStringNotContainsString($code, json_encode([$log->old_values, $log->new_values]));
        }
    }

    // ─── Sign-in ─────────────────────────────────────────────────────────────

    public function test_password_signs_in_as_before_when_codes_are_off(): void
    {
        $user = $this->userWithRole();

        $this->signIn($user)->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
    }

    public function test_correct_password_sends_a_code_and_does_not_sign_in_yet(): void
    {
        $this->turnOn();
        $user = $this->userWithRole('nurse', ['email' => 'pat.cruz@school.edu']);

        $this->signIn($user)->assertRedirect(route('login.code'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
        $this->assertSame(1, $this->sentCodes());
        Mail::assertSent(SignInCodeMail::class, fn (SignInCodeMail $m) => $m->hasTo('pat.cruz@school.edu')
            && preg_match('/^\d{6}$/', $m->code) === 1
            && $m->minutes === 10);

        // Only a hash of the code is kept.
        $pending = session('auth.sign_in_code');
        $this->assertNotSame($this->lastCode(), $pending['hash']);
        $this->assertTrue(Hash::check($this->lastCode(), $pending['hash']));

        // The page works without JavaScript: one real "code" input plus the six boxes for the script.
        $this->get(route('login.code'))
            ->assertOk()
            ->assertSee('Check your email')
            ->assertSee('We sent a 6-digit code to')
            ->assertSee('p***@school.edu')
            ->assertSee('name="code"', false)
            ->assertSee('autocomplete="one-time-code"', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('Remember this device for 30 days')
            ->assertSee('Resend code')
            ->assertSee('Use a different account');

        $this->assertDatabaseHas('audit_logs', ['action' => 'code_sent', 'module' => 'auth', 'user_id' => $user->id]);
        $this->assertCodeNeverStored($this->lastCode());
    }

    public function test_correct_code_signs_in(): void
    {
        $this->turnOn();
        $user = $this->userWithRole();
        $this->signIn($user);
        $code = $this->lastCode();

        // Spaces pasted with the code are ignored.
        $this->verify(substr($code, 0, 3).' '.substr($code, 3))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertNull(session('auth.sign_in_code'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'code_verified', 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'logged_in', 'user_id' => $user->id]);
        $this->assertCodeNeverStored($code);

        // The code page is for guests only.
        $this->get(route('login.code'))->assertRedirect();
    }

    public function test_keep_me_signed_in_is_kept_through_the_code(): void
    {
        $this->turnOn();
        $user = $this->userWithRole();
        $this->signIn($user, ['remember' => 'on']);

        $response = $this->verify($this->lastCode());

        $this->assertAuthenticatedAs($user);
        $response->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_forced_password_change_comes_after_the_code(): void
    {
        $this->turnOn();
        $user = $this->userWithRole('nurse', ['must_change_password' => true]);

        $this->signIn($user)->assertRedirect(route('login.code'));
        $this->assertGuest();

        $this->verify($this->lastCode());
        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('profile.edit'));
    }

    public function test_five_wrong_codes_lock_the_code_until_a_new_one_is_sent(): void
    {
        $this->turnOn();
        $user = $this->userWithRole();
        $this->signIn($user);
        $code = $this->lastCode();

        for ($i = 1; $i <= 4; $i++) {
            $this->verify($this->wrongCode($code))
                ->assertRedirect(route('login.code'))
                ->assertSessionHasErrors(['code' => 'That code is not right. Check the email and try again. '.(5 - $i).' '.(5 - $i === 1 ? 'try' : 'tries').' left.']);
        }

        $this->verify($this->wrongCode($code))
            ->assertSessionHasErrors(['code' => 'Too many wrong codes. Send a new code to try again.']);

        // Even the right code no longer works.
        $this->verify($code)->assertSessionHasErrors(['code' => 'Too many wrong codes. Send a new code to try again.']);
        $this->assertGuest();

        $this->assertSame(4, AuditLog::where('action', 'code_failed')->where('user_id', $user->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'code_locked')->where('user_id', $user->id)->count());
        $this->assertCodeNeverStored($code);

        // A new code works.
        $this->travel(61)->seconds();
        $this->post(route('login.code.resend'))->assertSessionHasNoErrors();
        $this->verify($this->lastCode())->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_input_that_is_not_six_digits_is_not_counted_as_a_try(): void
    {
        $this->turnOn();
        $this->signIn($this->userWithRole());

        $this->verify('12345')->assertSessionHasErrors(['code' => 'Enter the 6-digit code from the email.']);

        $this->assertSame(5, app(SignInCodes::class)->triesLeft(session('auth.sign_in_code')));
    }

    public function test_expired_code_sends_the_person_back_to_sign_in(): void
    {
        $this->turnOn();
        $user = $this->userWithRole();
        $this->signIn($user);
        $code = $this->lastCode();

        $this->travel(11)->minutes();

        $this->verify($code)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Your sign-in code expired. Sign in again to get a new one.']);

        $this->assertGuest();
        $this->assertNull(session('auth.sign_in_code'));
        $this->get(route('login.code'))->assertRedirect(route('login'));
    }

    public function test_code_page_without_a_pending_sign_in_goes_to_sign_in(): void
    {
        $this->get(route('login.code'))->assertRedirect(route('login'));
        $this->post(route('login.code.verify'), ['code' => '123456'])->assertRedirect(route('login'));
        $this->post(route('login.code.resend'))->assertRedirect(route('login'));
        Mail::assertNothingSent();
    }

    public function test_resend_waits_a_minute_and_is_limited_to_five_an_hour(): void
    {
        $this->turnOn();
        $this->signIn($this->userWithRole());
        $first = $this->lastCode();

        $this->post(route('login.code.resend'))
            ->assertRedirect(route('login.code'))
            ->assertSessionHasErrors('code');
        $this->assertSame(1, $this->sentCodes());

        for ($i = 1; $i <= 5; $i++) {
            $this->travel(61)->seconds();
            $this->post(route('login.code.resend'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', 'We sent a new code. The earlier code no longer works.');
        }
        $this->assertSame(6, $this->sentCodes());

        $this->travel(61)->seconds();
        $this->post(route('login.code.resend'))
            ->assertSessionHasErrors(['code' => 'You asked for a new code too many times. Try again in 55 minutes.']);
        $this->assertSame(6, $this->sentCodes());

        // Only the newest code works.
        if ($first !== $this->lastCode()) {
            $this->verify($first)->assertSessionHasErrors('code');
        }
        $this->verify($this->lastCode())->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_code_email_failure_keeps_the_person_signed_out(): void
    {
        $this->turnOn();
        $user = $this->userWithRole();
        Log::spy();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Brevo rejected the request'));

        $this->signIn($user)
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => "We couldn't send your code. Try again, or ask an administrator."]);

        $this->assertGuest();
        $this->assertNull(session('auth.sign_in_code'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'code_not_sent', 'user_id' => $user->id]);
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => str_contains($message, 'Sign-in code email could not be sent')
            && ($context['error'] ?? null) === 'Brevo rejected the request')->once();
    }

    public function test_deactivated_account_gets_no_code(): void
    {
        $this->turnOn();
        $user = $this->userWithRole('nurse', ['is_active' => false]);

        $this->signIn($user)->assertSessionHasErrors('email');

        $this->assertGuest();
        Mail::assertNothingSent();
    }

    public function test_wrong_password_gets_no_code(): void
    {
        $this->turnOn();
        $user = $this->userWithRole();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        Mail::assertNothingSent();
    }

    public function test_administrators_only_option(): void
    {
        $this->turnOn(['otp_applies_to' => 'admins']);
        $nurse = $this->userWithRole('nurse');
        $admin = $this->userWithRole('administrator');

        $this->signIn($nurse)->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($nurse);
        Mail::assertNothingSent();

        $this->post('/logout');

        $this->signIn($admin)->assertRedirect(route('login.code'));
        $this->assertGuest();
        $this->assertSame(1, $this->sentCodes());
    }

    public function test_codes_are_skipped_when_email_stops_working(): void
    {
        $this->turnOn();
        config(['mail.default' => 'log']);
        $user = $this->userWithRole();

        $this->signIn($user)->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
    }

    // ─── Remembered devices ──────────────────────────────────────────────────

    public function test_remembered_device_skips_the_code_until_it_expires(): void
    {
        $this->turnOn(['otp_remember_days' => 30]);
        $user = $this->userWithRole();
        $this->signIn($user);

        $response = $this->verify($this->lastCode(), ['remember_device' => '1']);
        $this->assertAuthenticatedAs($user);

        $name = SignInCodes::deviceCookieName($user);
        $cookie = $response->getCookie($name);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());

        // Only a hash of the cookie token is stored.
        $device = TrustedDevice::where('user_id', $user->id)->sole();
        $this->assertSame(hash('sha256', $cookie->getValue()), $device->token_hash);
        $this->assertTrue($device->expires_at->between(now()->addDays(29), now()->addDays(31)));

        $this->post('/logout');
        $this->assertGuest();

        // Next time on this browser: straight in, no code.
        $this->withCookie($name, $cookie->getValue());
        $this->signIn($user)->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, $this->sentCodes());
        $this->assertNotNull($device->fresh()->last_used_at);

        $this->post('/logout');

        // After 30 days the code is asked for again.
        $this->travel(31)->days();
        $this->signIn($user)->assertRedirect(route('login.code'));
        $this->assertGuest();
        $this->assertSame(2, $this->sentCodes());
        $this->assertSame(0, TrustedDevice::count());
    }

    public function test_device_is_not_remembered_when_the_box_is_not_ticked_or_days_is_zero(): void
    {
        $this->turnOn();
        $user = $this->userWithRole();
        $this->signIn($user);
        $this->verify($this->lastCode());
        $this->assertSame(0, TrustedDevice::count());

        $this->post('/logout');

        app(SettingsService::class)->setMany(['otp_remember_days' => 0]);
        $this->signIn($user);
        $this->get(route('login.code'))->assertOk()->assertDontSee('Remember this device');
        $this->verify($this->lastCode(), ['remember_device' => '1']);
        $this->assertSame(0, TrustedDevice::count());
    }

    public function test_password_change_forgets_remembered_devices(): void
    {
        $user = $this->userWithRole();
        $device = fn () => TrustedDevice::create([
            'user_id' => $user->id, 'token_hash' => hash('sha256', \Illuminate\Support\Str::random(64)), 'expires_at' => now()->addDays(30),
        ]);
        $device();

        $user->update(['name' => 'New Name']);
        $this->assertSame(1, $user->trustedDevices()->count());

        $user->update(['password' => Hash::make('N3w!SecurePass')]);
        $this->assertSame(0, $user->trustedDevices()->count());

        // Also through the profile page.
        $device();
        $this->actingAs($user)->put(route('profile.password'), [
            'current_password'      => 'N3w!SecurePass',
            'password'              => 'An0ther!SecurePass',
            'password_confirmation' => 'An0ther!SecurePass',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $user->trustedDevices()->count());
    }

    public function test_administrator_can_forget_a_users_remembered_devices(): void
    {
        $this->turnOn();
        $admin = $this->userWithRole('administrator');
        $nurse = $this->userWithRole('nurse');
        TrustedDevice::create(['user_id' => $nurse->id, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDays(30), 'last_used_at' => now()]);
        TrustedDevice::create(['user_id' => $nurse->id, 'token_hash' => str_repeat('b', 64), 'expires_at' => now()->addDays(30)]);

        $this->actingAs($admin)->get(route('admin.users.show', $nurse))
            ->assertOk()
            ->assertSee('Remembered devices')
            ->assertSee('2 devices')
            ->assertSee('Forget remembered devices');

        // Viewing users is not enough.
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo('view-users');
        $this->actingAs($viewer)->post(route('admin.users.forget-devices', $nurse))->assertForbidden();
        $this->assertSame(2, $nurse->trustedDevices()->count());

        $this->actingAs($admin)->from(route('admin.users.show', $nurse))
            ->post(route('admin.users.forget-devices', $nurse))
            ->assertRedirect(route('admin.users.show', $nurse))
            ->assertSessionHas('success');

        $this->assertSame(0, $nurse->trustedDevices()->count());
        $this->assertDatabaseHas('audit_logs', ['module' => 'users', 'user_id' => $admin->id, 'description' => "Forgot remembered devices for: {$nurse->name} ({$nurse->email}), 2 removed"]);
    }

    // ─── Settings and the emergency switch ───────────────────────────────────

    public function test_codes_cannot_be_turned_on_while_email_is_not_set_up(): void
    {
        $admin = $this->userWithRole('administrator');
        $form = ['otp_enabled' => '1', 'otp_applies_to' => 'everyone', 'otp_remember_days' => '30',
            'session_idle_minutes' => '480', 'session_remember_days' => '30'];

        foreach ([['mail.default' => 'log'], ['mail.default' => 'array'], ['mail.default' => 'brevo', 'services.brevo.key' => null]] as $mail) {
            config($mail);

            $this->actingAs($admin)->get(route('admin.settings.edit', 'security'))
                ->assertOk()
                ->assertSee('Email is not set up, so sign-in codes cannot be turned on')
                ->assertSee('php artisan auth:otp-off');

            $this->actingAs($admin)->from(route('admin.settings.edit', 'security'))
                ->put(route('admin.settings.update', 'security'), $form)
                ->assertSessionHasErrors('otp_enabled');

            $this->assertFalse(settings('otp_enabled'));
        }

        // Saving other security settings with codes off still works.
        $this->actingAs($admin)->put(route('admin.settings.update', 'security'), ['otp_enabled' => '0'] + $form)
            ->assertSessionHasNoErrors();
        $this->assertFalse(settings('otp_enabled'));

        config(['mail.default' => 'brevo', 'services.brevo.key' => 'xkeysib-test']);
        $this->actingAs($admin)->put(route('admin.settings.update', 'security'), ['otp_applies_to' => 'admins', 'otp_remember_days' => '7'] + $form)
            ->assertSessionHasNoErrors();

        $this->assertTrue(settings('otp_enabled'));
        $this->assertSame('admins', settings('otp_applies_to'));
        $this->assertSame(7, settings('otp_remember_days'));
        $this->actingAs($admin)->get(route('admin.settings.edit', 'security'))->assertSee('Sign-in codes are on');
    }

    public function test_emergency_command_turns_codes_off(): void
    {
        $this->turnOn();
        $user = $this->userWithRole();

        $this->artisan('auth:otp-off')
            ->expectsOutputToContain('Sign-in codes are now off')
            ->assertSuccessful();

        $this->assertFalse(settings('otp_enabled'));
        $this->assertDatabaseHas('audit_logs', ['module' => 'settings', 'description' => 'Turned off sign-in codes by email from the server (php artisan auth:otp-off)']);

        $this->signIn($user)->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();

        $this->artisan('auth:otp-off')->expectsOutputToContain('already off')->assertSuccessful();
    }

    // ─── The email ───────────────────────────────────────────────────────────

    public function test_code_email_is_branded(): void
    {
        app(SettingsService::class)->setMany(['clinic_name' => 'Sunrise Clinic', 'brand_primary_color' => '#0F766E']);

        $mail = new SignInCodeMail('482913', 10);

        $mail->assertHasSubject('Your sign-in code');
        $mail->assertSeeInText("Your sign-in code is 482913. It expires in 10 minutes. If this wasn't you, change your password.");
        $mail->assertSeeInHtml('482913');
        $mail->assertSeeInHtml('Sunrise Clinic');
        $mail->assertSeeInHtml('#0F766E');
    }
}
