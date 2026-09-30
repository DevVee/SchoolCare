<?php

namespace Tests\Feature\Clinical;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\SmsLog;

class AppointmentTransitionTest extends ClinicalTestCase
{
    public function test_state_machine_definition(): void
    {
        $a = new Appointment(['status' => 'pending']);
        $this->assertTrue($a->canTransitionTo('approved'));
        $this->assertTrue($a->canTransitionTo('cancelled'));
        $this->assertFalse($a->canTransitionTo('completed'));
        $this->assertFalse($a->canTransitionTo('no_show'));

        $a->status = 'approved';
        $this->assertTrue($a->canTransitionTo('completed'));
        $this->assertTrue($a->canTransitionTo('no_show'));
        $this->assertTrue($a->canTransitionTo('cancelled'));
        $this->assertFalse($a->canTransitionTo('approved'));

        foreach (['completed', 'cancelled', 'no_show'] as $terminal) {
            $a->status = $terminal;
            $this->assertTrue($a->isTerminal());
            foreach (Appointment::statuses() as $to) {
                $this->assertFalse($a->canTransitionTo($to), "{$terminal} -> {$to} must be rejected");
            }
        }
    }

    public function test_approving_a_cancelled_appointment_is_rejected(): void
    {
        $appt = Appointment::factory()->status('cancelled')->create();

        $this->actingAs($this->admin)
            ->from(route('appointments.show', $appt))
            ->patch(route('appointments.approve', $appt))
            ->assertRedirect(route('appointments.show', $appt))
            ->assertSessionHas('error');

        $this->assertSame('cancelled', $appt->fresh()->status);
    }

    public function test_completing_or_no_show_of_pending_appointment_is_rejected(): void
    {
        $appt = Appointment::factory()->create();

        $this->actingAs($this->admin)->patch(route('appointments.complete', $appt))->assertSessionHas('error');
        $this->actingAs($this->admin)->patch(route('appointments.no-show', $appt))->assertSessionHas('error');

        $this->assertSame('pending', $appt->fresh()->status);
    }

    public function test_cancelling_a_completed_appointment_is_rejected(): void
    {
        $appt = Appointment::factory()->status('completed')->create();

        $this->actingAs($this->admin)
            ->patch(route('appointments.cancel', $appt), ['cancelled_reason' => 'Oops'])
            ->assertSessionHas('error');

        $this->assertSame('completed', $appt->fresh()->status);
    }

    public function test_double_approve_does_not_resend_sms(): void
    {
        $appt = Appointment::factory()->create();

        $this->actingAs($this->admin)->patch(route('appointments.approve', $appt))->assertSessionHas('success');
        $this->assertSame('approved', $appt->fresh()->status);
        $smsAfterFirst = SmsLog::count();

        $this->actingAs($this->admin)->patch(route('appointments.approve', $appt))->assertSessionHas('error');
        $this->assertSame($smsAfterFirst, SmsLog::count());
    }

    public function test_valid_flow_pending_approved_completed(): void
    {
        $appt = Appointment::factory()->create();

        $this->actingAs($this->admin)->patch(route('appointments.approve', $appt))->assertSessionHas('success');
        $this->actingAs($this->admin)->patch(route('appointments.complete', $appt))->assertSessionHas('success');

        $this->assertSame('completed', $appt->fresh()->status);
    }

    public function test_terminal_appointments_cannot_be_edited(): void
    {
        $appt = Appointment::factory()->status('completed')->create();

        $this->actingAs($this->admin)
            ->get(route('appointments.edit', $appt))
            ->assertRedirect(route('appointments.show', $appt))
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->put(route('appointments.update', $appt), [
                'patient_id'       => $appt->patient_id,
                'appointment_date' => today()->addDays(5)->toDateString(),
                'appointment_time' => '10:00:00',
                'purpose'          => 'Changed',
            ])
            ->assertSessionHas('error');

        $this->assertSame('Check-up', $appt->fresh()->purpose);
    }

    public function test_policies_follow_the_state_machine(): void
    {
        $cancelled = Appointment::factory()->status('cancelled')->create();
        $approved  = Appointment::factory()->status('approved')->create();

        $this->assertFalse($this->admin->can('approve', $cancelled));
        $this->assertFalse($this->admin->can('cancel', $cancelled));
        $this->assertFalse($this->admin->can('update', $cancelled));
        $this->assertTrue($this->admin->can('complete', $approved));
        $this->assertTrue($this->admin->can('cancel', $approved));
    }

    public function test_consultation_from_appointment_preselects_and_completes_it(): void
    {
        $patient = Patient::factory()->create();
        $appt    = Appointment::factory()->status('approved')->create(['patient_id' => $patient->id]);

        $this->actingAs($this->admin)
            ->get(route('consultations.create', ['appointment_id' => $appt->id]))
            ->assertOk()
            ->assertSee('const oldAppt       = "' . $appt->id . '"', false) // appointment preselected
            ->assertSee($appt->appointment_date->format('M d, Y'));           // formatted server-side

        $this->actingAs($this->admin)
            ->post(route('consultations.store'), [
                'patient_id'      => $patient->id,
                'appointment_id'  => $appt->id,
                'visit_date'      => today()->toDateString(),
                'chief_complaint' => 'Follow-up',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('completed', $appt->fresh()->status);
    }

    public function test_consultation_cannot_complete_an_appointment_of_another_patient(): void
    {
        $patient = Patient::factory()->create();
        $foreign = Appointment::factory()->status('approved')->create();

        $this->actingAs($this->admin)
            ->post(route('consultations.store'), [
                'patient_id'      => $patient->id,
                'appointment_id'  => $foreign->id,
                'visit_date'      => today()->toDateString(),
                'chief_complaint' => 'Follow-up',
            ])
            ->assertSessionHasErrors('appointment_id');

        $this->assertSame('approved', $foreign->fresh()->status);
    }
}
