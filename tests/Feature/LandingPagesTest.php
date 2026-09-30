<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ProductSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public website: the product page at "/", the school clinic's page at
 * "/clinic" and the privacy notice.
 */
class LandingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_is_the_product_page_with_a_path_to_the_clinic(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Visit the clinic page')
            ->assertSee(route('clinic'), false)
            ->assertSee(route('login'), false)
            ->assertSee('Currently in clinic');
    }

    public function test_product_copy_uses_the_app_name_from_settings(): void
    {
        Setting::set('app_name', 'Campus Care');

        $this->get('/')->assertOk()->assertSee('Campus Care keeps patient records');
    }

    public function test_product_copy_falls_back_to_schoolcare_and_has_no_placeholders(): void
    {
        Setting::set('app_name', '');

        $content = ProductSite::content();
        $this->assertSame('SchoolCare', $content['app']);

        $text = json_encode($content, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString(':app', $text);
        $this->assertStringNotContainsString(':ai', $text);
        // Owner rule: no em or en dashes in visible text.
        $this->assertStringNotContainsString("\u{2014}", $text);
        $this->assertStringNotContainsString("\u{2013}", $text);
    }

    public function test_clinic_page_shows_todays_status_for_guests_and_staff(): void
    {
        $this->get('/clinic')->assertOk()->assertSee('Today at the clinic');

        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get('/clinic')->assertOk();
    }

    public function test_privacy_notice_links_back_to_the_clinic_page(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Back to the clinic page');
    }
}
