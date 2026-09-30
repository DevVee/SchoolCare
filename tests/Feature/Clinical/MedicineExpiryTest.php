<?php

namespace Tests\Feature\Clinical;

use App\Models\Medicine;

class MedicineExpiryTest extends ClinicalTestCase
{
    public function test_expiring_soon_and_expired_flags(): void
    {
        $days = Medicine::expiryWarningDays();

        $cases = [
            // [expiration_date, expected is_expiring_soon, expected is_expired]
            [now()->addYear()->toDateString(),             false, false],
            [now()->addDays($days + 1)->toDateString(),    false, false],
            [now()->addDays($days)->toDateString(),        true,  false],
            [now()->addDays(10)->toDateString(),           true,  false],
            [today()->toDateString(),                      true,  false], // usable through its expiry date
            [now()->subDay()->toDateString(),              false, true],
            [now()->subYear()->toDateString(),             false, true],
            [null,                                         false, false],
        ];

        foreach ($cases as [$date, $soon, $expired]) {
            $m = Medicine::factory()->create(['expiration_date' => $date])->fresh();
            $label = $date ?? 'null';

            $this->assertSame($soon, $m->is_expiring_soon, "is_expiring_soon for {$label}");
            $this->assertSame($expired, $m->is_expired, "is_expired for {$label}");

            // Accessors and query scopes agree.
            $this->assertSame($soon, Medicine::whereKey($m->id)->expiringSoon()->exists(), "scopeExpiringSoon for {$label}");
            $this->assertSame($expired, Medicine::whereKey($m->id)->expired()->exists(), "scopeExpired for {$label}");
        }
    }

    public function test_days_until_expiry_is_a_whole_number(): void
    {
        $m = Medicine::factory()->create(['expiration_date' => now()->addDays(5)->toDateString()])->fresh();
        $this->assertSame(5, $m->days_until_expiry);
    }

    public function test_dispensable_scope_excludes_expired_inactive_and_empty(): void
    {
        $ok = Medicine::factory()->create(['quantity' => 3]);
        Medicine::factory()->expired()->create(['quantity' => 3]);
        Medicine::factory()->inactive()->create(['quantity' => 3]);
        Medicine::factory()->create(['quantity' => 0]);
        $noExpiry = Medicine::factory()->create(['quantity' => 3, 'expiration_date' => null]);

        $this->assertEqualsCanonicalizing(
            [$ok->id, $noExpiry->id],
            Medicine::dispensable()->pluck('id')->all()
        );
    }

    public function test_medicine_update_cannot_change_quantity_directly(): void
    {
        $m = Medicine::factory()->create(['quantity' => 20]);

        $this->actingAs($this->admin)
            ->put(route('medicines.update', $m), [
                'name'                => $m->name,
                'category_id'         => $m->category_id,
                'quantity'            => 999,
                'unit'                => 'tablet',
                'low_stock_threshold' => 5,
                'is_active'           => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(20, $m->fresh()->quantity);
    }

    public function test_opening_stock_on_create_goes_through_the_ledger(): void
    {
        $category = \App\Models\MedicineCategory::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('medicines.store'), [
                'name'                => 'Ledgerol 500mg',
                'category_id'         => $category->id,
                'quantity'            => 40,
                'unit'                => 'tablet',
                'low_stock_threshold' => 5,
                'expiration_date'     => now()->addYear()->toDateString(),
                'is_active'           => '1',
            ])
            ->assertSessionHasNoErrors();

        $m = Medicine::where('name', 'Ledgerol 500mg')->firstOrFail();
        $this->assertSame(40, $m->quantity);
        $this->assertDatabaseHas('inventory_transactions', [
            'medicine_id' => $m->id, 'transaction_type' => 'stock_in', 'quantity' => 40, 'after_quantity' => 40,
        ]);
    }

    public function test_inactive_medicines_remain_visible_on_the_index(): void
    {
        Medicine::factory()->inactive()->create(['name' => 'Retiredol 5mg']);

        $this->actingAs($this->admin)
            ->get(route('medicines.index'))
            ->assertOk()
            ->assertSee('Retiredol 5mg');

        $this->actingAs($this->admin)
            ->get(route('medicines.index', ['status' => 'active']))
            ->assertOk()
            ->assertDontSee('Retiredol 5mg');
    }

    public function test_stock_in_keeps_the_earliest_expiry_on_hand(): void
    {
        $this->actingAs($this->admin);
        $soon = now()->addMonths(2)->toDateString();
        $m    = Medicine::factory()->create(['quantity' => 10, 'expiration_date' => $soon]);

        app(\App\Services\InventoryService::class)->stockIn($m, [
            'quantity' => 5, 'expiration_date' => now()->addYears(2)->toDateString(),
        ]);

        $this->assertSame($soon, $m->fresh()->expiration_date->toDateString());
        $this->assertSame(15, $m->fresh()->quantity);
    }
}
