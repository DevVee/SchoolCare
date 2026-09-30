<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-appointments');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->can('view-appointments');
    }

    public function create(User $user): bool
    {
        return $user->can('create-appointments');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->can('update-appointments') && $appointment->isEditable();
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->can('delete-appointments');
    }

    public function approve(User $user, Appointment $appointment): bool
    {
        // Unlinked online requests must be linked to a patient before approval.
        return $user->can('approve-appointments') && $appointment->canTransitionTo('approved')
            && ! $appointment->needsPatientLink();
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->can('cancel-appointments') && $appointment->canTransitionTo('cancelled');
    }

    public function markNoShow(User $user, Appointment $appointment): bool
    {
        return $user->can('complete-appointments') && $appointment->canTransitionTo('no_show');
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $user->can('complete-appointments') && $appointment->canTransitionTo('completed');
    }
}
