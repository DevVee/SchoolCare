<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\LandingItem;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Administration > Website: page text, services, common questions, clinic team
 * and advisories (permission manage-landing).
 */
class WebsiteAdminTest extends TestCase
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

    private function item(string $section, array $attributes = []): LandingItem
    {
        return LandingItem::create($attributes + [
            'section'    => $section,
            'title'      => ucfirst($section).' title '.uniqid(),
            'subtitle'   => $section === 'team' ? 'School nurse' : null,
            'body'       => 'Some text about it.',
            'sort_order' => (int) LandingItem::query()->section($section)->max('sort_order') + 1,
            'is_enabled' => true,
        ]);
    }

    private function advisory(array $attributes = []): Announcement
    {
        return Announcement::create($attributes + [
            'title'      => 'Clinic closed on Friday',
            'type'       => 'advisory',
            'audience'   => 'public',
            'is_enabled' => true,
        ]);
    }

    /** Titles of one section in their saved order. */
    private function titles(string $section): array
    {
        return LandingItem::query()->section($section)->ordered()->pluck('title')->all();
    }

    public function test_admin_can_open_every_website_screen(): void
    {
        $items = [
            'services' => $this->item('service', ['title' => 'Vision screening', 'icon' => 'eye']),
            'faqs'     => $this->item('faq', ['title' => 'Can I rest in the clinic?']),
            'team'     => $this->item('team', ['title' => 'Maria Santos']),
        ];
        $advisory = $this->advisory();

        $this->actingAs($this->admin)->get(route('admin.website.edit'))
            ->assertOk()
            ->assertSee('Page text')
            ->assertSee('View the clinic page')
            ->assertSee(route('clinic'), false)
            ->assertSee('name="landing_section_order"', false)
            ->assertSee('name="landing_hero_heading"', false)
            ->assertSee('name="landing_hero_image"', false);

        foreach ($items as $segment => $item) {
            $this->actingAs($this->admin)->get(route('admin.website.items.index', $segment))
                ->assertOk()
                ->assertSee($item->title)
                ->assertSee(route('admin.website.items.move', [$segment, $item]), false)
                ->assertSee(route('admin.website.items.toggle', [$segment, $item]), false)
                ->assertSee(route('admin.website.items.destroy', [$segment, $item]), false);

            $this->actingAs($this->admin)->get(route('admin.website.items.create', $segment))
                ->assertOk()
                ->assertSee(route('admin.website.items.store', $segment), false);

            $this->actingAs($this->admin)->get(route('admin.website.items.edit', [$segment, $item]))
                ->assertOk()
                ->assertSee($item->title)
                ->assertSee(route('admin.website.items.update', [$segment, $item]), false);
        }

        $this->actingAs($this->admin)->get(route('admin.website.advisories.index'))
            ->assertOk()
            ->assertSee('Clinic closed on Friday')
            ->assertSee('Showing');
        $this->actingAs($this->admin)->get(route('admin.website.advisories.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.website.advisories.edit', $advisory))
            ->assertOk()
            ->assertSee('Clinic closed on Friday');
    }

    public function test_lists_show_an_empty_state_when_nothing_is_added(): void
    {
        LandingItem::query()->delete();

        foreach (['services' => 'No services yet', 'faqs' => 'No questions yet', 'team' => 'No team members yet'] as $segment => $text) {
            $this->actingAs($this->admin)->get(route('admin.website.items.index', $segment))
                ->assertOk()
                ->assertSee($text);
        }

        $this->actingAs($this->admin)->get(route('admin.website.advisories.index'))
            ->assertOk()
            ->assertSee('No advisories yet');
    }

    public function test_page_text_is_saved_and_shown_on_the_clinic_page(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.website.update'), [
                'landing_hero_heading'    => 'Welcome to the Riverside clinic',
                'landing_primary_cta_target'   => 'request',
                'landing_secondary_cta_target' => 'schedule',
                'landing_section_order'   => "faq\nservices",
                'landing_show_advisories' => '1',
                'landing_show_services'   => '1',
                'landing_show_schedule'   => '1',
                'landing_show_steps'      => '1',
                'landing_show_team'       => '0',
                'landing_show_faq'        => '1',
                'landing_show_contact'    => '1',
                'landing_show_credit'     => '1',
            ])
            ->assertRedirect(route('admin.website.edit'))
            ->assertSessionHas('success');

        $this->assertSame('Welcome to the Riverside clinic', Setting::get('landing_hero_heading'));
        $order = settings()->list('landing_section_order');
        $this->assertSame(['faq', 'services'], array_slice($order, 0, 2));
        $this->assertFalse((bool) settings('landing_show_team'));

        $this->get(route('clinic'))->assertOk()->assertSee('Welcome to the Riverside clinic');
    }

    public function test_page_text_errors_come_back_to_the_form(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.website.edit'))
            ->put(route('admin.website.update'), [
                'landing_primary_cta_url'      => 'not a web address',
                'landing_primary_cta_target'   => 'url',
                'landing_secondary_cta_target' => 'schedule',
            ])
            ->assertRedirect(route('admin.website.edit'))
            ->assertSessionHasErrors('landing_primary_cta_url');

        $this->actingAs($this->admin)->get(route('admin.website.edit'))
            ->assertOk()
            ->assertSee('Please fix the errors below')
            ->assertSee('not a web address')
            ->assertSee('is-invalid', false);
    }

    public function test_page_text_form_has_every_field_of_the_website_group(): void
    {
        // Unchecked switches save as "off", so every switch of the group must be on the page.
        $response = $this->actingAs($this->admin)->get(route('admin.website.edit'))->assertOk();

        foreach (array_keys(settings()->fields('landing')) as $key) {
            $response->assertSee('name="'.$key.'"', false);
        }
    }

    public function test_service_can_be_added_edited_hidden_moved_and_deleted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.website.items.store', 'services'), [
                'title'      => 'Eye check',
                'body'       => 'A quick vision test for students.',
                'icon'       => 'eye',
                'link_label' => '',
                'link_url'   => '',
                'is_enabled' => '1',
            ])
            ->assertRedirect(route('admin.website.items.index', 'services'))
            ->assertSessionHas('success');

        $service = LandingItem::query()->where('title', 'Eye check')->firstOrFail();
        $this->assertSame('service', $service->section);
        $this->assertSame('eye', $service->icon);
        $this->assertTrue($service->is_enabled);
        $this->assertSame('Eye check', last($this->titles('service')), 'A new service goes to the end of the list.');

        $this->get(route('clinic'))->assertSee('Eye check');

        $this->actingAs($this->admin)
            ->put(route('admin.website.items.update', ['services', $service]), [
                'title'      => 'Eye and ear check',
                'body'       => 'A quick vision and hearing test.',
                'icon'       => 'ear',
                'link_label' => 'Book a check',
                'link_url'   => 'https://example.test/book',
                'is_enabled' => '1',
            ])
            ->assertRedirect(route('admin.website.items.index', 'services'));

        $service->refresh();
        $this->assertSame('Eye and ear check', $service->title);
        $this->assertSame('ear', $service->icon);
        $this->assertSame('https://example.test/book', $service->link_url);

        // Hide it from the website, then show it again.
        $this->actingAs($this->admin)
            ->from(route('admin.website.items.index', 'services'))
            ->patch(route('admin.website.items.toggle', ['services', $service]))
            ->assertRedirect(route('admin.website.items.index', 'services'));
        $this->assertFalse($service->fresh()->is_enabled);
        $this->get(route('clinic'))->assertDontSee('Eye and ear check');

        $this->actingAs($this->admin)->patch(route('admin.website.items.toggle', ['services', $service]));
        $this->assertTrue($service->fresh()->is_enabled);

        // Move it up one place.
        $before = $this->titles('service');
        $this->actingAs($this->admin)
            ->from(route('admin.website.items.index', 'services'))
            ->patch(route('admin.website.items.move', ['services', $service]), ['direction' => 'up'])
            ->assertRedirect(route('admin.website.items.index', 'services'));
        $after = $this->titles('service');
        $this->assertSame(array_search('Eye and ear check', $before, true) - 1, array_search('Eye and ear check', $after, true));

        // And back down.
        $this->actingAs($this->admin)->patch(route('admin.website.items.move', ['services', $service]), ['direction' => 'down']);
        $this->assertSame($before, $this->titles('service'));

        $this->actingAs($this->admin)
            ->delete(route('admin.website.items.destroy', ['services', $service]))
            ->assertRedirect(route('admin.website.items.index', 'services'))
            ->assertSessionHas('success');
        $this->assertModelMissing($service);
    }

    public function test_item_form_errors_are_reported_per_field(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.website.items.create', 'faqs'))
            ->post(route('admin.website.items.store', 'faqs'), ['title' => '', 'body' => ''])
            ->assertRedirect(route('admin.website.items.create', 'faqs'))
            ->assertSessionHasErrors(['title', 'body']);

        $this->actingAs($this->admin)
            ->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['title' => 'The question field is required.']))])
            ->get(route('admin.website.items.create', 'faqs'))
            ->assertOk()
            ->assertSee('The question field is required.')
            ->assertSee('is-invalid', false);
    }

    public function test_team_member_photo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.website.items.store', 'team'), [
                'title'      => 'Ana Reyes',
                'subtitle'   => 'School nurse',
                'body'       => '',
                'image'      => UploadedFile::fake()->image('ana.jpg', 300, 300),
                'is_enabled' => '1',
            ])
            ->assertRedirect(route('admin.website.items.index', 'team'));

        $member = LandingItem::query()->where('title', 'Ana Reyes')->firstOrFail();
        $this->assertNotNull($member->image);
        Storage::disk('public')->assertExists($member->image);
        $path = $member->image;

        $this->actingAs($this->admin)->get(route('admin.website.items.edit', ['team', $member]))
            ->assertOk()
            ->assertSee($member->imageUrl(), false)
            ->assertSee('name="remove_image"', false);

        $this->actingAs($this->admin)
            ->put(route('admin.website.items.update', ['team', $member]), [
                'title'        => 'Ana Reyes',
                'subtitle'     => 'Head nurse',
                'remove_image' => '1',
                'is_enabled'   => '1',
            ])
            ->assertRedirect(route('admin.website.items.index', 'team'));

        $member->refresh();
        $this->assertNull($member->image);
        $this->assertSame('Head nurse', $member->subtitle);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_an_item_is_only_reachable_under_its_own_tab(): void
    {
        $faq = $this->item('faq');

        $this->actingAs($this->admin)->get(route('admin.website.items.edit', ['services', $faq]))->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/website/unknown')->assertNotFound();
    }

    public function test_advisory_can_be_posted_turned_off_moved_and_deleted(): void
    {
        $older = $this->advisory(['title' => 'Older advisory', 'sort_order' => 0]);

        $this->actingAs($this->admin)
            ->post(route('admin.website.advisories.store'), [
                'title'      => 'Deworming day on Monday',
                'body'       => 'Bring the signed consent form.',
                'type'       => 'info',
                'audience'   => 'both',
                'starts_at'  => now()->subHour()->format('Y-m-d\TH:i'),
                'ends_at'    => now()->addDays(3)->format('Y-m-d\TH:i'),
                'is_enabled' => '1',
            ])
            ->assertRedirect(route('admin.website.advisories.index'))
            ->assertSessionHas('success');

        $advisory = Announcement::query()->where('title', 'Deworming day on Monday')->firstOrFail();
        $this->assertSame('both', $advisory->audience);
        $this->assertSame($this->admin->id, $advisory->created_by);
        $this->assertSame(['Deworming day on Monday', 'Older advisory'], Announcement::query()->ordered()->pluck('title')->all());

        $this->get(route('clinic'))->assertSee('Deworming day on Monday');

        $this->actingAs($this->admin)
            ->put(route('admin.website.advisories.update', $advisory), [
                'title'    => 'Deworming day on Tuesday',
                'type'     => 'urgent',
                'audience' => 'public',
                'is_enabled' => '1',
            ])
            ->assertRedirect(route('admin.website.advisories.index'));
        $this->assertSame('urgent', $advisory->fresh()->type);

        $this->actingAs($this->admin)->patch(route('admin.website.advisories.toggle', $advisory))->assertRedirect();
        $this->assertFalse($advisory->fresh()->is_enabled);
        $this->actingAs($this->admin)->get(route('admin.website.advisories.index'))->assertOk()->assertSee('Off');

        $this->actingAs($this->admin)->patch(route('admin.website.advisories.move', $advisory), ['direction' => 'down'])->assertRedirect();
        $this->assertSame(['Older advisory', 'Deworming day on Tuesday'], Announcement::query()->ordered()->pluck('title')->all());

        $this->actingAs($this->admin)
            ->delete(route('admin.website.advisories.destroy', $advisory))
            ->assertRedirect(route('admin.website.advisories.index'));
        $this->assertModelMissing($advisory);
        $this->assertModelExists($older);
    }

    public function test_advisory_end_must_not_be_before_its_start(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.website.advisories.create'))
            ->post(route('admin.website.advisories.store'), [
                'title'     => 'Bad dates',
                'type'      => 'info',
                'audience'  => 'public',
                'starts_at' => '2026-10-10T08:00',
                'ends_at'   => '2026-10-09T08:00',
            ])
            ->assertRedirect(route('admin.website.advisories.create'))
            ->assertSessionHasErrors('ends_at');

        $this->assertDatabaseMissing('announcements', ['title' => 'Bad dates']);
    }

    public function test_user_without_manage_landing_gets_403_everywhere(): void
    {
        $role = Role::firstOrCreate(['name' => 'records-only', 'guard_name' => 'web']);
        $role->syncPermissions(['view-users']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->assignRole('viewer');
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole($role);

        $service = $this->item('service', ['title' => 'Untouched service']);
        $advisory = $this->advisory(['title' => 'Untouched advisory']);

        $requests = [
            ['get',    route('admin.website.edit')],
            ['put',    route('admin.website.update')],
            ['get',    route('admin.website.items.index', 'services')],
            ['get',    route('admin.website.items.index', 'faqs')],
            ['get',    route('admin.website.items.index', 'team')],
            ['get',    route('admin.website.items.create', 'services')],
            ['post',   route('admin.website.items.store', 'services')],
            ['get',    route('admin.website.items.edit', ['services', $service])],
            ['put',    route('admin.website.items.update', ['services', $service])],
            ['patch',  route('admin.website.items.toggle', ['services', $service])],
            ['patch',  route('admin.website.items.move', ['services', $service])],
            ['delete', route('admin.website.items.destroy', ['services', $service])],
            ['get',    route('admin.website.advisories.index')],
            ['get',    route('admin.website.advisories.create')],
            ['post',   route('admin.website.advisories.store')],
            ['get',    route('admin.website.advisories.edit', $advisory)],
            ['put',    route('admin.website.advisories.update', $advisory)],
            ['patch',  route('admin.website.advisories.toggle', $advisory)],
            ['patch',  route('admin.website.advisories.move', $advisory)],
            ['delete', route('admin.website.advisories.destroy', $advisory)],
        ];

        foreach ([$viewer, $other] as $user) {
            foreach ($requests as [$method, $url]) {
                $this->actingAs($user)->{$method}($url, ['title' => 'Hacked'])->assertForbidden();
            }
        }

        $this->assertSame('Untouched service', $service->fresh()->title);
        $this->assertTrue($service->fresh()->is_enabled);
        $this->assertSame('Untouched advisory', $advisory->fresh()->title);
    }

    public function test_website_admin_views_have_no_dashes_or_emoji(): void
    {
        foreach (File::allFiles(resource_path('views/admin/website')) as $file) {
            $text = $file->getContents();
            $this->assertStringNotContainsString("\u{2014}", $text, $file->getRelativePathname().' has an em dash');
            $this->assertStringNotContainsString("\u{2013}", $text, $file->getRelativePathname().' has an en dash');
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text, $file->getRelativePathname().' has an emoji');
        }
    }
}
