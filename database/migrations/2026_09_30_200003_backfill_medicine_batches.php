<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: every medicine that holds stock gets one batch carrying its
 * current batch_number, expiration_date, supplier and quantity, so
 * medicines.quantity == SUM(non-disposed batches) from day one.
 *
 * Idempotent: medicines that already have a batch are skipped, and only the
 * stock not yet covered by batches is backfilled. Soft-deleted medicines are
 * included so their ledger stays consistent if they are restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('medicines')
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($medicines) use ($now) {
                foreach ($medicines as $m) {
                    $covered = (int) DB::table('medicine_batches')
                        ->where('medicine_id', $m->id)
                        ->whereNull('disposed_at')
                        ->sum('quantity');

                    $missing = (int) $m->quantity - $covered;
                    if ($missing <= 0) {
                        continue;
                    }

                    DB::table('medicine_batches')->insert([
                        'medicine_id'      => $m->id,
                        'batch_number'     => $m->batch_number,
                        'expiry_date'      => $m->expiration_date ? substr((string) $m->expiration_date, 0, 10) : null,
                        'quantity'         => $missing,
                        'initial_quantity' => $missing,
                        'received_at'      => $m->created_at ? substr((string) $m->created_at, 0, 10) : $now->toDateString(),
                        'unit_cost'        => $m->purchase_price ?? null,
                        'supplier'         => $m->supplier,
                        'created_by'       => null,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Batches are the source of truth from here on; nothing to undo.
    }
};
