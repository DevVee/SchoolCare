<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The SMS page has an on/off switch that saves only notifications.sms_enabled
 * (partial save via `only[]`) and returns to the SMS page (`return_to`).
 */
class SettingsPartialSaveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('administrator');
    }

    public function test_sms_switch_saves_only_the_master_switch_and_returns_to_sms_page(): void
    {
        $this->assertTrue(settings('notify_sms_appointment_created'));

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'notifications'), [
                'only'        => ['sms_enabled'],
                'return_to'   => 'sms',
                'sms_enabled' => '1',
            ])
            ->assertRedirect(route('admin.settings.edit', 'sms'));

        $this->assertTrue(settings('sms_enabled'));
        // Other notification toggles were not part of the request and must be unchanged.
        $this->assertTrue(settings('notify_sms_appointment_created'));
        $this->assertTrue(settings('notify_email_user_created'));
    }

    public function test_return_to_ignores_unknown_groups(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'notifications'), [
                'only'        => ['sms_enabled'],
                'return_to'   => 'https://example.com',
                'sms_enabled' => '0',
            ])
            ->assertRedirect(route('admin.settings.edit', 'notifications'));
    }

    public function test_settings_pages_render_friendly_editors(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'clinic'))
            ->assertOk()->assertSee('data-list-editor', false)->assertDontSee('Clinic Status Text');

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'appointments'))
            ->assertOk()->assertSee('data-hours-grid', false);

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'academic'))
            ->assertOk()->assertSee('data-category-lists', false);

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'sms'))
            ->assertOk()->assertSee('SMS is off')->assertSee('Technical details')->assertSee('Patient name');
    }
}
