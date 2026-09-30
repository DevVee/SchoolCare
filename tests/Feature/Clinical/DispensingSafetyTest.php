<?php

namespace Tests\Feature\Clinical;

use App\Models\DispensingRecord;
use App\Models\Medicine;
use App\Models\Patient;
use App\Services\DispensingService;

class DispensingSafetyTest extends ClinicalTestCase
{
    public function test_expired_medicine_cannot_be_dispensed(): void
    {
        $patient  = Patient::factory()->create();
        $medicine = Medicine::factory()->expired()->create(['quantity' => 50]);

        $this->actingAs($this->admin)
            ->post(route('dispensing.store'), [
                'patient_id'  => $patient->id,
                'medicine_id' => $medicine->id,
                'quantity'    => 1,
            ])
            ->assertSessionHasErrors('medicine_id');

        $this->assertSame(50, $medicine->fresh()->quantity);
        $this->assertSame(0, DispensingRecord::count());
    }

    public function test_inactive_medicine_cannot_be_dispensed(): void
    {
        $patient  = Patient::factory()->create();
        $medicine = Medicine::factory()->inactive()->create(['quantity' => 50]);

        $this->actingAs($this->admin)
            ->post(route('dispensing.store'), [
                'patient_id'  => $patient->id,
                'medicine_id' => $medicine->id,
                'quantity'    => 1,
            ])
            ->assertSessionHasErrors('medicine_id');

        $this->assertSame(50, $medicine->fresh()->quantity);
    }

    public function test_service_also_rejects_expired_medicine(): void
    {
        $this->actingAs($this->admin);
        $patient  = Patient::factory()->create();
        $medicine = Medicine::factory()->expired()->create(['quantity' => 5]);

        $this->expectException(\RuntimeException::class);
        app(DispensingService::class)->dispense([
            'patient_id' => $patient->id, 'medicine_id' => $medicine->id, 'quantity' => 1,
        ]);
    }

    public function test_insufficient_stock_is_rejected_with_validation_error(): void
    {
        $patient  = Patient::factory()->create();
        $medicine = Medicine::factory()->create(['quantity' => 3]);

        $this->actingAs($this->admin)
            ->post(route('dispensing.store'), [
                'patient_id'  => $patient->id,
                'medicine_id' => $medicine->id,
                'quantity'    => 4,
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(3, $medicine->fresh()->quantity);
    }

    public function test_valid_dispense_decrements_stock_and_writes_ledger(): void
    {
        $patient  = Patient::factory()->create();
        $medicine = Medicine::factory()->create(['quantity' => 10]);

        $this->actingAs($this->admin)
            ->post(route('dispensing.store'), [
                'patient_id'  => $patient->id,
                'medicine_id' => $medicine->id,
                'quantity'    => 4,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(6, $medicine->fresh()->quantity);
        $this->assertDatabaseHas('inventory_transactions', [
            'medicine_id' => $medicine->id, 'transaction_type' => 'dispensed',
            'quantity' => -4, 'before_quantity' => 10, 'after_quantity' => 6,
        ]);
    }

    public function test_repeated_dispensing_never_drives_stock_negative(): void
    {
        $this->actingAs($this->admin);
        $patient  = Patient::factory()->create();
        $medicine = Medicine::factory()->create(['quantity' => 5]);
        $service  = app(DispensingService::class);

        // Simulates a double submit: both requests ask for the full stock.
        $service->dispense(['patient_id' => $patient->id, 'medicine_id' => $medicine->id, 'quantity' => 5]);

        try {
            $service->dispense(['patient_id' => $patient->id, 'medicine_id' => $medicine->id, 'quantity' => 5]);
            $this->fail('Second dispense should have been rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertSame(0, $medicine->fresh()->quantity);
        $this->assertSame(1, DispensingRecord::count());
        $this->assertSame(0, Medicine::where('quantity', '<', 0)->count());
    }

    public function test_stale_model_cannot_overdraw_stock(): void
    {
        // Even if stock changed after the form/validation read it, the
        // conditional decrement refuses to go below zero.
        $this->actingAs($this->admin);
        $patient  = Patient::factory()->create();
        $medicine = Medicine::factory()->create(['quantity' => 5]);
        Medicine::whereKey($medicine->id)->update(['quantity' => 2]);

        $this->expectException(\RuntimeException::class);
        try {
            app(DispensingService::class)->dispense(['patient_id' => $patient->id, 'medicine_id' => $medicine->id, 'quantity' => 3]);
        } finally {
            $this->assertSame(2, $medicine->fresh()->quantity);
        }
    }

    public function test_create_form_lists_only_dispensable_medicines(): void
    {
        $ok       = Medicine::factory()->create(['name' => 'Goodmed 500mg', 'quantity' => 5]);
        $expired  = Medicine::factory()->expired()->create(['name' => 'Oldmed 500mg', 'quantity' => 5]);
        $inactive = Medicine::factory()->inactive()->create(['name' => 'Offmed 500mg', 'quantity' => 5]);
        $empty    = Medicine::factory()->create(['name' => 'Nomed 500mg', 'quantity' => 0]);

        $this->actingAs($this->admin)
            ->get(route('dispensing.create'))
            ->assertOk()
            ->assertSee('Goodmed 500mg')
            ->assertDontSee('Oldmed 500mg')
            ->assertDontSee('Offmed 500mg')
            ->assertDontSee('Nomed 500mg');
    }

    public function test_consultation_of_another_patient_cannot_be_linked(): void
    {
        $patient = Patient::factory()->create();
        $other   = \App\Models\Consultation::factory()->create();
        $medicine = Medicine::factory()->create(['quantity' => 5]);

        $this->actingAs($this->admin)
            ->post(route('dispensing.store'), [
                'patient_id'      => $patient->id,
                'consultation_id' => $other->id,
                'medicine_id'     => $medicine->id,
                'quantity'        => 1,
            ])
            ->assertSessionHasErrors('consultation_id');
    }
}
