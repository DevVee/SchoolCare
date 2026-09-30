<?php

namespace App\Services;

use App\Exceptions\InvalidStatusTransition;
use App\Models\Appointment;
use App\Models\AppointmentTimeSlot;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(private readonly SmsService $sms) {}

    /**
     * Check if a time slot still has capacity on a given date.
     * Returns remaining slots (0 = full).
     */
    public function checkSlotAvailability(string $date, string $time, ?int $excludeId = null): int
    {
        $slot = AppointmentTimeSlot::where('slot_time', $time)
            ->where('is_active', true)
            ->first();

        if (!$slot) {
            return 0;
        }

        $booked = Appointment::whereDate('appointment_date', $date)
            ->where('appointment_time', $time)
            ->whereIn('status', ['pending', 'approved'])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->count();

        return max(0, $slot->max_appointments - $booked);
    }

    /**
     * Approve a pending appointment and send SMS notification.
     */
    public function approve(Appointment $appointment): void
    {
        // Online requests must be linked to a patient record before approval.
        if ($appointment->needsPatientLink()) {
            throw new InvalidStatusTransition('Link this online request to a patient record before approving it.');
        }

        $this->transition($appointment, 'approved');

        $appointment->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLogService::log(
            action: 'approved',
            module: 'appointments',
            description: "Approved appointment #{$appointment->id} for {$appointment->display_name} on " .
                         $appointment->appointment_date->format('M d, Y'),
        );

        // SMS/email (queued; never throws — see SMS log for the outcome)
        app(AppointmentNotifier::class)->notify('approved', $appointment);
    }

    /**
     * Cancel an appointment with a reason and send SMS notification.
     */
    public function cancel(Appointment $appointment, string $reason): void
    {
        $this->transition($appointment, 'cancelled');

        $appointment->update([
            'status'           => 'cancelled',
            'cancelled_reason' => $reason,
        ]);

        AuditLogService::log(
            action: 'cancelled',
            module: 'appointments',
            description: "Cancelled appointment #{$appointment->id} for {$appointment->display_name}. Reason: {$reason}",
        );

        // SMS/email (queued; never throws — see SMS log for the outcome)
        app(AppointmentNotifier::class)->notify('cancelled', $appointment, ['reason' => $reason]);
    }

    /**
     * Mark an appointment as no-show.
     */
    public function markNoShow(Appointment $appointment): void
    {
        $this->transition($appointment, 'no_show');

        $appointment->update(['status' => 'no_show']);

        AuditLogService::log(
            action: 'updated',
            module: 'appointments',
            description: "Marked appointment #{$appointment->id} as No Show for {$appointment->display_name}",
        );
    }

    /**
     * Mark an appointment as completed.
     */
    public function markCompleted(Appointment $appointment): void
    {
        $this->transition($appointment, 'completed');

        $appointment->update(['status' => 'completed']);

        AuditLogService::log(
            action: 'updated',
            module: 'appointments',
            description: "Marked appointment #{$appointment->id} as Completed for {$appointment->display_name}",
        );
    }

    /**
     * Complete an appointment because a consultation was recorded for it.
     * A pending appointment is implicitly approved by the attending nurse
     * (approved_by/approved_at are only set if not already approved).
     * Terminal appointments are left untouched.
     */
    public function completeFromConsultation(Appointment $appointment, ?int $nurseId): void
    {
        $appointment->refresh();

        if ($appointment->isTerminal()) {
            return;
        }

        $data = ['status' => 'completed'];
        if ($appointment->isPending()) {
            $data['approved_by'] = $nurseId;
            $data['approved_at'] = now();
        }

        $appointment->update($data);
    }

    /**
     * Guard a status change against the Appointment state machine.
     *
     * Re-reads the current status from the database (locking the row where
     * the driver supports it) so a double-submitted approve/cancel cannot
     * run twice — the second request sees the new status and is rejected,
     * which also prevents re-sending the SMS.
     *
     * @throws InvalidStatusTransition
     */
    private function transition(Appointment $appointment, string $to): void
    {
        $current = DB::table('appointments')
            ->where('id', $appointment->id)
            ->lockForUpdate()
            ->value('status') ?? $appointment->status;

        $appointment->status = $current;
        $appointment->syncOriginalAttribute('status');

        if (! $appointment->canTransitionTo($to)) {
            throw InvalidStatusTransition::for('appointment', $current, $to);
        }
    }
}
