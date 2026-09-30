<?php

namespace Tests\Feature\Parity;

use App\Models\Appointment;
use App\Models\SpecialistVisit;
use App\Notifications\OnlineAppointmentRequestNotification;
use App\Services\AppointmentBooking;
use App\Services\OnlineBooking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * The public appointment request form and everything it takes from
 * Admin > Settings > Appointments: the dropdowns, which questions it asks,
 * the dates and times it offers, the online share of each slot, the closed
 * dates and specialist visit days, the privacy statement, the clinic email,
 * and the public footer.
 */
class PublicBookingFormTest extends ParityTestCase
{
    private Carbon $monday;

    protected function setUp(): void
    {
        parent::setUp();

        // A fixed Monday morning, so "today", the window and the times never depend on when the tests run.
        $this->monday = Carbon::parse('next monday')->setTime(8, 0);
        $this->travelTo($this->monday);

        settings()->set('public_booking_enabled', true);

        // These tests send many requests from one address (the limit is tested in PublicBookingTest).
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function day(int $offset): string
    {
        return $this->monday->copy()->addDays($offset)->toDateString();
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'requester_name'    => 'Carla Mendoza',
            'requester_contact' => '09171234567',
            'category'          => 'college',
            'appointment_date'  => $this->day(1), // Tuesday
            'appointment_time'  => '09:00:00',
            'purpose'           => 'General Checkup',
            'consent'           => '1',
        ];
    }

    // ─── The form ────────────────────────────────────────────────────────────

    public function test_form_dropdowns_come_from_settings(): void
    {
        settings()->set('appointment_purposes', ['Sports Clearance', 'Other (please specify)']);
        settings()->set('appointment_providers', ['School Nurse', 'Dentist']);
        settings()->set('patient_categories', ['college' => 'College', 'shs' => 'Senior High', 'staff' => 'Faculty and Staff']);

        $page = $this->get(route('public.appointments.create'))->assertOk();

        $page->assertSee('Sports Clearance')->assertSee('Other (please specify)')
            ->assertSee('School Nurse')->assertSee('Faculty and Staff')->assertSee('Senior High')
            ->assertDontSee('General Checkup');

        // Dates: open days only, labelled with the open times; the times of the first open date.
        $page->assertSee('Tomorrow, '.$this->monday->copy()->addDay()->format('l, F j'))
            ->assertDontSee($this->monday->copy()->addDays(5)->format('l, F j')) // Saturday: closed by default
            ->assertSee('value="09:00:00"', false)
            ->assertDontSee('value="07:00:00"', false); // starts before the clinic opens at 07:30

        // The grade / program / section lists travel with the page for the category dropdown.
        $page->assertSee('"byCategory"', false)->assertSee('BSIT');

        // Questions that are optional by default are shown; the privacy statement is required.
        $page->assertSee('name="requester_email"', false)
            ->assertSee('name="requester_student_id"', false)
            ->assertSee('name="consent"', false);
    }

    public function test_hidden_questions_are_not_asked(): void
    {
        foreach (['category', 'school', 'student_id', 'email', 'provider', 'details'] as $field) {
            settings()->set("public_booking_field_{$field}", 'hidden');
        }
        settings()->set('public_booking_consent_required', false);

        $this->get(route('public.appointments.create'))->assertOk()
            ->assertDontSee('name="category"', false)
            ->assertDontSee('name="year_level"', false)
            ->assertDontSee('name="requester_student_id"', false)
            ->assertDontSee('name="requester_email"', false)
            ->assertDontSee('name="provider"', false)
            ->assertDontSee('name="details"', false)
            ->assertDontSee('name="consent"', false)
            ->assertSee('By sending it you agree to the');

        // Values for hidden questions are ignored.
        $this->post(route('public.appointments.store'), $this->payload([
            'category' => 'not-a-category', 'provider' => 'Somebody', 'consent' => null,
        ]))->assertRedirect(route('public.appointments.thanks'));

        $appt = Appointment::sole();
        $this->assertNull($appt->requester_category);
        $this->assertNull($appt->provider);
    }

    public function test_required_questions_must_be_answered(): void
    {
        foreach (['student_id', 'email', 'provider', 'details', 'school'] as $field) {
            settings()->set("public_booking_field_{$field}", 'required');
        }

        $this->post(route('public.appointments.store'), $this->payload())
            ->assertSessionHasErrors(['requester_student_id', 'requester_email', 'provider', 'details', 'year_level', 'program_strand', 'section']);

        // Only the school lists that apply to the category are required (junior high has no programs).
        $this->post(route('public.appointments.store'), $this->payload(['category' => 'junior_high']))
            ->assertSessionHasErrors(['year_level', 'section'])
            ->assertSessionDoesntHaveErrors('program_strand');

        // A category with no school lists (teachers) needs none of them.
        $this->post(route('public.appointments.store'), $this->payload([
            'category' => 'teacher', 'requester_student_id' => 'T-1', 'requester_email' => 'carla@example.com',
            'provider' => 'Nurse', 'details' => 'Blood pressure check',
        ]))->assertRedirect(route('public.appointments.thanks'));

        $this->assertSame(1, Appointment::count());
    }

    public function test_choices_are_checked(): void
    {
        $this->post(route('public.appointments.store'), $this->payload(['category' => 'martian']))->assertSessionHasErrors('category');
        $this->post(route('public.appointments.store'), $this->payload(['purpose' => 'Haircut']))->assertSessionHasErrors('purpose');
        $this->post(route('public.appointments.store'), $this->payload(['purpose' => 'Other']))->assertSessionHasErrors('purpose_other');
        $this->post(route('public.appointments.store'), $this->payload(['provider' => 'Surgeon']))->assertSessionHasErrors('provider');
        $this->post(route('public.appointments.store'), $this->payload(['category' => 'junior_high', 'year_level' => '3rd Year']))
            ->assertSessionHasErrors('year_level');
        $this->post(route('public.appointments.store'), $this->payload(['consent' => null]))->assertSessionHasErrors('consent');

        $this->assertSame(0, Appointment::count());
    }

    public function test_valid_request_creates_a_pending_appointment_with_every_answer(): void
    {
        settings()->set('public_booking_success_message', 'Salamat! The nurse will text you soon.');

        $this->post(route('public.appointments.store'), $this->payload([
            'requester_email' => 'carla@example.com',
            'year_level'      => '2nd Year',
            'program_strand'  => 'BSIT',
            'section'         => 'Block 1',
            'purpose'         => 'Other',
            'purpose_other'   => 'Clearance for the school fair',
            'provider'        => 'Nurse',
            'details'         => 'Mild asthma',
        ]))->assertRedirect(route('public.appointments.thanks'));

        $appt = Appointment::sole();
        $this->assertSame('pending', $appt->status);
        $this->assertSame(Appointment::SOURCE_ONLINE, $appt->source);
        $this->assertSame('Clearance for the school fair', $appt->purpose);
        $this->assertSame('Nurse', $appt->provider);
        $this->assertSame('college', $appt->requester_category);
        $this->assertSame('2nd Year', $appt->requester_year_level);
        $this->assertSame('BSIT', $appt->requester_program);
        $this->assertSame('Block 1', $appt->requester_section);
        $this->assertSame('Mild asthma', $appt->notes);
        $this->assertSame($this->day(1), $appt->appointment_date->toDateString());

        $this->get(route('public.appointments.thanks'))->assertOk()
            ->assertSee('Salamat! The nurse will text you soon.')
            ->assertSee('Clearance for the school fair');

        // Staff see the answers, and a new patient starts from them.
        $this->actingAs($this->admin)->get(route('appointments.show', $appt))->assertOk()
            ->assertSee('College')->assertSee('2nd Year, BSIT, Block 1');
        $this->actingAs($this->admin)->get(route('patients.create', ['from_appointment' => $appt->id]))->assertOk()
            ->assertSee('value="2nd Year" selected', false);
    }

    // ─── Dates and times ─────────────────────────────────────────────────────

    public function test_dates_follow_the_window_closed_dates_and_capacity(): void
    {
        settings()->set('public_booking_days_ahead', 7);
        settings()->set('public_booking_closed_dates', [$this->day(2).' Foundation Day', 'not a date']);
        settings()->set('max_daily_appointments', 1);
        Appointment::factory()->create(['appointment_date' => $this->day(3), 'appointment_time' => '10:00:00', 'status' => 'approved']);

        $dates = collect(app(OnlineBooking::class)->dates())->keyBy('date');

        $this->assertSame([$this->day(0), $this->day(1), $this->day(2), $this->day(3), $this->day(4), $this->day(7)], $dates->keys()->all());
        $this->assertSame('Foundation Day', $dates[$this->day(2)]['closed']);
        $this->assertSame(0, $dates[$this->day(2)]['open']);
        $this->assertSame(0, $dates[$this->day(3)]['open']); // daily limit reached
        $this->assertGreaterThan(0, $dates[$this->day(1)]['open']);

        $this->get(route('public.appointments.create'))->assertOk()
            ->assertSee('(closed: Foundation Day)')
            ->assertSee($this->monday->copy()->addDays(3)->format('l, F j').' (full)');
    }

    public function test_slot_lookup_returns_the_open_times_for_a_date(): void
    {
        $json = $this->getJson(route('public.appointments.slots', ['date' => $this->day(1)]))->assertOk()->json();

        $this->assertTrue($json['open']);
        $times = collect($json['slots'])->pluck('time');
        $this->assertTrue($times->contains('09:00:00'));
        $this->assertFalse($times->contains('07:00:00'));  // before opening (07:30)
        $this->assertFalse($times->contains('17:00:00'));  // at closing time
        $this->assertSame(5, collect($json['slots'])->firstWhere('time', '09:00:00')['remaining']);

        // Today: times that already started are marked, not offered.
        $today = collect($this->getJson(route('public.appointments.slots', ['date' => $this->day(0)]))->json('slots'));
        $this->assertSame('past', $today->firstWhere('time', '07:30:00')['state']);
        $this->assertTrue($today->firstWhere('time', '09:00:00')['available']);

        // Closed days: no times, with the reason.
        $saturday = $this->getJson(route('public.appointments.slots', ['date' => $this->day(5)]))->json();
        $this->assertFalse($saturday['open']);
        $this->assertSame([], $saturday['slots']);
        $this->assertStringContainsString('closed', $saturday['message']);

        $this->getJson(route('public.appointments.slots', ['date' => 'soon']))->assertStatus(422);
    }

    public function test_online_requests_take_only_their_share_of_a_slot(): void
    {
        settings()->set('public_booking_slot_limit', 1);
        $date = $this->day(1);

        $this->post(route('public.appointments.store'), $this->payload())->assertRedirect(route('public.appointments.thanks'));
        $this->post(route('public.appointments.store'), $this->payload(['requester_name' => 'Second Person']))
            ->assertSessionHasErrors('appointment_time');

        $slots = collect($this->getJson(route('public.appointments.slots', ['date' => $date]))->json('slots'))->keyBy('time');
        $this->assertSame('full', $slots['09:00:00']['state']);
        $this->assertSame(1, $slots['10:00:00']['remaining']);

        // Staff bookings still use the rest of the slot.
        app(AppointmentBooking::class)->book([
            'patient_id' => \App\Models\Patient::factory()->create()->id,
            'appointment_date' => $date, 'appointment_time' => '09:00:00', 'purpose' => 'Checkup', 'status' => 'approved',
        ]);
        $this->assertSame(2, Appointment::count());

        // The booking itself refuses a second online request, even without the form.
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(AppointmentBooking::class)->book([
            'appointment_date' => $date, 'appointment_time' => '09:00:00', 'purpose' => 'Checkup', 'status' => 'pending',
            'source' => Appointment::SOURCE_ONLINE, 'requester_name' => 'Race', 'requester_contact' => '09170000000',
        ]);
    }

    public function test_unavailable_dates_and_times_are_rejected(): void
    {
        settings()->set('public_booking_closed_dates', [$this->day(2).' Foundation Day']);
        settings()->set('public_booking_days_ahead', 14);

        $post = fn (array $o) => $this->post(route('public.appointments.store'), $this->payload($o));

        $post(['appointment_date' => $this->day(2)])->assertSessionHasErrors('appointment_date');         // closed date
        $post(['appointment_date' => $this->day(5)])->assertSessionHasErrors('appointment_date');         // Saturday
        $post(['appointment_date' => $this->day(21)])->assertSessionHasErrors('appointment_date');        // past the window
        $post(['appointment_date' => $this->day(-1)])->assertSessionHasErrors('appointment_date');        // yesterday
        $post(['appointment_date' => '10/06/2026'])->assertSessionHasErrors('appointment_date');          // not from the list
        $post(['appointment_time' => '07:00:00'])->assertSessionHasErrors('appointment_time');            // before opening
        $post(['appointment_time' => '09:15:00'])->assertSessionHasErrors('appointment_time');            // not a slot
        $post(['appointment_date' => $this->day(0), 'appointment_time' => '07:30:00'])->assertSessionHasErrors('appointment_time'); // already started

        settings()->set('public_booking_min_notice_hours', 48);
        $post([])->assertSessionHasErrors('appointment_time'); // tomorrow 09:00 is less than 48 hours away
        $post(['appointment_date' => $this->day(3)])->assertRedirect(route('public.appointments.thanks'));

        $this->assertSame(1, Appointment::count());
    }

    public function test_specialists_can_be_limited_to_their_visit_days(): void
    {
        settings()->set('appointment_providers', ['Nurse', 'Dentist']);
        settings()->set('public_booking_specialist_days_only', true);
        $visit = SpecialistVisit::create([
            'type' => 'Dentist', 'specialist_name' => 'Dr. Reyes', 'visit_date' => $this->day(3),
            'start_time' => '13:00:00', 'end_time' => '15:00:00', 'status' => 'scheduled',
        ]);

        $this->assertSame(['Dentist'], app(OnlineBooking::class)->specialistProviders());

        // Not a dentist day.
        $this->getJson(route('public.appointments.slots', ['date' => $this->day(1), 'provider' => 'Dentist']))
            ->assertJson(['open' => false, 'slots' => []]);
        $this->post(route('public.appointments.store'), $this->payload(['provider' => 'Dentist']))
            ->assertSessionHasErrors('appointment_date');

        // The dentist day: only the visit hours.
        $times = collect($this->getJson(route('public.appointments.slots', ['date' => $this->day(3), 'provider' => 'Dentist']))->json('slots'))->pluck('time')->all();
        $this->assertSame(['13:00:00', '13:30:00', '14:00:00', '14:30:00'], $times);

        $this->post(route('public.appointments.store'), $this->payload(['provider' => 'Dentist', 'appointment_date' => $this->day(3), 'appointment_time' => '13:30:00']))
            ->assertRedirect(route('public.appointments.thanks'));
        $this->assertSame($visit->id, Appointment::sole()->specialist_visit_id);

        // Other providers keep every open day, and the form marks the dentist's day.
        $this->getJson(route('public.appointments.slots', ['date' => $this->day(1), 'provider' => 'Nurse']))->assertJson(['open' => true]);
        $this->get(route('public.appointments.create'))->assertOk()->assertSee('"visits":["Dentist"]', false);
    }

    // ─── Settings, notifications, footer ─────────────────────────────────────

    public function test_settings_save_the_online_request_options(): void
    {
        $base = [
            'max_daily_appointments' => 50, 'booking_max_days_ahead' => 0, 'reminder_hours_before' => 24,
            'appointment_slot_minutes' => 30, 'clinic_weekly_hours' => "monday | 07:30-17:00\ntuesday | 07:30-17:00",
            'public_booking_enabled' => '1', 'public_booking_consent_required' => '1',
            'appointment_purposes' => "Checkup\nOther",
            'public_booking_intro' => 'Book a clinic visit.',
            'public_booking_success_message' => 'Got it.',
            'public_booking_closed_message' => 'Closed for now.',
            'public_booking_days_ahead' => 21,
            'public_booking_min_notice_hours' => 2,
            'public_booking_slot_limit' => 3,
            'public_booking_specialist_days_only' => '1',
            'public_booking_closed_dates' => "2026-12-25 Christmas Day\r\n\r\n2027-01-01 New Year",
            'public_booking_field_category' => 'required',
            'public_booking_field_school' => 'hidden',
            'public_booking_field_student_id' => 'required',
            'public_booking_field_email' => 'optional',
            'public_booking_field_provider' => 'required',
            'public_booking_field_details' => 'hidden',
            'public_booking_consent_text' => 'I agree.',
        ];

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'appointments'))->assertOk()
            ->assertSee('Online request dates and times')->assertSee('Closed dates')->assertSee('Online requests per time slot')
            ->assertSee('data-noun="date"', false);

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'appointments'), $base)->assertSessionHasNoErrors();

        $this->assertSame(['2026-12-25 Christmas Day', '2027-01-01 New Year'], settings()->list('public_booking_closed_dates'));
        $this->assertSame(['Checkup', 'Other'], settings()->list('appointment_purposes'));
        $this->assertSame(21, settings('public_booking_days_ahead'));
        $this->assertSame(3, settings('public_booking_slot_limit'));
        $this->assertTrue(settings('public_booking_specialist_days_only'));
        $this->assertSame('hidden', settings('public_booking_field_school'));
        $this->assertSame(['2026-12-25' => 'Christmas Day', '2027-01-01' => 'New Year'], app(OnlineBooking::class)->closedDates());

        // A closed date line must start with a date; a field can only be required, optional or hidden.
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'appointments'), ['public_booking_closed_dates' => "Christmas\n2026-12-31"] + $base)
            ->assertSessionHasErrors('public_booking_closed_dates');
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'appointments'), ['public_booking_field_email' => 'maybe'] + $base)
            ->assertSessionHasErrors('public_booking_field_email');
        $this->assertSame('optional', settings('public_booking_field_email'));
    }

    public function test_clinic_is_emailed_about_new_requests_when_turned_on(): void
    {
        Notification::fake();
        settings()->set('clinic_email', 'clinic@example.com');

        $this->post(route('public.appointments.store'), $this->payload());
        Notification::assertNothingSent();

        settings()->set('notify_email_online_request', true);
        $this->post(route('public.appointments.store'), $this->payload(['requester_name' => 'Second Person', 'appointment_time' => '10:00:00']));
        Notification::assertSentOnDemand(OnlineAppointmentRequestNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'clinic@example.com'
                && str_contains($n->toMail($notifiable)->subject, 'Second Person'));

        settings()->set('online_request_notify_email', 'nurse@example.com');
        $this->post(route('public.appointments.store'), $this->payload(['requester_name' => 'Third Person', 'appointment_time' => '10:30:00']));
        Notification::assertSentOnDemand(OnlineAppointmentRequestNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'nurse@example.com');
    }

    public function test_public_footer_shows_hours_links_and_contact(): void
    {
        settings()->set('app_name', 'ZetaProduct');
        settings()->set('clinic_name', 'Sunrise Clinic');
        settings()->set('org_name', 'Sunrise High School');
        settings()->set('clinic_contact', '0917 555 0101');
        settings()->set('clinic_email', 'clinic@sunrise.example');
        settings()->set('clinic_address', '12 Mabini Street, Batangas');
        settings()->set('public_intake_enabled', true);

        $this->travelTo($this->monday->copy()->setTime(10, 0));
        $page = $this->get(route('public.appointments.create'))->assertOk();
        $page->assertSee('Sunrise Clinic')
            ->assertSee('Open now until 5:00 PM')
            ->assertSee('class="c-live"', false)
            ->assertSee('Today, Monday: 7:30 AM to 5:00 PM')
            ->assertSee('Monday to Friday')
            ->assertSee('Quick links')
            ->assertSee(route('clinic'))->assertSee(route('public.health-form.create'))->assertSee(route('privacy'))
            ->assertSee('0917 555 0101')->assertSee('clinic@sunrise.example')->assertSee('12 Mabini Street, Batangas')
            ->assertSee('© '.now()->year.' Sunrise High School')
            ->assertDontSee('ZetaProduct');

        // Closed: grey dot, when it opens next; contact left out when not set.
        settings()->set('clinic_email', '');
        settings()->set('clinic_address', '');
        $this->travelTo($this->monday->copy()->addDays(5)->setTime(10, 0)); // Saturday
        $this->get(route('public.appointments.create'))->assertOk()
            ->assertSee('Closed, opens Monday 7:30 AM')
            ->assertDontSee('class="c-live"', false)
            ->assertDontSee('clinic@sunrise.example');

        // Without a clinic name the footer says "School Clinic", never the product name.
        settings()->set('clinic_name', '');
        settings()->set('org_name', '');
        $this->get(route('public.health-form.create'))->assertOk()
            ->assertSee('© '.now()->year.' School Clinic')
            ->assertDontSee('ZetaProduct');
    }
}
