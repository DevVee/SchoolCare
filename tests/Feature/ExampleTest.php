<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests see the public landing page at the root URL.
     */
    public function test_root_shows_landing_page_to_guests(): void
    {
        $this->get('/')->assertOk();
    }

    /**
     * Authenticated users visiting the root URL are sent to the dashboard.
     */
    public function test_root_redirects_authenticated_users_to_dashboard(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    }

    /**
     * The dashboard renders for any active authenticated user.
     */
    public function test_authenticated_users_reach_dashboard(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
}
