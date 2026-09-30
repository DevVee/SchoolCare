<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineDisposal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock movements, per batch (lot).
 *
 * Invariants, kept inside one DB transaction per movement:
 *  - medicines.quantity         == SUM(quantity) of the medicine's undisposed batches
 *  - medicines.expiration_date  == earliest expiry among batches that still hold stock
 *  - medicines.batch_number     == batch number of the first batch in FEFO order
 *  - every change writes one inventory_transactions row per batch touched
 *
 * Outgoing stock (dispensing, stock-out) is consumed First-Expiry-First-Out
 * and never from expired or disposed batches. Expired stock leaves only
 * through disposeBatch(). Decrements are conditional UPDATEs
 * (`quantity >= n`) so concurrent requests can never drive stock negative,
 * even on SQLite where SELECT ... FOR UPDATE is a no-op.
 */
class InventoryService
{
    // ─── Stock in ────────────────────────────────────────────────────────────

    /** Receive stock: creates a new batch and a stock_in ledger row. */
    public function stockIn(Medicine $medicine, array $data): InventoryTransaction
    {
        return DB::transaction(function () use ($medicine, $data) {
            $medicine = Medicine::withTrashed()->whereKey($medicine->id)->lockForUpdate()->firstOrFail();
            $this->reconcileUnbatched($medicine);

            $qty = (int) $data['quantity'];
            if ($qty < 1) {
                throw new \RuntimeException('Quantity must be at least 1.');
            }

            $unitCost = isset($data['unit_cost']) && $data['unit_cost'] !== ''
                ? $data['unit_cost']
                : $medicine->purchase_price;

            $batch = $medicine->batches()->create([
                'batch_number'     => $data['batch_number'] ?? null,
                'expiry_date'      => ! empty($data['expiration_date']) ? Carbon::parse($data['expiration_date'])->toDateString() : null,
                'quantity'         => $qty,
                'initial_quantity' => $qty,
                'received_at'      => ! empty($data['received_at']) ? Carbon::parse($data['received_at'])->toDateString() : today()->toDateString(),
                'unit_cost'        => $unitCost,
                'supplier'         => $data['supplier'] ?? null,
                'created_by'       => auth()->id(),
            ]);

            $before = (int) $medicine->quantity;
            Medicine::withTrashed()->whereKey($medicine->id)->increment('quantity', $qty);
            $after = (int) Medicine::withTrashed()->whereKey($medicine->id)->value('quantity');

            if (! empty($data['supplier'])) {
                Medicine::withTrashed()->whereKey($medicine->id)->update(['supplier' => $data['supplier']]);
            }

            $txn = InventoryTransaction::create([
                'medicine_id'      => $medicine->id,
                'batch_id'         => $batch->id,
                'transaction_type' => 'stock_in',
                'quantity'         => $qty,
                'before_quantity'  => $before,
                'after_quantity'   => $after,
                'batch_number'     => $batch->batch_number,
                'expiration_date'  => $batch->expiry_date,
                'supplier'         => $batch->supplier,
                'notes'            => $data['notes'] ?? null,
                'performed_by'     => auth()->id(),
            ]);

            $this->refreshCache($medicine);

            AuditLogService::log(
                action: 'created',
                module: 'inventory',
                description: "Stock in: +{$qty} {$medicine->unit}(s) of '{$medicine->name}'"
                    .($batch->batch_number ? " (batch {$batch->batch_number})" : '')
                    ." (was {$before}, now {$after})"
            );

            return $txn;
        });
    }

    // ─── Stock out ───────────────────────────────────────────────────────────

    /**
     * Remove stock (damage, loss, adjustment). Takes from one chosen batch,
     * or FEFO across usable batches when none is given. Expired batches are
     * refused: use disposeBatch() so the write-off is recorded as a disposal.
     *
     * @return InventoryTransaction the first ledger row written
     */
    public function stockOut(Medicine $medicine, array $data): InventoryTransaction
    {
        return DB::transaction(function () use ($medicine, $data) {
            $medicine = Medicine::withTrashed()->whereKey($medicine->id)->lockForUpdate()->firstOrFail();

            $batch = null;
            if (! empty($data['batch_id'])) {
                $batch = MedicineBatch::whereKey($data['batch_id'])->where('medicine_id', $medicine->id)->first();
                if (! $batch || $batch->is_disposed) {
                    throw new \RuntimeException('The selected batch is not available for this medicine.');
                }
                if ($batch->is_expired) {
                    throw new \RuntimeException("Batch {$batch->label} is expired. Use Dispose on the expiry page to write it off.");
                }
            }

            $rows = $this->consume($medicine, (int) $data['quantity'], 'stock_out', [
                'notes' => $data['notes'] ?? null,
            ], $batch);

            $qty   = (int) $data['quantity'];
            $first = $rows->first();
            $last  = $rows->last();

            AuditLogService::log(
                action: 'updated',
                module: 'inventory',
                description: "Stock out: -{$qty} {$medicine->unit}(s) of '{$medicine->name}' (was {$first->before_quantity}, now {$last->after_quantity})"
            );

            return $first;
        });
    }

    // ─── FEFO consumption ────────────────────────────────────────────────────

    /**
     * Take $quantity units of a medicine, earliest expiry first, and write one
     * ledger row per batch touched. MUST run inside a DB transaction (callers
     * wrap it); throws \RuntimeException on insufficient usable stock, in
     * which case the caller's transaction rolls everything back.
     *
     * @param  array{notes?:?string, reference?:?Model}  $context
     * @return Collection<int, InventoryTransaction>
     */
    public function consume(Medicine $medicine, int $quantity, string $type, array $context = [], ?MedicineBatch $only = null): Collection
    {
        if ($quantity < 1) {
            throw new \RuntimeException('Quantity must be at least 1.');
        }

        $this->reconcileUnbatched($medicine);

        $batches = $only
            ? collect([$only->fresh()])
            : $medicine->batches()->usable()->fefo()->lockForUpdate()->get();

        $available = (int) $batches->sum('quantity');
        if ($available < $quantity) {
            $expired = $medicine->expiredQuantity();
            throw new \RuntimeException(
                "Insufficient stock for \"{$medicine->name}\". Available: {$available} {$medicine->unit}(s), requested: {$quantity}."
                .($expired > 0 ? " {$expired} more {$medicine->unit}(s) are in expired batches and cannot be used." : '')
            );
        }

        $reference = $context['reference'] ?? null;
        $remaining = $quantity;
        $rows      = collect();

        foreach ($batches as $batch) {
            if ($remaining === 0) {
                break;
            }

            $take = min($remaining, (int) $batch->quantity);
            if ($take < 1) {
                continue;
            }

            $ok = MedicineBatch::whereKey($batch->id)->whereNull('disposed_at')
                ->where('quantity', '>=', $take)->decrement('quantity', $take);
            if ($ok === 0) {
                throw new \RuntimeException("Stock for \"{$medicine->name}\" changed while saving. Please try again.");
            }

            $ok = Medicine::withTrashed()->whereKey($medicine->id)
                ->where('quantity', '>=', $take)->decrement('quantity', $take);
            if ($ok === 0) {
                $left = (int) Medicine::withTrashed()->whereKey($medicine->id)->value('quantity');
                throw new \RuntimeException(
                    "Insufficient stock for \"{$medicine->name}\". Available: {$left} {$medicine->unit}(s), requested: {$quantity}."
                );
            }

            $after  = (int) Medicine::withTrashed()->whereKey($medicine->id)->value('quantity');
            $before = $after + $take;

            $rows->push(InventoryTransaction::create([
                'medicine_id'      => $medicine->id,
                'batch_id'         => $batch->id,
                'transaction_type' => $type,
                'quantity'         => -$take,
                'before_quantity'  => $before,
                'after_quantity'   => $after,
                'reference_id'     => $reference?->getKey(),
                'reference_type'   => $reference?->getMorphClass(),
                'batch_number'     => $batch->batch_number,
                'expiration_date'  => $batch->expiry_date,
                'notes'            => $context['notes'] ?? null,
                'performed_by'     => auth()->id(),
            ]));

            $remaining -= $take;
        }

        $this->refreshCache($medicine);

        return $rows;
    }

    // ─── Disposal ────────────────────────────────────────────────────────────

    /**
     * Dispose of everything left in a batch (expired, damaged, recalled).
     * Writes a 'disposed' ledger row and a medicine_disposals history row.
     */
    public function disposeBatch(MedicineBatch $batch, string $reason): MedicineDisposal
    {
        return DB::transaction(function () use ($batch, $reason) {
            $batch    = MedicineBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $medicine = Medicine::withTrashed()->whereKey($batch->medicine_id)->lockForUpdate()->firstOrFail();

            if ($batch->is_disposed) {
                throw new \RuntimeException('This batch has already been disposed of.');
            }

            $qty = (int) $batch->quantity;
            if ($qty < 1) {
                throw new \RuntimeException('This batch has no stock left to dispose of.');
            }

            $ok = MedicineBatch::whereKey($batch->id)->whereNull('disposed_at')->where('quantity', $qty)
                ->update(['quantity' => 0, 'disposed_at' => now()]);
            if ($ok === 0) {
                throw new \RuntimeException('The batch changed while saving. Please try again.');
            }

            // Never below zero, even if the cached total had drifted.
            $before = (int) $medicine->quantity;
            $after  = max(0, $before - $qty);
            Medicine::withTrashed()->whereKey($medicine->id)->update(['quantity' => $after]);

            $unitCost = $batch->unit_cost ?? $medicine->purchase_price;

            $disposal = MedicineDisposal::create([
                'medicine_id'       => $medicine->id,
                'medicine_batch_id' => $batch->id,
                'batch_number'      => $batch->batch_number,
                'expiry_date'       => $batch->expiry_date,
                'quantity'          => $qty,
                'unit_cost'         => $unitCost,
                'total_cost'        => $unitCost !== null ? round((float) $unitCost * $qty, 2) : null,
                'reason'            => $reason,
                'disposed_by'       => auth()->id(),
                'disposed_at'       => now(),
            ]);

            InventoryTransaction::create([
                'medicine_id'      => $medicine->id,
                'batch_id'         => $batch->id,
                'transaction_type' => 'disposed',
                'quantity'         => -$qty,
                'before_quantity'  => $before,
                'after_quantity'   => $after,
                'reference_id'     => $disposal->id,
                'reference_type'   => $disposal->getMorphClass(),
                'batch_number'     => $batch->batch_number,
                'expiration_date'  => $batch->expiry_date,
                'notes'            => 'Disposed: '.$reason,
                'performed_by'     => auth()->id(),
            ]);

            $this->refreshCache($medicine);

            AuditLogService::log(
                action: 'deleted',
                module: 'inventory',
                description: "Disposed {$qty} {$medicine->unit}(s) of '{$medicine->name}' ({$batch->label}). Reason: {$reason}",
                newValues: ['medicine_batch_id' => $batch->id, 'quantity' => $qty, 'disposal_id' => $disposal->id],
            );

            return $disposal;
        });
    }

    // ─── Batch corrections ───────────────────────────────────────────────────

    /** Correct a batch's details (not its quantity: that only moves through the ledger). */
    public function updateBatch(MedicineBatch $batch, array $data): MedicineBatch
    {
        return DB::transaction(function () use ($batch, $data) {
            $old = $batch->only(['batch_number', 'expiry_date', 'unit_cost', 'supplier']);

            $batch->update([
                'batch_number' => $data['batch_number'] ?? null,
                'expiry_date'  => $data['expiry_date'] ?? null,
                'unit_cost'    => ($data['unit_cost'] ?? '') === '' ? null : $data['unit_cost'],
                'supplier'     => $data['supplier'] ?? null,
            ]);

            $this->refreshCache($batch->medicine);

            AuditLogService::log(
                action: 'updated',
                module: 'inventory',
                description: "Batch #{$batch->id} of '{$batch->medicine?->name}' corrected",
                oldValues: array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $old),
                newValues: $batch->only(['batch_number', 'unit_cost', 'supplier']) + ['expiry_date' => $batch->expiry_date?->toDateString()],
            );

            return $batch;
        });
    }

    // ─── Cache / reconciliation ──────────────────────────────────────────────

    /**
     * Stock recorded on the medicine but not covered by any batch (legacy
     * rows or direct inserts) becomes a batch carrying the medicine's own
     * batch number and expiry, so FEFO can account for it.
     */
    public function reconcileUnbatched(Medicine $medicine): void
    {
        $batched = (int) $medicine->batches()->notDisposed()->sum('quantity');
        $current = (int) Medicine::withTrashed()->whereKey($medicine->id)->value('quantity');
        $missing = $current - $batched;

        if ($missing > 0) {
            $medicine->batches()->create([
                'batch_number'     => $medicine->batch_number,
                'expiry_date'      => $medicine->expiration_date,
                'quantity'         => $missing,
                'initial_quantity' => $missing,
                'received_at'      => today(),
                'unit_cost'        => $medicine->purchase_price,
                'supplier'         => $medicine->supplier,
                'created_by'       => auth()->id(),
            ]);
        }
    }

    /**
     * Recompute the cached expiry / batch number on the medicine from its
     * batches. Uses a query update so the medicine observer does not write
     * an audit row for every stock movement (the ledger already records it).
     */
    public function refreshCache(Medicine $medicine): void
    {
        $inStock = MedicineBatch::where('medicine_id', $medicine->id)->inStock()->fefo()->get(['id', 'batch_number', 'expiry_date']);

        $earliest = $inStock->whereNotNull('expiry_date')->sortBy(fn ($b) => $b->expiry_date->toDateString())->first();
        $first    = $inStock->first();

        Medicine::withTrashed()->whereKey($medicine->id)->update([
            'expiration_date' => $earliest?->expiry_date?->toDateString(),
            'batch_number'    => $first?->batch_number,
        ]);
    }
}
