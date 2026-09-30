<?php

namespace App\Services;

use App\Models\DispensingRecord;
use App\Models\Medicine;
use Illuminate\Support\Facades\DB;

class DispensingService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Dispense medicine to a patient inside a DB transaction.
     *
     * Stock is taken First-Expiry-First-Out from unexpired batches
     * (InventoryService::consume), writing one ledger row per batch used.
     * Throws \RuntimeException (rolled back, nothing written) when the
     * medicine is inactive, removed, only has expired stock, or has
     * insufficient usable stock. When called inside an outer transaction
     * (e.g. a logbook visit), the exception rolls back the whole visit.
     *
     * @param  array{patient_id:int, medicine_id:int, quantity:int, consultation_id?:?int, patient_log_id?:?int, remarks?:?string}  $data
     */
    public function dispense(array $data): DispensingRecord
    {
        $quantity = (int) $data['quantity'];

        if ($quantity < 1) {
            throw new \RuntimeException('Quantity must be at least 1.');
        }

        return DB::transaction(function () use ($data, $quantity) {

            // Lock the row where supported (MySQL/Postgres) to serialise writers.
            $medicine = Medicine::withTrashed()->lockForUpdate()->findOrFail($data['medicine_id']);

            if ($medicine->trashed() || ! $medicine->is_active) {
                throw new \RuntimeException("\"{$medicine->name}\" is inactive and cannot be dispensed.");
            }

            $this->inventory->reconcileUnbatched($medicine);

            if ($medicine->availableQuantity() === 0 && $medicine->expiredQuantity() > 0) {
                throw new \RuntimeException(
                    "All remaining stock of \"{$medicine->name}\" is expired and cannot be dispensed. Dispose of it from the Expiry page."
                );
            }

            $record = DispensingRecord::create([
                'patient_id'      => $data['patient_id'],
                'consultation_id' => $data['consultation_id'] ?? null,
                'patient_log_id'  => $data['patient_log_id'] ?? null,
                'medicine_id'     => $medicine->id,
                'quantity'        => $quantity,
                'dispensed_by'    => auth()->id(),
                'dispensed_at'    => $data['dispensed_at'] ?? now(),
                'remarks'         => $data['remarks'] ?? null,
            ]);

            $notes = "Dispensed to patient ID {$record->patient_id}"
                .($record->consultation_id ? ", consultation #{$record->consultation_id}" : '')
                .($record->patient_log_id ? ", clinic visit #{$record->patient_log_id}" : '');

            $this->inventory->consume($medicine, $quantity, 'dispensed', [
                'notes'     => $notes,
                'reference' => $record,
            ]);

            AuditLogService::log(
                action: 'created',
                module: 'dispensing',
                description: "Dispensed {$quantity} {$medicine->unit}(s) of \"{$medicine->name}\" to patient ID {$data['patient_id']}"
                    .($record->patient_log_id ? " (clinic visit #{$record->patient_log_id})" : '')
            );

            return $record;
        });
    }
}
