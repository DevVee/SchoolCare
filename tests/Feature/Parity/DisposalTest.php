<?php

namespace Tests\Feature\Parity;

use App\Models\InventoryTransaction;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineDisposal;
use App\Services\InventoryService;
use Tests\Feature\Clinical\ClinicalTestCase;

class DisposalTest extends ClinicalTestCase
{
    /** Medicine with an expired batch of 8 (cost 2.50) and a good batch of 5. */
    private function setUpStock(): array
    {
        $this->actingAs($this->admin);
        $medicine = Medicine::factory()->create([
            'name' => 'Disposol 500mg', 'quantity' => 8, 'purchase_price' => 2.50,
            'batch_number' => 'EXP-1', 'expiration_date' => now()->subWeek()->toDateString(),
        ]);
        app(InventoryService::class)->stockIn($medicine, [
            'quantity' => 5, 'batch_number' => 'GOOD-1', 'expiration_date' => now()->addYear()->toDateString(),
        ]);

        return [
            $medicine->fresh(),
            MedicineBatch::where('batch_number', 'EXP-1')->firstOrFail(),
            MedicineBatch::where('batch_number', 'GOOD-1')->firstOrFail(),
        ];
    }

    public function test_expiry_page_lists_batches_and_dispose_updates_totals_and_history(): void
    {
        [$medicine, $expired, $good] = $this->setUpStock();
        $this->assertTrue($medicine->is_expired); // earliest stocked batch is expired

        $this->actingAs($this->admin)
            ->get(route('medicines.expiring', ['view' => 'expired']))
            ->assertOk()->assertSee('Disposol 500mg')->assertSee('EXP-1');

        $this->actingAs($this->admin)
            ->post(route('disposals.store', $expired), ['reason' => 'Expired on shelf'])
            ->assertSessionHas('success');

        $expired->refresh();
        $this->assertSame(0, $expired->quantity);
        $this->assertNotNull($expired->disposed_at);

        $medicine->refresh();
        $this->assertSame(5, $medicine->quantity);
        $this->assertSame((int) MedicineBatch::where('medicine_id', $medicine->id)->notDisposed()->sum('quantity'), $medicine->quantity);
        // Cached expiry moves to the remaining batch.
        $this->assertSame($good->expiry_date->toDateString(), $medicine->expiration_date->toDateString());
        $this->assertFalse($medicine->is_expired);

        $disposal = MedicineDisposal::firstOrFail();
        $this->assertSame(8, $disposal->quantity);
        $this->assertSame('20.00', (string) $disposal->total_cost);
        $this->assertSame('Expired on shelf', $disposal->reason);
        $this->assertSame($this->admin->id, $disposal->disposed_by);

        $this->assertDatabaseHas('inventory_transactions', [
            'medicine_id' => $medicine->id, 'batch_id' => $expired->id,
            'transaction_type' => 'disposed', 'quantity' => -8, 'before_quantity' => 13, 'after_quantity' => 5,
        ]);

        $this->actingAs($this->admin)
            ->get(route('disposals.index', ['medicine_id' => $medicine->id, 'year' => now()->year]))
            ->assertOk()->assertSee('Disposol 500mg')->assertSee('Expired on shelf');

        $csv = $this->actingAs($this->admin)->get(route('disposals.export'));
        $csv->assertOk();
        $this->assertStringContainsString('Expired on shelf', $csv->streamedContent());
    }

    public function test_dispose_requires_permission_and_reason_and_cannot_repeat(): void
    {
        [$medicine, $expired] = $this->setUpStock();

        // staff has no dispose-medicines
        $this->actingAs($this->userWithRole('staff'))
            ->post(route('disposals.store', $expired), ['reason' => 'Expired'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('disposals.store', $expired), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertSame(8, $expired->fresh()->quantity);

        $this->actingAs($this->admin)->post(route('disposals.store', $expired), ['reason' => 'Expired']);
        $this->actingAs($this->admin)->post(route('disposals.store', $expired), ['reason' => 'Expired'])->assertSessionHas('error');
        $this->assertSame(1, MedicineDisposal::count());
        $this->assertSame(5, $medicine->fresh()->quantity);
    }

    public function test_disposal_export_requires_export_permission(): void
    {
        // viewer: view-inventory but not export-reports
        $viewer = $this->userWithRole('viewer');
        $this->actingAs($viewer)->get(route('disposals.index'))->assertOk();
        $this->actingAs($viewer)->get(route('disposals.export'))->assertForbidden();
    }

    public function test_stock_out_uses_fefo_and_refuses_expired_batches(): void
    {
        [$medicine, $expired, $good] = $this->setUpStock();

        $this->actingAs($this->admin)
            ->post(route('inventory.stock-out'), ['medicine_id' => $medicine->id, 'batch_id' => $expired->id, 'quantity' => 1, 'notes' => 'Broken'])
            ->assertSessionHasErrors('quantity');

        $this->actingAs($this->admin)
            ->post(route('inventory.stock-out'), ['medicine_id' => $medicine->id, 'quantity' => 2, 'notes' => 'Broken'])
            ->assertSessionHasNoErrors();

        $this->assertSame(8, $expired->fresh()->quantity);
        $this->assertSame(3, $good->fresh()->quantity);
        $this->assertSame(11, $medicine->fresh()->quantity);
        $this->assertSame($good->id, InventoryTransaction::where('transaction_type', 'stock_out')->value('batch_id'));
    }
}
