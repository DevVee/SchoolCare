<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\PatientLog;
use App\Models\SmsLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Clinic logbook visits: saving a visit together with the medicines given
 * (one transaction, stock deducted FEFO) and discharging patients.
 */
class PatientLogService
{
    public function __construct(
        private readonly DispensingService $dispensing,
        private readonly SmsService $sms,
    ) {}

    /**
     * Create a visit and dispense its medicines atomically. If any medicine
     * cannot be dispensed the visit is not saved either.
     *
     * @param  array<int, array{medicine_id:int, quantity:int, row:int|string}>  $medicines
     *
     * @throws StockException
     */
    public function create(array $attributes, array $medicines = []): PatientLog
    {
        return DB::transaction(function () use ($attributes, $medicines) {
            $log = PatientLog::create($attributes);
            $this->dispenseRows($log, $medicines);

            return $log;
        });
    }

    /**
     * Update a visit and dispense any newly added medicines atomically.
     * Already-given medicines are kept; if the patient changes, their
     * dispensing records move with the visit.
     *
     * @throws StockException
     */
    public function update(PatientLog $log, array $attributes, array $newMedicines = []): PatientLog
    {
        return DB::transaction(function () use ($log, $attributes, $newMedicines) {
            $log->update($attributes);

            if ($log->wasChanged('patient_id')) {
                $log->dispensingRecords()->update(['patient_id' => $log->patient_id]);
            }

            $this->dispenseRows($log, $newMedicines);

            return $log;
        });
    }

    /**
     * Discharge a patient who is still in the clinic: records the time out,
     * optionally a final disposition, and sends the guardian discharge SMS
     * when enabled in Settings (SmsService checks the toggles).
     */
    public function discharge(PatientLog $log, ?string $disposition = null, bool $notifyGuardian = true): ?SmsLog
    {
        if ($log->time_out !== null) {
            throw new \RuntimeException('This patient has already been discharged.');
        }

        $now    = now();
        $timeIn = Carbon::parse($log->log_date->toDateString().' '.$log->time_in);
        // A time out can never be before the time in (e.g. a time in typed ahead).
        $timeOut = $log->log_date->isToday() && $now->lt($timeIn) ? $timeIn : $now;

        $log->update(array_filter([
            'time_out'    => $timeOut->format('H:i'),
            'disposition' => $disposition ?: null,
        ], fn ($v) => $v !== null));

        return $notifyGuardian ? $this->sms->sendDischargeNotice($log) : null;
    }

    /** @throws StockException */
    private function dispenseRows(PatientLog $log, array $rows): void
    {
        foreach ($rows as $row) {
            try {
                $this->dispensing->dispense([
                    'patient_id'     => $log->patient_id,
                    'patient_log_id' => $log->id,
                    'medicine_id'    => (int) $row['medicine_id'],
                    'quantity'       => (int) $row['quantity'],
                    'dispensed_at'   => now(),
                    'remarks'        => 'Given during clinic visit',
                ]);
            } catch (\RuntimeException $e) {
                throw new StockException("medicines.{$row['row']}.quantity", $e->getMessage(), $e);
            }
        }
    }
}
