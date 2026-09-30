<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
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

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_index_redirects_to_first_group_and_every_group_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.index'))
            ->assertRedirect(route('admin.settings.edit', 'general'));

        foreach (array_keys(settings()->visibleGroups()) as $group) {
            $this->actingAs($this->admin)
                ->get(route('admin.settings.edit', $group))
                ->assertOk()
                ->assertSee(settings()->groups()[$group]['label']);
        }
    }

    public function test_unknown_or_empty_group_is_404(): void
    {
        $this->actingAs($this->admin)->get('/admin/settings/nope')->assertNotFound();
        $this->actingAs($this->admin)->put('/admin/settings/nope', [])->assertNotFound();
        // The landing group has no fields yet, so it is not editable.
        $this->actingAs($this->admin)->get('/admin/settings/landing')->assertNotFound();
    }

    public function test_user_without_manage_settings_gets_403(): void
    {
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($viewer)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.settings.edit', 'general'))->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.settings.update', 'general'), ['app_name' => 'Hacked'])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.settings.test-sms'), ['test_number' => '09171234567'])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.settings.test-email'))->assertForbidden();

        $this->assertSame('SSCMS', settings('app_name'));
    }

    public function test_saving_one_group_does_not_reset_booleans_of_another_group(): void
    {
        // Turn SMS on (notifications group).
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'notifications'), [
                'sms_enabled'                     => '1',
                'notify_sms_appointment_approved' => '1',
                // every other notifications toggle unchecked (absent) → false
            ])
            ->assertRedirect(route('admin.settings.edit', 'notifications'));

        $this->assertTrue(settings('sms_enabled'));
        $this->assertTrue(settings('notify_sms_appointment_approved'));
        $this->assertFalse(settings('notify_sms_appointment_created'));

        // Save the AI group with its checkbox unchecked.
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'ai'), ['ai_model' => 'llama-3.3-70b-versatile'])
            ->assertSessionHasNoErrors();

        $this->assertFalse(settings('ai_enabled'));
        // Booleans of the notifications group are untouched.
        $this->assertTrue(settings('sms_enabled'));
        $this->assertTrue(settings('notify_sms_appointment_approved'));
        $this->assertTrue(settings('allow_weekend_booking')); // appointments group default
    }

    public function test_unknown_and_other_group_keys_are_ignored(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'general'), [
                'app_name'         => 'School Health Hub',
                'timezone'         => 'Asia/Manila',
                'date_format'      => 'M d, Y',
                'time_format'      => 'h:i A',
                'records_per_page' => 25,
                'evil_key'         => 'x',
                'ai_model'         => 'mixtral-8x7b-32768', // belongs to the ai group
                'sms_enabled'      => '1',                  // belongs to notifications
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('School Health Hub', settings('app_name'));
        $this->assertSame(25, settings('records_per_page'));
        $this->assertDatabaseMissing('settings', ['key' => 'evil_key']);
        $this->assertSame('llama-3.3-70b-versatile', settings('ai_model'));
        $this->assertFalse(settings('sms_enabled'));
    }

    public function test_list_and_options_fields_are_parsed_from_lines(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'academic'), [
                'year_levels'     => "Grade 7\r\nGrade 8\n\n Grade 8 \nGrade 9",
                'sections'        => 'Rizal',
                'program_strands' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Grade 7', 'Grade 8', 'Grade 9'], settings('year_levels'));
        $this->assertSame(['Rizal'], settings('sections'));
        $this->assertSame([], settings('program_strands'));

        $clinic = $this->clinicPayload(['patient_categories' => "college | College\nalumni | Alumni\nNew Hires"]);
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'clinic'), $clinic)
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['college' => 'College', 'alumni' => 'Alumni', 'new_hires' => 'New Hires'],
            \App\Models\Patient::categoryLabels()
        );
    }

    public function test_sms_template_with_unknown_placeholder_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'sms'), [
                'sms_sender_name'       => 'CLINIC',
                'sms_template_approval' => 'Hi {name}, see you {date} at {time} - {clinic} {bogus}',
            ])
            ->assertSessionHasErrors('sms_template_approval');

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'sms'), [
                'sms_sender_name'       => 'CLINIC',
                'sms_template_approval' => 'Hi {name}, see you {date} at {time} - {clinic}',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Hi {name}, see you {date} at {time} - {clinic}', settings('sms_template_approval'));
    }

    public function test_logo_upload_replace_and_remove(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'branding'), $this->brandingPayload([
                'brand_logo' => UploadedFile::fake()->image('logo.png', 128, 128),
            ]))
            ->assertSessionHasNoErrors();

        $first = settings('brand_logo');
        $this->assertStringStartsWith('branding/brand_logo-', $first);
        $this->assertStringEndsWith('.png', $first);
        Storage::disk('public')->assertExists($first);
        $this->assertStringContainsString($first, settings()->imageUrl('brand_logo'));

        // Replace → old file deleted.
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'branding'), $this->brandingPayload([
                'brand_logo' => UploadedFile::fake()->image('logo2.jpg', 64, 64),
            ]))
            ->assertSessionHasNoErrors();

        $second = settings('brand_logo');
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        // Remove → back to the default image.
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'branding'), $this->brandingPayload(['remove_brand_logo' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertSame('', settings('brand_logo'));
        Storage::disk('public')->assertMissing($second);
        $this->assertSame('/sscms-icon.svg', settings()->imageUrl('brand_logo'));
    }

    public function test_svg_logo_and_oversized_favicon_are_rejected(): void
    {
        Storage::fake('public');

        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'branding'), $this->brandingPayload(['brand_logo' => $svg]))
            ->assertSessionHasErrors('brand_logo');

        // An SVG renamed to .png is still rejected (content sniffing).
        $disguised = UploadedFile::fake()->createWithContent(
            'logo.png',
            '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>'
        );
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'branding'), $this->brandingPayload(['brand_logo' => $disguised]))
            ->assertSessionHasErrors('brand_logo');

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'branding'), $this->brandingPayload([
                'brand_favicon' => UploadedFile::fake()->image('favicon.png')->size(300),
            ]))
            ->assertSessionHasErrors('brand_favicon');

        $this->assertSame('', settings('brand_logo'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_brand_color_is_validated(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'branding'), $this->brandingPayload(['brand_primary_color' => 'red']))
            ->assertSessionHasErrors('brand_primary_color');

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'branding'), $this->brandingPayload(['brand_primary_color' => '#10B981']))
            ->assertSessionHasNoErrors();

        $this->assertSame('#10B981', settings('brand_primary_color'));
    }

    public function test_changes_are_audited_with_changed_keys_only(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'inventory'), $this->groupPayload('inventory', [
                'expiry_warning_days' => 45,
            ]))
            ->assertSessionHasNoErrors();

        $log = AuditLog::where('module', 'settings')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('expiry_warning_days', $log->description);
        $this->assertStringNotContainsString('low_stock_threshold', $log->description);
        $this->assertSame(['expiry_warning_days' => '45'], $log->new_values);
        $this->assertSame(45, \App\Models\Medicine::expiryWarningDays());
    }

    public function test_sync_defaults_inserts_missing_keys_without_overwriting(): void
    {
        Setting::create(['key' => 'clinic_name', 'value' => 'My Clinic', 'type' => 'string', 'group' => 'wrong']);

        $inserted = app(SettingsService::class)->syncDefaults();

        $this->assertGreaterThan(10, $inserted);
        $this->assertSame('My Clinic', settings('clinic_name'));
        $this->assertDatabaseHas('settings', ['key' => 'clinic_name', 'group' => 'clinic']);
        $this->assertDatabaseHas('settings', ['key' => 'reminder_hours_before', 'value' => '24']);

        // Second run inserts nothing.
        $this->assertSame(0, app(SettingsService::class)->syncDefaults());
    }

    public function test_legacy_setting_model_wrappers_still_work(): void
    {
        Setting::set('clinic_name', 'Wrapper Clinic');
        $this->assertSame('Wrapper Clinic', Setting::get('clinic_name'));
        $this->assertSame('Wrapper Clinic', settings('clinic_name'));
        $this->assertSame('fallback', Setting::get('does_not_exist', 'fallback'));
    }

    private function brandingPayload(array $overrides = []): array
    {
        return array_merge([
            'brand_primary_color' => '#2563EB',
            'header_title'        => 'app',
            'login_headline'      => "Your clinic,\ncompletely\norganized.",
            'login_subtext'       => 'Sub',
            'login_quote'         => '',
            'login_quote_author'  => '',
        ], $overrides);
    }

    private function clinicPayload(array $overrides = []): array
    {
        return $this->groupPayload('clinic', $overrides);
    }

    /** Current values of every field in a group, as the settings form would post them. */
    private function groupPayload(string $group, array $overrides = []): array
    {
        $svc = app(SettingsService::class);
        $payload = [];
        foreach ($svc->fields($group) as $key => $def) {
            if ($def['type'] === 'image') {
                continue;
            }
            $payload[$key] = in_array($def['type'], ['json_list', 'options'], true)
                ? $svc->toText($key)
                : $svc->get($key);
        }

        return array_merge($payload, $overrides);
    }
}
