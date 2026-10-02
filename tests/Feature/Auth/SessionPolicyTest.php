<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnforceIdleTimeout;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\SessionPolicy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Staying signed in (Settings > Security, App\Support\SessionPolicy): the
 * inactivity limit, "Keep me signed in", where an account is signed in, and
 * signing out other devices. The live server signed everyone out after 120
 * minutes (SESSION_LIFETIME) and an open tab stayed signed in for ever.
 */
class SessionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';
    private const SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    private User $nurse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->nurse = User::factory()->create(['is_active' => true, 'password' => Hash::make('Correct-Horse-9!')]);
        $this->nurse->assignRole('nurse');
    }

    private function signedIn(User $user, string $id, string $agent, int $minutesAgo = 5): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '203.0.113.7', 'user_agent' => $agent,
            'payload' => '', 'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
        ]);
    }

    public function test_settings_decide_the_session_and_remember_me_lengths(): void
    {
        // Defaults: 8 hours without activity, "Keep me signed in" for 30 days.
        $this->assertSame(480, config('session.lifetime'));
        $this->assertSame(30 * 1440, config('auth.guards.web.remember'));

        settings()->setMany(['session_idle_minutes' => '60', 'session_remember_days' => '7']);
        SessionPolicy::applyToConfig();

        $this->assertSame(60, config('session.lifetime'));
        $this->assertSame(7 * 1440, config('auth.guards.web.remember'));
        $this->assertSame('1 hour', SessionPolicy::idleLabel());

        // Anything outside the choices falls back to the defaults.
        settings()->setMany(['session_idle_minutes' => '5', 'session_remember_days' => '9999']);
        $this->assertSame(480, SessionPolicy::idleMinutes());
        $this->assertSame(30, SessionPolicy::rememberDays());
    }

    public function test_inactive_user_is_signed_out_with_a_message(): void
    {
        $this->actingAs($this->nurse)
            ->withSession([EnforceIdleTimeout::KEY => now()->subHours(9)->getTimestamp()])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'You were signed out after 8 hours without activity. Please sign in again.');

        $this->assertGuest();
    }

    public function test_recent_activity_keeps_the_user_signed_in(): void
    {
        $this->actingAs($this->nurse)
            ->withSession([EnforceIdleTimeout::KEY => now()->subHours(7)->getTimestamp()])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas(EnforceIdleTimeout::KEY, now()->getTimestamp());

        $this->assertAuthenticatedAs($this->nurse);
    }

    public function test_the_background_keep_alive_is_not_activity(): void
    {
        $last = now()->subHours(3)->getTimestamp();

        $this->actingAs($this->nurse)->withSession([EnforceIdleTimeout::KEY => $last])
            ->getJson(route('session.token'))
            ->assertOk()
            ->assertSessionHas(EnforceIdleTimeout::KEY, $last);

        // Past the limit even the keep-alive is refused, as JSON.
        $this->actingAs($this->nurse)->withSession([EnforceIdleTimeout::KEY => now()->subHours(9)->getTimestamp()])
            ->getJson(route('session.token'))
            ->assertStatus(401)
            ->assertJson(['expired' => true]);
    }

    public function test_a_device_kept_signed_in_is_not_signed_out_for_inactivity(): void
    {
        $this->actingAs($this->nurse)
            ->withSession([EnforceIdleTimeout::KEY => now()->subDays(3)->getTimestamp()])
            ->withCookie(Auth::guard('web')->getRecallerName(), 'remembered')
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_keep_me_signed_in_shows_its_length_and_can_be_turned_off(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Keep me signed in on this device for 30 days');

        settings()->setMany(['session_remember_days' => '0']);
        $this->get(route('login'))->assertOk()->assertDontSee('Keep me signed in');

        // A posted "remember" is ignored while it is off: no remember-me cookie.
        $response = $this->post(route('login'), ['email' => $this->nurse->email, 'password' => 'Correct-Horse-9!', 'remember' => '1']);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($this->nurse);
        $this->assertNull($response->getCookie(Auth::guard('web')->getRecallerName(), false));
    }

    public function test_keep_me_signed_in_sets_the_remember_cookie_for_the_chosen_days(): void
    {
        settings()->setMany(['session_remember_days' => '7']);
        SessionPolicy::applyToConfig();

        $response = $this->post(route('login'), ['email' => $this->nurse->email, 'password' => 'Correct-Horse-9!', 'remember' => '1']);

        $cookie = $response->getCookie(Auth::guard('web')->getRecallerName(), false);
        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta(now()->addDays(7)->getTimestamp(), $cookie->getExpiresTime(), 120);
    }

    public function test_profile_lists_where_the_user_is_signed_in(): void
    {
        config(['session.driver' => 'database']);
        $this->signedIn($this->nurse, str_repeat('a', 40), self::CHROME_WINDOWS, 10);
        $this->signedIn($this->nurse, str_repeat('b', 40), self::SAFARI_IPHONE, 90);
        $this->signedIn($this->nurse, str_repeat('c', 40), self::SAFARI_IPHONE, 60 * 24 * 3); // expired: not listed

        $this->actingAs($this->nurse)->withHeader('User-Agent', self::CHROME_WINDOWS)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee("Where you're signed in")
            ->assertSeeInOrder(['This device', 'Chrome on Windows', 'Safari on iPhone'])
            ->assertSee('203.0.113.7');
    }

    public function test_signing_out_other_devices_needs_the_password_and_keeps_this_one(): void
    {
        config(['session.driver' => 'database']);
        $other = User::factory()->create(['is_active' => true]);
        $this->signedIn($this->nurse, str_repeat('a', 40), self::CHROME_WINDOWS);
        $this->signedIn($this->nurse, str_repeat('b', 40), self::SAFARI_IPHONE);
        $this->signedIn($other, str_repeat('z', 40), self::CHROME_WINDOWS);
        $token = $this->nurse->remember_token;

        $mine = [str_repeat('a', 40), str_repeat('b', 40)];

        $this->actingAs($this->nurse)->from(route('profile.edit'))
            ->delete(route('profile.sessions.destroy'), ['password' => 'wrong'])
            ->assertSessionHasErrorsIn('otherSessions', 'password');
        $this->assertSame(2, DB::table('sessions')->whereIn('id', $mine)->count());
        // That request stored its own session; the next one starts a new one.
        DB::table('sessions')->whereNotIn('id', [...$mine, str_repeat('z', 40)])->delete();

        $this->actingAs($this->nurse)
            ->delete(route('profile.sessions.destroy'), ['password' => 'Correct-Horse-9!'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Signed out on 2 other devices.');

        $this->assertSame(0, DB::table('sessions')->whereIn('id', $mine)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertNotSame($token, $this->nurse->fresh()->remember_token);
        $this->assertAuthenticatedAs($this->nurse);
        $this->assertTrue(AuditLog::where('description', 'Signed out of other devices (2)')->exists());
    }

    public function test_changing_the_password_signs_out_other_devices(): void
    {
        config(['session.driver' => 'database']);
        $this->signedIn($this->nurse, str_repeat('a', 40), self::CHROME_WINDOWS);

        $this->actingAs($this->nurse)->put(route('profile.password'), [
            'current_password'      => 'Correct-Horse-9!',
            'password'              => 'New-Battery-Staple-7!',
            'password_confirmation' => 'New-Battery-Staple-7!',
        ])->assertSessionHas('success', 'Password changed. You were signed out on 1 other device.');

        $this->assertFalse(DB::table('sessions')->where('id', str_repeat('a', 40))->exists());
    }

    public function test_admin_can_sign_a_user_out_everywhere(): void
    {
        config(['session.driver' => 'database']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('administrator');
        $this->signedIn($this->nurse, str_repeat('a', 40), self::CHROME_WINDOWS);
        $this->signedIn($this->nurse, str_repeat('b', 40), self::SAFARI_IPHONE);
        $token = $this->nurse->remember_token;

        $this->actingAs($admin)->get(route('admin.users.show', $this->nurse))
            ->assertOk()->assertSee('2 devices')->assertSee('Sign out everywhere');

        $this->actingAs($admin)->post(route('admin.users.sign-out', $this->nurse))
            ->assertSessionHas('success');

        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->nurse->id)->count());
        $this->assertNotSame($token, $this->nurse->fresh()->remember_token);

        // A nurse cannot, and nobody uses it on their own account.
        $this->actingAs($this->nurse)->post(route('admin.users.sign-out', $admin))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.sign-out', $admin))->assertForbidden();
    }

    public function test_device_names_are_plain(): void
    {
        $this->assertSame('Chrome on Windows', SessionPolicy::device(self::CHROME_WINDOWS));
        $this->assertSame('Safari on iPhone', SessionPolicy::device(self::SAFARI_IPHONE));
        $this->assertSame('Edge on Windows', SessionPolicy::device('Mozilla/5.0 (Windows NT 10.0) Chrome/140.0 Safari/537.36 Edg/140.0'));
        $this->assertSame('Unknown device', SessionPolicy::device(''));
    }
}
