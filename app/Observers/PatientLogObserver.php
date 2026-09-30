<?php

namespace App\Observers;

use App\Models\PatientLog;
use App\Services\AuditLogService;

/**
 * Audit trail for clinic logbook entries (PHI). Clinical free-text and vitals
 * are omitted from the stored values; the log row itself is the source of truth.
 */
class PatientLogObserver
{
    private const SENSITIVE_FIELDS = [
        'chief_complaint', 'vital_signs', 'assessment', 'treatment', 'notes',
        'reasons', 'other_reason',
    ];

    private function sanitize(array $data): array
    {
        $filtered = array_diff_key($data, array_flip(self::SENSITIVE_FIELDS));
        if (count($data) !== count($filtered)) {
            $filtered['_clinical_fields_omitted'] = true;
        }

        return $filtered;
    }

    public function created(PatientLog $log): void
    {
        AuditLogService::log(
            action: 'created',
            module: 'patient_logs',
            description: "Clinic log #{$log->id} created for patient ID {$log->patient_id}",
            newValues: $this->sanitize($log->toArray()),
        );
    }

    public function updated(PatientLog $log): void
    {
        $dirty = $log->getChanges();
        unset($dirty['updated_at']);

        // sms_sent flips are bookkeeping, not clinical edits.
        if ($dirty === [] || array_keys($dirty) === ['sms_sent']) {
            return;
        }

        $old = array_intersect_key($log->getOriginal(), $dirty);

        AuditLogService::log(
            action: 'updated',
            module: 'patient_logs',
            description: "Clinic log #{$log->id} updated",
            oldValues: $this->sanitize($old),
            newValues: $this->sanitize($dirty),
        );
    }

    public function deleted(PatientLog $log): void
    {
        AuditLogService::log(
            action: 'deleted',
            module: 'patient_logs',
            description: "Clinic log #{$log->id} for patient ID {$log->patient_id} deleted",
        );
    }
}
