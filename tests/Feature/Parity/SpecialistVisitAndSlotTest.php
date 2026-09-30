<?php

namespace Tests\Feature\Parity;

use App\Models\Appointment;
use App\Models\AppointmentTimeSlot;
use App\Models\Patient;
use App\Models\SpecialistVisit;
use App\Services\AppointmentBooking;
use Illuminate\Validation\ValidationException;

class SpecialistVisitAndSlotTest extends ParityTestCase
{
    private function visitPayload(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'Dentist', 'specialist_name' => 'Dr. Molar',
            'visit_date' => $this->nextWeekday()->toDateString(),
            'start_time' => '08:00', 'end_time' => '11:00', 'capacity' => 2,
        ];
    }

    public function test_specialist_visit_permissions(): void
    {
        $staff = $this->userWithRole('staff');     // view only
        $nurse = $this->userWithRole('nurse');     // view + manage

        $this->actingAs($staff)->get(route('specialist-visits.index'))->assertOk();
        $this->actingAs($staff)->get(route('specialist-visits.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('specialist-visits.store'), $this->visitPayload())->assertForbidden();

        $noAccess = $this->userWithPermissions(['view-patients']);
        $this->actingAs($noAccess)->get(route('specialist-visits.index'))->assertForbidden();

        $this->actingAs($nurse)->post(route('specialist-visits.store'), $this->visitPayload())
            ->assertRedirect(route('specialist-visits.index'));
        $visit = SpecialistVisit::sole();
        $this->assertSame('08:00:00', $visit->start_time);

        $this->actingAs($staff)->get(route('specialist-visits.show', $visit))->assertOk()->assertSee('Dr. Molar');
        $this->actingAs($staff)->delete(route('specialist-visits.destroy', $visit))->assertForbidden();
    }

    public function test_overlapping_visit_for_same_specialist_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('specialist-visits.store'), $this->visitPayload())->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('specialist-visits.store'), $this->visitPayload(['start_time' => '10:00', 'end_time' => '12:00']))
            ->assertSessionHasErrors('start_time');

        $this->actingAs($this->admin)
            ->post(route('specialist-visits.store'), $this->visitPayload(['start_time' => '11:00', 'end_time' => '12:00']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('specialist-visits.store'), $this->visitPayload(['type' => 'Astrologer']))
            ->assertSessionHasErrors('type');
    }

    public function test_appointment_can_reference_a_specialist_visit_with_capacity(): void
    {
        $date  = $this->nextWeekday()->toDateString();
        $visit = SpecialistVisit::create($this->visitPayload(['visit_date' => $date, 'capacity' => 1, 'start_time' => '08:00:00', 'end_time' => '11:00:00']));
        [$p1, $p2] = Patient::factory()->count(2)->create();

        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'patient_id' => $p1->id, 'appointment_date' => $date, 'appointment_time' => '08:00:00',
            'purpose' => 'Dental check', 'specialist_visit_id' => $visit->id,
        ])->assertSessionHasNoErrors();

        $appt = Appointment::sole();
        $this->assertSame($visit->id, $appt->specialist_visit_id);
        $this->assertSame('Dentist', $appt->provider, 'Provider defaults to the visit type');

        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'patient_id' => $p2->id, 'appointment_date' => $date, 'appointment_time' => '08:30:00',
            'purpose' => 'Dental check', 'specialist_visit_id' => $visit->id,
        ])->assertSessionHasErrors('specialist_visit_id');

        // A visit on another date cannot be attached.
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'patient_id' => $p2->id, 'appointment_date' => $this->nextWeekday(3)->toDateString(), 'appointment_time' => '08:30:00',
            'purpose' => 'x', 'specialist_visit_id' => $visit->id,
        ])->assertSessionHasErrors('specialist_visit_id');

        // Deleting a visit with bookings is blocked.
        $this->actingAs($this->admin)->delete(route('specialist-visits.destroy', $visit))->assertSessionHas('error');
        $this->assertNotNull($visit->fresh());
    }

    public function test_slot_capacity_is_enforced(): void
    {
        $date = $this->nextWeekday()->toDateString();
        AppointmentTimeSlot::where('slot_time', '09:00:00')->update(['max_appointments' => 1]);
        [$p1, $p2] = Patient::factory()->count(2)->create();

        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'patient_id' => $p1->id, 'appointment_date' => $date, 'appointment_time' => '09:00:00', 'purpose' => 'Check-up',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'patient_id' => $p2->id, 'appointment_date' => $date, 'appointment_time' => '09:00:00', 'purpose' => 'Check-up',
        ])->assertSessionHasErrors('appointment_time');

        $this->assertSame(1, Appointment::count());

        // The service re-counts inside its transaction too.
        $this->expectException(ValidationException::class);
        app(AppointmentBooking::class)->book([
            'patient_id' => $p2->id, 'appointment_date' => $date, 'appointment_time' => '09:00:00',
            'purpose' => 'Race', 'status' => 'pending',
        ]);
    }

    public function test_slot_weekday_availability_and_availability_endpoint(): void
    {
        $monday = today()->next(\Carbon\Carbon::MONDAY);
        AppointmentTimeSlot::where('slot_time', '10:00:00')->update(['weekdays' => [3]]); // Wednesday only
        $patient = Patient::factory()->create();

        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'patient_id' => $patient->id, 'appointment_date' => $monday->toDateString(), 'appointment_time' => '10:00:00', 'purpose' => 'x',
        ])->assertSessionHasErrors('appointment_time');

        $json = $this->actingAs($this->admin)
            ->getJson(route('appointments.availability', ['date' => $monday->toDateString()]))
            ->assertOk()->json('slots');
        $this->assertNotContains('10:00:00', array_column($json, 'time'));
        $this->assertContains('09:00:00', array_column($json, 'time'));
    }

    public function test_slot_management_crud_and_in_use_protection(): void
    {
        $nurse = $this->userWithRole('nurse'); // no manage-appointment-slots by default
        $this->actingAs($nurse)->get(route('admin.appointment-slots.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.appointment-slots.index'))->assertOk();

        $this->actingAs($this->admin)->post(route('admin.appointment-slots.store'), [
            'label' => 'Late', 'slot_time' => '17:30', 'end_time' => '18:00', 'max_appointments' => 3,
            'weekdays' => [1, 2, 3, 4, 5], 'is_active' => '1',
        ])->assertRedirect(route('admin.appointment-slots.index'));

        $slot = AppointmentTimeSlot::where('slot_time', '17:30:00')->sole();
        $this->assertSame([1, 2, 3, 4, 5], $slot->weekdays);
        $this->assertSame('18:00:00', $slot->end_time);

        // Duplicate start time.
        $this->actingAs($this->admin)->post(route('admin.appointment-slots.store'), [
            'slot_time' => '17:30', 'end_time' => '18:00', 'max_appointments' => 1, 'is_active' => '1',
        ])->assertSessionHasErrors('slot_time');

        // In use: cannot delete, can deactivate.
        Appointment::factory()->create(['appointment_time' => '17:30:00']);
        $this->actingAs($this->admin)->delete(route('admin.appointment-slots.destroy', $slot))->assertSessionHas('error');
        $this->assertNotNull($slot->fresh());

        $this->actingAs($this->admin)->patch(route('admin.appointment-slots.toggle', $slot))->assertSessionHas('success');
        $this->assertFalse($slot->fresh()->is_active);

        // Unused slot can be deleted.
        $unused = AppointmentTimeSlot::where('slot_time', '07:00:00')->sole();
        $this->actingAs($this->admin)->delete(route('admin.appointment-slots.destroy', $unused))->assertSessionHas('success');
        $this->assertNull($unused->fresh());
    }

    public function test_cancel_reason_follows_the_setting(): void
    {
        $a = Appointment::factory()->create();
        settings()->set('appointment_cancel_reason_required', true);
        $this->actingAs($this->admin)->patch(route('appointments.cancel', $a), [])->assertSessionHasErrors('cancelled_reason');

        settings()->set('appointment_cancel_reason_required', false);
        $this->actingAs($this->admin)->patch(route('appointments.cancel', $a), [])->assertSessionHas('success');
        $this->assertSame('cancelled', $a->fresh()->status);
    }
}
