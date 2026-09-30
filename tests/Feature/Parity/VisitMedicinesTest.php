<?php

namespace Tests\Feature\Parity;

use App\Exceptions\StockException;
use App\Models\DispensingRecord;
use App\Models\InventoryTransaction;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Patient;
use App\Models\PatientLog;
use App\Services\InventoryService;
use App\Services\PatientLogService;
use Tests\Feature\Clinical\ClinicalTestCase;

class VisitMedicinesTest extends ClinicalTestCase
{
    /** Medicine with an expired lot (10), a lot expiring soon (5) and a later lot (5). */
    private function medicineWithThreeBatches(): array
    {
        $this->actingAs($this->admin);

        $medicine = Medicine::factory()->create([
            'quantity' => 10, 'batch_number' => 'OLD', 'expiration_date' => now()->subDays(3)->toDateString(),
        ]);
        $inventory = app(InventoryService::class);
        $inventory->stockIn($medicine, ['quantity' => 5, 'batch_number' => 'LATE', 'expiration_date' => now()->addYear()->toDateString()]);
        $inventory->stockIn($medicine, ['quantity' => 5, 'batch_number' => 'SOON', 'expiration_date' => now()->addMonths(2)->toDateString()]);

        return [
            $medicine->fresh(),
            MedicineBatch::where('batch_number', 'OLD')->firstOrFail(),
            MedicineBatch::where('batch_number', 'SOON')->firstOrFail(),
            MedicineBatch::where('batch_number', 'LATE')->firstOrFail(),
        ];
    }

    private function visitPayload(Patient $patient, array $medicines = []): array
    {
        return [
            'patient_id'   => $patient->id,
            'log_date'     => today()->toDateString(),
            'time_in'      => '09:00',
            'severity'     => 'Mild',
            'reasons'      => ['Headache', 'Fever'],
            'other_reason' => 'Felt faint in class',
            'disposition'  => 'rest_in_clinic',
            'medicines'    => $medicines,
        ];
    }

    public function test_visit_with_medicines_deducts_fefo_batches_and_skips_expired(): void
    {
        [$medicine, $old, $soon, $late] = $this->medicineWithThreeBatches();
        $this->assertSame(20, $medicine->quantity);
        $this->assertSame(10, $medicine->availableQuantity());

        $patient = Patient::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('patient-logs.store'), $this->visitPayload($patient, [
                ['medicine_id' => $medicine->id, 'quantity' => 7],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $log = PatientLog::firstOrFail();
        $this->assertSame('Mild', $log->severity);
        $this->assertSame(['Headache', 'Fever'], $log->reasons);
        $this->assertSame('Felt faint in class', $log->other_reason);
        $this->assertNull($log->chief_complaint);
        $this->assertSame('Headache, Fever, Felt faint in class', $log->complaint_summary);

        // FEFO: the batch expiring soonest is used up first, expired stock is never touched.
        $this->assertSame(10, $old->fresh()->quantity);
        $this->assertSame(0, $soon->fresh()->quantity);
        $this->assertSame(3, $late->fresh()->quantity);
        $this->assertSame(13, $medicine->fresh()->quantity);

        $record = DispensingRecord::firstOrFail();
        $this->assertSame($log->id, $record->patient_log_id);
        $this->assertSame(7, $record->quantity);

        $rows = InventoryTransaction::where('transaction_type', 'dispensed')->orderBy('id')->get();
        $this->assertSame([$soon->id, $late->id], $rows->pluck('batch_id')->all());
        $this->assertSame([-5, -2], $rows->pluck('quantity')->all());
        $this->assertTrue($rows->every(fn ($t) => $t->reference_id === $record->id));

        // Medicines appear on the visit page with the batch used.
        $this->actingAs($this->admin)->get(route('patient-logs.show', $log))
            ->assertOk()->assertSee($medicine->name)->assertSee('SOON');
    }

    public function test_insufficient_usable_stock_is_a_validation_error_and_nothing_is_saved(): void
    {
        [$medicine] = $this->medicineWithThreeBatches();
        $patient = Patient::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('patient-logs.store'), $this->visitPayload($patient, [
                ['medicine_id' => $medicine->id, 'quantity' => 11], // 10 usable (+10 expired)
            ]))
            ->assertSessionHasErrors('medicines.0.quantity');

        $this->assertSame(0, PatientLog::count());
        $this->assertSame(20, $medicine->fresh()->quantity);
        $this->assertSame(0, DispensingRecord::count());
    }

    public function test_failure_on_a_later_medicine_rolls_back_the_whole_visit(): void
    {
        $this->actingAs($this->admin);
        $ok      = Medicine::factory()->create(['quantity' => 10]);
        $short   = Medicine::factory()->create(['quantity' => 2]);
        $patient = Patient::factory()->create();

        try {
            // Bypasses request validation: the service itself must roll back.
            app(PatientLogService::class)->create([
                'patient_id' => $patient->id, 'logged_by' => $this->admin->id,
                'log_date' => today()->toDateString(), 'time_in' => '10:00',
                'severity' => 'Mild', 'reasons' => ['Cough'], 'disposition' => 'rest_in_clinic',
            ], [
                ['medicine_id' => $ok->id, 'quantity' => 4, 'row' => 0],
                ['medicine_id' => $short->id, 'quantity' => 5, 'row' => 1],
            ]);
            $this->fail('Expected a StockException.');
        } catch (StockException $e) {
            $this->assertSame('medicines.1.quantity', $e->field);
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertSame(0, PatientLog::count());
        $this->assertSame(0, DispensingRecord::count());
        $this->assertSame(10, $ok->fresh()->quantity);
        $this->assertSame(10, (int) MedicineBatch::where('medicine_id', $ok->id)->sum('quantity'));
        $this->assertSame(2, $short->fresh()->quantity);
    }

    public function test_a_reason_or_complaint_is_required_and_severity_is_validated(): void
    {
        $patient = Patient::factory()->create();
        $payload = $this->visitPayload($patient);
        unset($payload['reasons'], $payload['other_reason']);
        $payload['severity'] = 'Catastrophic';

        $this->actingAs($this->admin)
            ->post(route('patient-logs.store'), $payload)
            ->assertSessionHasErrors(['reasons', 'severity']);

        $this->assertSame(0, PatientLog::count());
    }

    public function test_edit_adds_new_medicines_and_keeps_given_ones(): void
    {
        $this->actingAs($this->admin);
        $medicine = Medicine::factory()->create(['quantity' => 10]);
        $log = PatientLog::factory()->create(['severity' => 'Mild', 'reasons' => ['Cough']]);

        $this->put(route('patient-logs.update', $log), [
            'patient_id'  => $log->patient_id,
            'log_date'    => $log->log_date->toDateString(),
            'time_in'     => '08:15',
            'severity'    => 'Moderate',
            'reasons'     => ['Cough'],
            'disposition' => 'sent_home',
            'medicines'   => [['medicine_id' => $medicine->id, 'quantity' => 3]],
        ])->assertSessionHasNoErrors()->assertRedirect(route('patient-logs.show', $log));

        $this->assertSame('Moderate', $log->fresh()->severity);
        $this->assertSame(7, $medicine->fresh()->quantity);
        $this->assertSame(1, $log->dispensingRecords()->count());
    }

    public function test_logbook_filters_by_severity_reason_and_date_range(): void
    {
        // time_out set so neither shows in the "currently in clinic" panel.
        PatientLog::factory()->create(['severity' => 'Severe', 'reasons' => ['Fever'], 'time_out' => '09:00', 'log_date' => today()->subDays(2)->toDateString(),
            'patient_id' => Patient::factory()->create(['last_name' => 'Severecase'])->id]);
        PatientLog::factory()->create(['severity' => 'Mild', 'reasons' => ['Headache'], 'time_out' => '09:00', 'log_date' => today()->toDateString(),
            'patient_id' => Patient::factory()->create(['last_name' => 'Mildcase'])->id]);

        $range = ['date_from' => today()->subDays(7)->toDateString(), 'date_to' => today()->toDateString()];

        $this->actingAs($this->admin)
            ->get(route('patient-logs.index', $range + ['severity' => 'Severe']))
            ->assertOk()->assertSee('Severecase')->assertDontSee('Mildcase');

        $this->actingAs($this->admin)
            ->get(route('patient-logs.index', $range + ['reason' => 'Headache']))
            ->assertOk()->assertSee('Mildcase')->assertDontSee('Severecase');

        // Default view is today only.
        $this->actingAs($this->admin)
            ->get(route('patient-logs.index'))
            ->assertOk()->assertSee('Mildcase')->assertDontSee('Severecase');
    }
}
