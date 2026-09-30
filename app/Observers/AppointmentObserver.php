<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Services\AuditLogService;

class AppointmentObserver
{
    public function created(Appointment $appointment): void
    {
        AuditLogService::log(
            action: 'created',
            module: 'appointments',
            description: "Booked appointment for {$appointment->display_name} on " .
                         $appointment->appointment_date->format('M d, Y'),
            newValues: $appointment->toArray(),
        );
    }

    public function updated(Appointment $appointment): void
    {
        // Status changes are logged by AppointmentService; skip double-logging
        $statusOnly = array_keys($appointment->getDirty()) === ['status']
                   || array_keys($appointment->getDirty()) === ['status', 'approved_by', 'approved_at']
                   || array_keys($appointment->getDirty()) === ['status', 'cancelled_reason'];

        if ($statusOnly) {
            return;
        }

        $dirty = $appointment->getDirty();
        if (empty($dirty)) {
            return;
        }

        AuditLogService::log(
            action: 'updated',
            module: 'appointments',
            description: "Updated appointment #{$appointment->id}",
            oldValues: array_intersect_key($appointment->getOriginal(), $dirty),
            newValues: $dirty,
        );
    }

    public function deleted(Appointment $appointment): void
    {
        AuditLogService::log(
            action: 'deleted',
            module: 'appointments',
            description: "Deleted appointment #{$appointment->id} for {$appointment->display_name}",
        );
    }
}
