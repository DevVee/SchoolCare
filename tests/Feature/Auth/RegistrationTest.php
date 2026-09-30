<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SchoolCare is a closed-staff system — self-registration is disabled by design.
 * New accounts are created exclusively by administrators via Admin → Users → Create.
 * ALLOW_REGISTRATION is false in config/auth.php.
 *
 * @see CRITICAL-2: Public self-registration disabled for security.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The /register route must NOT be publicly accessible.
     * Guests hitting /register should receive a 404 (route doesn't exist).
     */
    public function test_registration_screen_is_disabled(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    /**
     * Posting to /register should also return 404 since the route is gated off.
     */
    public function test_new_users_cannot_self_register(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response->assertStatus(404);
    }
}
