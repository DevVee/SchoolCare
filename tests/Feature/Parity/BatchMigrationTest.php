<?php

namespace Tests\Feature\Parity;

use App\Models\MedicineBatch;
use App\Models\MedicineCategory;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Clinical\ClinicalTestCase;

class BatchMigrationTest extends ClinicalTestCase
{
    private function runBackfill(): void
    {
        $migration = require database_path('migrations/2026_09_30_200003_backfill_medicine_batches.php');
        $migration->up();
    }

    /** Insert a legacy medicine row directly (no model events, so no batch). */
    private function legacyMedicine(array $attrs): int
    {
        $category = MedicineCategory::factory()->create();

        return DB::table('medicines')->insertGetId($attrs + [
            'category_id' => $category->id, 'unit' => 'tablet', 'low_stock_threshold' => 10,
            'is_active' => true, 'created_at' => now()->subYear(), 'updated_at' => now()->subYear(),
        ]);
    }

    public function test_backfill_creates_one_batch_per_stocked_medicine_and_keeps_totals(): void
    {
        $a = $this->legacyMedicine(['name' => 'Legacy A', 'quantity' => 40, 'batch_number' => 'L-1',
            'expiration_date' => '2027-03-01', 'supplier' => 'Old Supplier']);
        $b = $this->legacyMedicine(['name' => 'Legacy B', 'quantity' => 7, 'batch_number' => null, 'expiration_date' => null]);
        $c = $this->legacyMedicine(['name' => 'Legacy C (empty)', 'quantity' => 0]);

        $this->runBackfill();

        foreach ([$a => 40, $b => 7] as $id => $qty) {
            $this->assertSame(1, MedicineBatch::where('medicine_id', $id)->count());
            $this->assertSame($qty, (int) MedicineBatch::where('medicine_id', $id)->sum('quantity'));
            $this->assertSame($qty, (int) DB::table('medicines')->where('id', $id)->value('quantity'));
        }
        $this->assertSame(0, MedicineBatch::where('medicine_id', $c)->count());

        $batch = MedicineBatch::where('medicine_id', $a)->first();
        $this->assertSame('L-1', $batch->batch_number);
        $this->assertSame('2027-03-01', $batch->expiry_date->toDateString());
        $this->assertSame('Old Supplier', $batch->supplier);
        $this->assertSame(40, $batch->initial_quantity);

        // Idempotent: running again adds nothing.
        $this->runBackfill();
        $this->assertSame(2, MedicineBatch::whereIn('medicine_id', [$a, $b, $c])->count());
        $this->assertSame(47, (int) MedicineBatch::whereIn('medicine_id', [$a, $b, $c])->sum('quantity'));
    }
}
