<?php

namespace Tests\Feature\Clinical;

use App\Models\SmsLog;

class IndexFiltersTest extends ClinicalTestCase
{
    public function test_sms_search_does_not_bypass_status_filter(): void
    {
        SmsLog::create(['recipient_number' => '09170000001', 'recipient_name' => 'Maria Sent', 'message' => 'Hello there', 'status' => 'sent']);
        SmsLog::create(['recipient_number' => '09170000002', 'recipient_name' => 'Maria Failed', 'message' => 'Hello there', 'status' => 'failed']);

        $this->actingAs($this->admin)
            ->get(route('sms.index', ['search' => 'Maria', 'status' => 'sent']))
            ->assertOk()
            ->assertSee('Maria Sent')
            ->assertDontSee('Maria Failed');
    }

    public function test_index_and_form_pages_render_with_filters(): void
    {
        $medicine = \App\Models\Medicine::factory()->create(['batch_number' => 'LOT-1']);
        $patient  = \App\Models\Patient::factory()->create();
        \App\Models\DispensingRecord::factory()->create(['patient_id' => $patient->id, 'medicine_id' => $medicine->id]);
        \App\Models\PatientLog::factory()->create(['patient_id' => $patient->id]);
        $medicine->inventoryTransactions()->create([
            'transaction_type' => 'stock_in', 'quantity' => 5, 'before_quantity' => 0, 'after_quantity' => 5,
        ]);

        $urls = [
            route('medicines.index', ['search' => 'LOT', 'status' => 'inactive', 'stock' => 'expiring']),
            route('medicines.create'),
            route('medicines.edit', $medicine),
            route('medicines.show', $medicine),
            route('medicines.expiring', ['days' => 14, 'page' => 1]),
            route('medicines.low-stock'),
            route('medicine-categories.index'),
            route('inventory.index', ['status' => 'all', 'search' => 'LOT']),
            route('inventory.transactions', ['search' => 'LOT', 'type' => 'stock_in']),
            route('inventory.stock-in.form'),
            route('inventory.stock-out.form'),
            route('patients.index', ['search' => $patient->last_name, 'is_active' => '1']),
            route('patients.edit', $patient),
            route('patients.show', $patient),
            route('patient-logs.index', ['search' => $patient->last_name, 'disposition' => 'returned_to_class']),
            route('patient-logs.create'),
            route('dispensing.index', ['search' => $medicine->name, 'medicine_id' => $medicine->id]),
            route('dispensing.create'),
            route('consultations.index', ['search' => 'Headache']),
            route('consultations.create'),
            route('appointments.index', ['search' => $patient->last_name, 'status' => 'pending']),
            route('appointments.create'),
            route('reports.index'),
            route('reports.export', ['type' => 'daily', 'format' => 'pdf']),
            route('reports.export', ['type' => 'annual', 'format' => 'pdf']),
            route('reports.export', ['type' => 'annual', 'format' => 'csv']),
            route('reports.export', ['type' => 'monthly', 'format' => 'csv']),
            route('reports.export', ['type' => 'appointments', 'format' => 'csv']),
        ];

        foreach ($urls as $url) {
            $response = $this->actingAs($this->admin)->get($url);
            $this->assertSame(200, $response->getStatusCode(), "GET {$url} returned {$response->getStatusCode()}");
        }
    }

    public function test_health_endpoint_does_not_leak_exception_details(): void
    {
        $json = $this->getJson('/health')->json();

        $this->assertArrayNotHasKey('database_error', $json);
    }
}
