<?php

namespace Tests\Feature\Parity;

use App\Models\Appointment;
use App\Models\Patient;

class PublicBookingTest extends ParityTestCase
{
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'requester_name'    => 'Carla Mendoza',
            'requester_contact' => '09171234567',
            'appointment_date'  => $this->nextWeekday()->toDateString(),
            'appointment_time'  => '09:00:00',
            'purpose'           => 'General Checkup',
            'category'          => 'college',
            'consent'           => '1',
        ];
    }

    public function test_disabled_shows_the_closed_notice_and_stores_nothing(): void
    {
        settings()->set('public_booking_enabled', false);
        settings()->set('public_booking_closed_message', 'Online booking is paused for the semester break.');

        $this->get(route('public.appointments.create'))->assertOk()
            ->assertSee('Online requests are closed')
            ->assertSee('Online booking is paused for the semester break.')
            ->assertDontSee('name="requester_name"', false);
        $this->post(route('public.appointments.store'), $this->payload())->assertRedirect(route('public.appointments.create'));
        $this->get(route('public.appointments.thanks'))->assertRedirect(route('public.appointments.create'));
        $this->get(route('public.schedule'))->assertNotFound();
        $this->getJson(route('public.appointments.slots', ['date' => today()->toDateString()]))->assertNotFound();
        $this->assertSame(0, Appointment::count());
    }

    public function test_request_creates_pending_online_appointment_without_patient(): void
    {
        settings()->set('public_booking_enabled', true);

        $this->get(route('public.appointments.create'))->assertOk()->assertSee('Request an appointment');
        $this->get(route('public.schedule'))->assertOk()->assertSee('Clinic schedule');

        $this->post(route('public.appointments.store'), $this->payload(['requester_student_id' => 'S-1']))
            ->assertRedirect(route('public.appointments.thanks'));

        $appt = Appointment::sole();
        $this->assertNull($appt->patient_id);
        $this->assertSame('pending', $appt->status);
        $this->assertSame('online', $appt->source);
        $this->assertSame('Carla Mendoza', $appt->requester_name);
        $this->assertNull($appt->created_by);

        $this->get(route('public.appointments.thanks'))->assertOk()->assertSee('Request sent');

        // Staff see it with the online badge.
        $this->actingAs($this->admin)->get(route('appointments.index'))->assertOk()
            ->assertSee('Online request')->assertSee('Carla Mendoza');
        $this->actingAs($this->admin)->get(route('appointments.show', $appt))->assertOk()
            ->assertSee('Not linked to a patient record yet');
    }

    public function test_honeypot_stores_nothing(): void
    {
        settings()->set('public_booking_enabled', true);

        $this->post(route('public.appointments.store'), $this->payload(['website' => 'http://spam.example']))
            ->assertRedirect(route('public.appointments.thanks'));

        $this->assertSame(0, Appointment::count());
    }

    public function test_date_rules_are_enforced(): void
    {
        settings()->set('public_booking_enabled', true);

        $this->post(route('public.appointments.store'), $this->payload(['appointment_date' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors('appointment_date');

        $saturday = today()->next(\Carbon\Carbon::SATURDAY)->toDateString(); // closed by default
        $this->post(route('public.appointments.store'), $this->payload(['appointment_date' => $saturday]))
            ->assertSessionHasErrors('appointment_date');

        $this->post(route('public.appointments.store'), $this->payload(['requester_contact' => '12345']))
            ->assertSessionHasErrors('requester_contact');

        $this->assertSame(0, Appointment::count());
    }

    public function test_daily_limit_and_slot_capacity_apply(): void
    {
        settings()->set('public_booking_enabled', true);
        settings()->set('max_daily_appointments', 1);

        $this->post(route('public.appointments.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('public.appointments.store'), $this->payload(['appointment_time' => '10:00:00', 'requester_name' => 'Second Person']))
            ->assertSessionHasErrors('appointment_date');

        $this->assertSame(1, Appointment::count());
    }

    public function test_requests_are_throttled_per_ip(): void
    {
        settings()->set('public_booking_enabled', true);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('public.appointments.store'), $this->payload(['website' => 'bot']))->assertRedirect();
        }

        $this->post(route('public.appointments.store'), $this->payload())->assertStatus(429);
        $this->assertSame(0, Appointment::count());
    }

    public function test_approving_requires_linking_a_patient(): void
    {
        settings()->set('public_booking_enabled', true);
        $this->post(route('public.appointments.store'), $this->payload(['requester_student_id' => 'S-9']));
        $appt = Appointment::sole();

        $this->actingAs($this->admin)
            ->patch(route('appointments.approve', $appt))
            ->assertSessionHas('error');
        $this->assertSame('pending', $appt->fresh()->status);
        $this->assertFalse($this->admin->can('approve', $appt->fresh()));

        // Possible match by student ID shows on the request page.
        $patient = Patient::factory()->create(['student_id' => 'S-9', 'first_name' => 'Carla', 'last_name' => 'Mendoza']);
        $this->actingAs($this->admin)->get(route('appointments.show', $appt))->assertOk()->assertSee($patient->patient_number);

        $this->actingAs($this->admin)
            ->patch(route('appointments.link-patient', $appt), ['patient_id' => $patient->id])
            ->assertSessionHas('success');
        $this->assertSame($patient->id, $appt->fresh()->patient_id);

        $this->actingAs($this->admin)
            ->patch(route('appointments.approve', $appt->fresh()))
            ->assertSessionHas('success');
        $this->assertSame('approved', $appt->fresh()->status);
    }

    public function test_create_patient_from_request_links_it(): void
    {
        settings()->set('public_booking_enabled', true);
        $this->post(route('public.appointments.store'), $this->payload(['requester_student_id' => 'NEW-1']));
        $appt = Appointment::sole();

        $this->actingAs($this->admin)
            ->get(route('patients.create', ['from_appointment' => $appt->id]))
            ->assertOk()->assertSee('Carla')->assertSee('Mendoza')->assertSee('NEW-1');

        $this->actingAs($this->admin)->post(route('patients.store'), [
            'category' => 'college', 'first_name' => 'Carla', 'last_name' => 'Mendoza', 'sex' => 'female',
            'student_id' => 'NEW-1', 'link_appointment_id' => $appt->id,
        ])->assertRedirect(route('appointments.show', $appt));

        $this->assertNotNull($appt->fresh()->patient_id);
    }

    public function test_strong_match_links_automatically(): void
    {
        settings()->set('public_booking_enabled', true);
        $patient = Patient::factory()->create(['student_id' => 'MATCH-1', 'contact_number' => '09171234567']);

        $this->post(route('public.appointments.store'), $this->payload(['requester_student_id' => 'MATCH-1']));
        $this->assertSame($patient->id, Appointment::sole()->patient_id);
    }
}
