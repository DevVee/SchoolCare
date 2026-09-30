<?php

namespace App\Observers;

use App\Models\Patient;
use App\Services\AuditLogService;

class PatientObserver
{
    /**
     * HIGH-7 FIX: Sensitive PHI fields are excluded from audit log new/old values.
     * The description still records who was affected; the sensitive clinical and
     * contact details are omitted so audit_logs are not a PHI data store.
     * Medical staff with full access view PHI through the patient record itself.
     */
    private const SENSITIVE_FIELDS = [
        'blood_type', 'allergies', 'medical_conditions', 'notes',
        'guardian_name', 'guardian_relationship', 'guardian_contact', 'guardian_address',
        'emergency_contact_name', 'emergency_contact_number',
        'contact_number', 'email', 'address',
        'other_contact', 'guardian_facebook', 'pediatrician_name', 'pediatrician_contact', 'current_medications',
    ];

    private function sanitize(array $data): array
    {
        $filtered = array_diff_key($data, array_flip(self::SENSITIVE_FIELDS));
        if (count($data) !== count($filtered)) {
            $filtered['_phi_fields_omitted'] = true;
        }
        return $filtered;
    }

    public function created(Patient $patient): void
    {
        AuditLogService::log(
            action: 'created',
            module: 'patients',
            description: "Created patient: {$patient->full_name} ({$patient->patient_number})",
            newValues: $this->sanitize($patient->toArray()),
        );
    }

    public function updated(Patient $patient): void
    {
        $dirty = $patient->getDirty();

        if (empty($dirty)) {
            return;
        }

        $old = array_intersect_key($patient->getOriginal(), $dirty);

        AuditLogService::log(
            action: 'updated',
            module: 'patients',
            description: "Updated patient: {$patient->full_name} ({$patient->patient_number})",
            oldValues: $this->sanitize($old),
            newValues: $this->sanitize($dirty),
        );
    }

    public function deleted(Patient $patient): void
    {
        AuditLogService::log(
            action: 'deleted',
            module: 'patients',
            description: "Deleted patient: {$patient->full_name} ({$patient->patient_number})",
        );
    }

    public function restored(Patient $patient): void
    {
        AuditLogService::log(
            action: 'restored',
            module: 'patients',
            description: "Restored patient: {$patient->full_name} ({$patient->patient_number})",
        );
    }
}
