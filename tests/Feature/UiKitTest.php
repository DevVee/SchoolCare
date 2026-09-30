<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ColorScale;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Smoke tests for the x-ui component library: the /ui-kit style guide renders
 * every component, access is limited to manage-settings, and the runtime brand
 * colour scale behaves.
 */
class UiKitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_ui_kit_renders_every_component_for_administrators(): void
    {
        $response = $this->actingAs($this->userWithRole('administrator'))->get(route('ui.kit', ['category' => 'student', 'search' => 'Maria']));

        $response->assertOk()
            ->assertSee('data-chart=', false)
            ->assertSee('class="c-pagination"', false)
            ->assertSee('id="confirmModal"', false)
            ->assertSee('Showing <strong>21</strong> to <strong>30</strong>', false)
            ->assertSee('Filtered by')
            ->assertSee('Enter a valid email address', false)
            ->assertSee('Actions for Maria Santos');
    }

    public function test_ui_kit_is_forbidden_without_manage_settings(): void
    {
        $this->actingAs($this->userWithRole('viewer'))->get(route('ui.kit'))->assertForbidden();
    }

    public function test_legacy_links_use_the_branded_paginator(): void
    {
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(range(1, 5), 5, 5, 1, ['path' => '/patients']);

        $html = (string) $paginator->links();

        $this->assertStringContainsString('c-pagination', $html);
        $this->assertStringContainsString('Showing <strong>1</strong> to <strong>5</strong>', $html);
    }

    public function test_components_render_in_isolation(): void
    {
        view()->share('errors', new ViewErrorBag);

        $html = Blade::render(<<<'BLADE'
            <x-ui.button icon="plus-lg">Add</x-ui.button>
            <x-ui.badge color="secondary">Draft</x-ui.badge>
            <x-ui.status-badge status="no_show" type="appointment" />
            <x-ui.count :value="148" />
            <x-ui.stat-strip :items="[['label' => 'Visits', 'value' => 1200]]" />
            <x-ui.chart type="donut" :series="[0, 0]" :labels="['A', 'B']" empty="Nothing yet." />
        BLADE);

        $this->assertStringContainsString('btn btn-primary', $html);
        $this->assertStringContainsString('pill-neutral', $html);
        $this->assertStringContainsString('No-show', $html);
        $this->assertStringContainsString('99+', $html);
        $this->assertStringContainsString('1,200', $html);
        $this->assertStringContainsString('Nothing yet.', $html);
    }

    public function test_color_scale_only_overrides_non_default_valid_colours(): void
    {
        $this->assertSame([], ColorScale::cssVariables('#2563eb'));
        $this->assertSame([], ColorScale::cssVariables('not-a-colour'));

        $vars = ColorScale::cssVariables('#0F766E');
        $this->assertSame('#0F766E', $vars['--brand-600']);
        $this->assertSame('15, 118, 110', $vars['--brand-rgb']);
        $this->assertArrayHasKey('--brand-50', $vars);
        $this->assertArrayHasKey('--brand-950', $vars);
        $this->assertSame('#FFFFFF', $vars['--brand-contrast']);
    }
}
