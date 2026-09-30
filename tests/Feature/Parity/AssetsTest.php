<?php

namespace Tests\Feature\Parity;

use App\Models\Asset;
use App\Models\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Clinical\ClinicalTestCase;

class AssetsTest extends ClinicalTestCase
{
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Digital BP Monitor', 'category' => 'Diagnostic', 'property_number' => 'PN-0001',
            'quantity' => 2, 'condition' => 'Good', 'location' => 'Clinic room 1',
            'acquired_at' => '2026-01-15', 'cost' => '1500.00', 'notes' => 'Omron',
        ];
    }

    public function test_admin_can_create_update_and_remove_an_asset_with_a_picture(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('assets.store'), $this->payload(['image' => UploadedFile::fake()->image('bp.jpg', 400, 300)]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $asset = Asset::firstOrFail();
        $this->assertSame('Digital BP Monitor', $asset->name);
        $this->assertNotNull($asset->image_path);
        $this->assertStringStartsWith('assets/', $asset->image_path);
        $this->assertStringNotContainsString('bp.jpg', $asset->image_path); // hashed name
        Storage::disk('public')->assertExists($asset->image_path);
        $this->assertTrue(AuditLog::where('module', 'assets')->where('action', 'created')->exists());

        $this->actingAs($this->admin)->get(route('assets.index', ['search' => 'BP', 'condition' => 'Good']))
            ->assertOk()->assertSee('Digital BP Monitor');

        $old = $asset->image_path;
        $this->actingAs($this->admin)
            ->put(route('assets.update', $asset), $this->payload(['condition' => 'Needs repair', 'remove_image' => '1']))
            ->assertSessionHasNoErrors();
        $asset->refresh();
        $this->assertSame('Needs repair', $asset->condition);
        $this->assertNull($asset->image_path);
        Storage::disk('public')->assertMissing($old);

        $this->actingAs($this->admin)->delete(route('assets.destroy', $asset))->assertRedirect(route('assets.index'));
        $this->assertSoftDeleted($asset);
    }

    public function test_validation_rejects_unknown_condition_and_non_images(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('assets.store'), $this->payload([
                'condition' => 'Sparkly',
                'image'     => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ]))
            ->assertSessionHasErrors(['condition', 'image']);

        $this->actingAs($this->admin)
            ->post(route('assets.store'), $this->payload(['image' => UploadedFile::fake()->image('big.png')->size(3000)]))
            ->assertSessionHasErrors('image');

        $this->assertSame(0, Asset::count());
    }

    public function test_nurse_can_view_but_not_manage_and_staff_cannot_view(): void
    {
        $asset = Asset::create($this->payload());
        $nurse = $this->userWithRole('nurse');
        $staff = $this->userWithRole('staff');

        $this->actingAs($nurse)->get(route('assets.index'))->assertOk()->assertSee($asset->name);
        $this->actingAs($nurse)->get(route('assets.show', $asset))->assertOk();
        $this->actingAs($nurse)->get(route('assets.export'))->assertOk();
        $this->actingAs($nurse)->get(route('assets.create'))->assertForbidden();
        $this->actingAs($nurse)->post(route('assets.store'), $this->payload())->assertForbidden();
        $this->actingAs($nurse)->put(route('assets.update', $asset), $this->payload(['name' => 'Hacked']))->assertForbidden();
        $this->actingAs($nurse)->delete(route('assets.destroy', $asset))->assertForbidden();

        $this->actingAs($staff)->get(route('assets.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('assets.export'))->assertForbidden();

        $this->assertSame('Digital BP Monitor', $asset->fresh()->name);
        $this->assertFalse($asset->fresh()->trashed());
    }
}
