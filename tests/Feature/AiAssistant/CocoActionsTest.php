<?php

namespace Tests\Feature\AiAssistant;

use App\Mail\AssistantMessageMail;
use App\Models\AiPendingAction;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Coco\CocoActions;
use App\Services\Coco\CocoRefusal;
use Carbon\Carbon;
use Database\Seeders\AppointmentTimeSlotSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The assistant's actions (App\Services\Coco): proposals are checked and
 * stored, nothing runs until confirm(), which checks again and runs through
 * the app's own services.
 */
class CocoActionsTest extends TestCase
{
    use RefreshDatabase;

    private const SEMAPHORE = 'https://api.semaphore.co/*';

    private User $nurse;
    private User $admin;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 08:00:00')); // a Monday
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AppointmentTimeSlotSeeder::class);
        config(['semaphore.api_key' => 'test-key', 'semaphore.sender_name' => 'CLINIC', 'services.groq.api_key' => 'test-groq']);
        Http::preventStrayRequests();
        settings()->setMany(['ai_actions_enabled' => true, 'sms_enabled' => true]);

        $this->nurse = User::factory()->create(['name' => 'Maria Santos', 'is_active' => true]);
        $this->nurse->assignRole('nurse');
        $this->admin = User::factory()->create(['name' => 'Ana Admin', 'is_active' => true]);
        $this->admin->assignRole('administrator');

        $this->patient = Patient::factory()->create([
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'category' => 'junior_high',
            'year_level' => 'Grade 7', 'section' => 'Rizal',
            'contact_number' => '09181112222', 'guardian_name' => 'Rosa Dela Cruz', 'guardian_contact' => '09171234567',
            'email' => 'juan@example.com',
        ]);
    }

    private function actions(): CocoActions
    {
        return app(CocoActions::class);
    }

    private function propose(string $type, array $args, ?User $user = null): AiPendingAction
    {
        $user ??= $this->nurse;
        $this->actingAs($user);

        return $this->actions()->propose($type, $args, $user);
    }

    private function refusal(string $type, array $args, ?User $user = null): string
    {
        try {
            $this->propose($type, $args, $user);
        } catch (CocoRefusal $e) {
            return $e->getMessage();
        }

        $this->fail("{$type} was not refused.");
    }

    private function confirm(AiPendingAction $action, array $input = [], ?User $user = null): array
    {
        $user ??= $this->nurse;
        $this->actingAs($user);

        return $this->actions()->confirm($action->fresh(), $user, $input);
    }

    private function fakeSemaphore(): void
    {
        Http::fake([self::SEMAPHORE => Http::response([['message_id' => 991, 'recipient' => '639171234567', 'status' => 'Pending']])]);
    }

    private function sms(array $extra = []): array
    {
        return ['to' => 'guardian', 'patient_id' => $this->patient->id, 'message' => 'Hello, Juan is resting in the clinic. - School Clinic'] + $extra;
    }

    // ── Text messages ─────────────────────────────────────────────────────────

    public function test_sms_proposal_is_stored_with_a_masked_preview_and_nothing_is_sent(): void
    {
        Http::fake();

        $action = $this->propose('send_sms', $this->sms());

        $this->assertSame(AiPendingAction::PENDING, $action->status);
        $this->assertTrue($action->expires_at->equalTo(now()->addMinutes(10)));
        $card = $this->actions()->card($action);
        $this->assertSame('Send SMS', $card['title']);
        $this->assertSame([
            ['label' => 'To', 'value' => 'Rosa Dela Cruz (guardian of Juan Dela Cruz)'],
            ['label' => 'Number', 'value' => '0917 *** 4567'],
        ], $card['fields']);
        $this->assertSame('sms', $card['editable']['kind']);
        $this->assertStringNotContainsString('09171234567', json_encode($card));

        Http::assertNothingSent();
        $this->assertSame(0, SmsLog::count());
    }

    public function test_sms_proposals_that_cannot_work_are_refused(): void
    {
        $this->patient->update(['guardian_contact' => null]);

        $this->assertStringContainsString('no valid guardian mobile number', $this->refusal('send_sms', $this->sms()));
        $this->assertStringContainsString('not a valid Philippine mobile number', $this->refusal('send_sms', ['to' => 'number', 'phone' => '12345', 'message' => 'Hello there']));
        $this->assertStringContainsString('too long', $this->refusal('send_sms', ['to' => 'number', 'phone' => '09171234567', 'message' => str_repeat('a', 470)]));
        $this->assertStringContainsString('No active patient matches', $this->refusal('send_sms', ['to' => 'patient', 'patient_name' => 'Nobody Here', 'message' => 'Hello']));
        $this->assertSame(0, AiPendingAction::count());
    }

    public function test_confirm_sends_the_edited_sms_through_semaphore_and_is_audited(): void
    {
        $this->fakeSemaphore();
        $action = $this->propose('send_sms', $this->sms());

        $result = $this->confirm($action, ['message' => 'Juan is resting in the clinic — he is fine.']);

        $this->assertTrue($result['ok']);
        $this->assertSame('Sent to Rosa Dela Cruz (guardian of Juan Dela Cruz). 1 SMS credit.', $result['text']);
        Http::assertSent(fn (Request $r) => $r['number'] === '639171234567' && $r['message'] === 'Juan is resting in the clinic, he is fine.');

        $log = SmsLog::sole();
        $this->assertSame('sent', $log->status);
        $this->assertSame('assistant', $log->event);
        $this->assertSame($this->nurse->id, $log->created_by);
        $this->assertSame(AiPendingAction::CONFIRMED, $action->fresh()->status);

        $audit = AuditLog::where('module', 'ai-assistant')->where('action', 'confirmed')->sole();
        $this->assertSame('Coco, confirmed by Maria Santos: Send SMS to Rosa Dela Cruz (guardian of Juan Dela Cruz).', $audit->description);
        $this->assertSame($this->nurse->id, $audit->user_id);
        $this->assertSame(
            'Coco prepared a card for Maria Santos: Send SMS to Rosa Dela Cruz (guardian of Juan Dela Cruz). Nothing is done until they confirm.',
            AuditLog::where('module', 'ai-assistant')->where('action', 'proposed')->sole()->description,
        );
    }

    public function test_a_name_matching_several_patients_lets_the_user_pick(): void
    {
        $this->fakeSemaphore();
        $twin = Patient::factory()->create(['first_name' => 'Juan', 'middle_name' => 'Bautista', 'last_name' => 'Dela Cruz', 'guardian_contact' => '09995556666']);

        $action = $this->propose('send_sms', ['to' => 'guardian', 'patient_name' => 'Juan Dela Cruz', 'message' => 'Please call the clinic.']);
        $card   = $this->actions()->card($action);

        $this->assertCount(2, $card['candidates']);
        $this->assertNull($card['choice']);
        $this->assertSame([], array_filter($card['fields'], fn ($f) => $f['label'] === 'To'));

        $noPick = $this->confirm($action);
        $this->assertFalse($noPick['ok']);
        $this->assertStringContainsString('Pick who this is for', $noPick['text']);
        $this->assertSame(AiPendingAction::PENDING, $action->fresh()->status);

        $this->assertFalse($this->confirm($action, ['choice' => '999999'])['ok']);

        $this->assertTrue($this->confirm($action, ['choice' => (string) $twin->id])['ok']);
        Http::assertSent(fn (Request $r) => $r['number'] === '639995556666');
        Http::assertSentCount(1);
    }

    public function test_double_confirm_runs_once_and_returns_the_same_result(): void
    {
        $this->fakeSemaphore();
        $action = $this->propose('send_sms', $this->sms());

        $first  = $this->confirm($action);
        $second = $this->confirm($action);

        $this->assertTrue($second['ok']);
        $this->assertSame($first['text'], $second['text']);
        Http::assertSentCount(1);
        $this->assertSame(1, SmsLog::count());
        $this->assertSame(1, AuditLog::where('action', 'confirmed')->count());
    }

    public function test_expired_and_cancelled_proposals_do_nothing(): void
    {
        Http::fake();
        $expired   = $this->propose('send_sms', $this->sms());
        $cancelled = $this->propose('send_sms', $this->sms(['message' => 'Another message']));

        $this->assertTrue($this->actions()->cancel($cancelled->fresh(), $this->nurse)['ok']);
        $this->assertSame(AiPendingAction::CANCELLED, $cancelled->fresh()->status);
        $this->assertFalse($this->confirm($cancelled)['ok']);

        $this->travel(11)->minutes();
        $result = $this->confirm($expired);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('expired', $result['text']);
        $this->assertSame(AiPendingAction::EXPIRED, $expired->fresh()->status);
        Http::assertNothingSent();
        $this->assertSame(0, SmsLog::count());
    }

    public function test_confirmed_actions_are_limited_per_hour(): void
    {
        $this->fakeSemaphore();

        foreach (range(1, CocoActions::HOURLY_LIMIT) as $i) {
            $action = $this->propose('send_sms', ['to' => 'number', 'phone' => '09171234567', 'message' => "Message {$i}"]);
            $this->assertTrue($this->confirm($action)['ok']);
        }

        $extra  = $this->propose('send_sms', ['to' => 'number', 'phone' => '09171234567', 'message' => 'One more']);
        $result = $this->confirm($extra);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('20 actions in the last hour', $result['text']);
        $this->assertSame(AiPendingAction::PENDING, $extra->fresh()->status);
        Http::assertSentCount(CocoActions::HOURLY_LIMIT);
    }

    public function test_sms_off_is_shown_on_the_card_and_logged_as_skipped(): void
    {
        Http::fake();
        settings()->set('sms_enabled', false);

        $action = $this->propose('send_sms', $this->sms());
        $this->assertStringContainsString('turned off in Settings', $this->actions()->card($action)['notes'][0]);

        $result = $this->confirm($action);

        $this->assertFalse($result['ok']);
        $this->assertStringStartsWith('Not sent:', $result['text']);
        $this->assertSame(AiPendingAction::FAILED, $action->fresh()->status);
        $this->assertSame('skipped', SmsLog::sole()->status);
        Http::assertNothingSent();
    }

    // ── Email ─────────────────────────────────────────────────────────────────

    public function test_email_to_a_patient_is_sent_with_the_users_edits(): void
    {
        Mail::fake();

        $action = $this->propose('send_email', ['to' => 'patient', 'patient_id' => $this->patient->id, 'subject' => 'Clinic visit', 'body' => "Hello Juan,\n\nPlease visit the clinic.\n\nSchool Clinic"]);
        $this->assertSame('ju***@example.com', collect($this->actions()->card($action)['fields'])->firstWhere('label', 'Email')['value']);
        Mail::assertNothingSent();

        $result = $this->confirm($action, ['subject' => 'Your clinic visit', 'message' => "Hello Juan,\n\nPlease drop by the clinic today."]);

        $this->assertTrue($result['ok']);
        Mail::assertSent(AssistantMessageMail::class, fn ($mail) => $mail->hasTo('juan@example.com')
            && $mail->subjectLine === 'Your clinic visit'
            && str_contains($mail->body, 'drop by the clinic')
            && $mail->senderName === 'Maria Santos');
    }

    public function test_email_recipients_are_checked(): void
    {
        $this->assertStringContainsString('Guardians have no email', $this->refusal('send_email', ['to' => 'guardian', 'patient_id' => $this->patient->id, 'subject' => 'Hi', 'body' => 'Hello there']));
        $this->assertStringContainsString('not a valid email', $this->refusal('send_email', ['to' => 'address', 'email' => 'not-an-email', 'subject' => 'Hi', 'body' => 'Hello there']));
        $this->assertStringContainsString('needs a subject', $this->refusal('send_email', ['to' => 'address', 'email' => 'a@b.co', 'subject' => '', 'body' => 'Hello there']));

        $this->patient->update(['email' => null]);
        $this->assertStringContainsString('no email address on file', $this->refusal('send_email', ['to' => 'patient', 'patient_id' => $this->patient->id, 'subject' => 'Hi', 'body' => 'Hello there']));

        $staff  = $this->propose('send_email', ['to' => 'staff', 'staff_name' => 'Ana Admin', 'subject' => 'Supplies', 'body' => 'We need more gauze.']);
        $this->assertSame('Ana Admin', collect($this->actions()->card($staff)['fields'])->firstWhere('label', 'To')['value']);
    }

    // ── Appointments ──────────────────────────────────────────────────────────

    public function test_booking_is_proposed_then_booked_through_the_booking_service(): void
    {
        $this->fakeSemaphore();

        $action = $this->propose('book_appointment', ['patient_id' => $this->patient->id, 'date' => '2026-10-06', 'time' => '9:00 AM', 'purpose' => 'Follow-up Visit']);
        $fields = collect($this->actions()->card($action)['fields'])->pluck('value', 'label');

        $this->assertSame('Tuesday, October 6, 2026', $fields['Date']);
        $this->assertSame('5 of 5', $fields['Places left']);
        $this->assertSame(0, Appointment::count());

        $result = $this->confirm($action);

        $this->assertTrue($result['ok'], $result['text']);
        $appointment = Appointment::sole();
        $this->assertSame('2026-10-06', $appointment->appointment_date->toDateString());
        $this->assertSame('09:00:00', $appointment->appointment_time);
        $this->assertSame('pending', $appointment->status);
        $this->assertSame(Appointment::SOURCE_STAFF, $appointment->source);
        $this->assertSame($this->nurse->id, $appointment->created_by);
        $this->assertSame(route('appointments.show', $appointment), $result['url']);
        // The usual "appointment booked" text went out.
        $this->assertSame('appointment_created', SmsLog::sole()->event);
        $this->assertDatabaseHas('audit_logs', ['module' => 'appointments', 'action' => 'created', 'user_id' => $this->nurse->id]);
    }

    public function test_booking_without_a_reason_uses_the_first_reason_and_says_so(): void
    {
        $card = $this->actions()->card($this->propose('book_appointment', ['patient_name' => 'Juan Dela Cruz', 'date' => '2026-10-06', 'time' => '09:00']));

        $this->assertSame('General Checkup', collect($card['fields'])->firstWhere('label', 'Purpose')['value']);
        $this->assertStringContainsString('No reason was given, so it is booked as General Checkup', $card['notes'][0]);
    }

    public function test_booking_refuses_what_the_appointments_page_refuses(): void
    {
        $args = ['patient_id' => $this->patient->id, 'purpose' => 'Checkup'];

        $this->assertStringContainsString('not one of the appointment times', $this->refusal('book_appointment', $args + ['date' => '2026-10-06', 'time' => '09:10']));
        $this->assertStringContainsString('already passed', $this->refusal('book_appointment', $args + ['date' => '2026-10-04', 'time' => '09:00']));
        $this->assertStringContainsString('already passed today', $this->refusal('book_appointment', $args + ['date' => '2026-10-05', 'time' => '07:00']));

        Appointment::factory()->count(5)->create(['appointment_date' => '2026-10-06', 'appointment_time' => '09:00:00']);
        $full = $this->refusal('book_appointment', $args + ['date' => '2026-10-06', 'time' => '09:00']);
        $this->assertStringContainsString('fully booked', $full);
        $this->assertStringContainsString('Free times on Tue, Oct 6: 07:00, 07:30, 08:00, 08:30, 09:30', $full);

        settings()->set('allow_weekend_booking', false);
        $this->assertStringContainsString('weekends', $this->refusal('book_appointment', $args + ['date' => '2026-10-10', 'time' => '09:00']));
    }

    public function test_a_slot_that_fills_up_before_confirm_is_refused_and_can_be_retried(): void
    {
        $action = $this->propose('book_appointment', ['patient_id' => $this->patient->id, 'date' => '2026-10-06', 'time' => '10:00', 'purpose' => 'Checkup']);
        Appointment::factory()->count(5)->create(['appointment_date' => '2026-10-06', 'appointment_time' => '10:00:00']);

        $result = $this->confirm($action);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('fully booked', $result['text']);
        $this->assertSame(AiPendingAction::PENDING, $action->fresh()->status);
        $this->assertSame(5, Appointment::count());
    }

    public function test_reschedule_and_cancel_use_the_appointment_services(): void
    {
        Http::fake([self::SEMAPHORE => Http::response([['message_id' => 1, 'status' => 'Pending']])]);
        $appointment = Appointment::factory()->create(['patient_id' => $this->patient->id, 'appointment_date' => '2026-10-06', 'appointment_time' => '09:00:00', 'status' => 'approved']);

        $same = $this->refusal('reschedule_appointment', ['appointment_id' => $appointment->id, 'date' => '2026-10-06', 'time' => '09:00']);
        $this->assertStringContainsString('already at that date and time', $same);

        $move = $this->propose('reschedule_appointment', ['patient_name' => 'Juan Dela Cruz', 'date' => '2026-10-07', 'time' => '10:30']);
        $this->assertTrue($this->confirm($move)['ok']);
        $appointment->refresh();
        $this->assertSame('2026-10-07', $appointment->appointment_date->toDateString());
        $this->assertSame('10:30:00', $appointment->appointment_time);
        $this->assertSame(1, SmsLog::where('event', 'appointment_rescheduled')->count());

        $this->assertStringContainsString('reason is needed', $this->refusal('cancel_appointment', ['appointment_id' => $appointment->id, 'reason' => '']));

        $cancel = $this->propose('cancel_appointment', ['appointment_id' => $appointment->id, 'reason' => 'Clinic closed for a seminar']);
        $this->assertTrue($this->confirm($cancel)['ok']);
        $appointment->refresh();
        $this->assertSame('cancelled', $appointment->status);
        $this->assertSame('Clinic closed for a seminar', $appointment->cancelled_reason);
        $this->assertSame(1, SmsLog::where('event', 'appointment_cancelled')->count());

        $this->assertStringContainsString('already cancelled', $this->refusal('cancel_appointment', ['appointment_id' => $appointment->id, 'reason' => 'Again']));
    }

    // ── Permissions and switches ──────────────────────────────────────────────

    public function test_actions_follow_the_users_own_permissions(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->assignRole('viewer');
        $this->assertSame([], $this->actions()->available($viewer));
        $this->assertStringContainsString('not allowed', $this->refusal('send_sms', $this->sms(), $viewer));

        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('staff'); // can book, cannot text or cancel
        $this->assertSame(['book_appointment'], array_keys($this->actions()->available($staff)));

        // Permission taken away after the card was made: Confirm refuses.
        Http::fake();
        $action = $this->propose('send_sms', $this->sms());
        $this->nurse->roles()->detach();
        $this->nurse->givePermissionTo('use-ai-assistant');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $result = $this->confirm($action, [], $this->nurse->fresh());
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('permission', $result['text']);
        Http::assertNothingSent();
    }

    public function test_switches_turn_actions_off_even_for_waiting_cards(): void
    {
        Http::fake();
        $action = $this->propose('send_sms', $this->sms());

        settings()->set('ai_actions_messages', false);
        $this->assertArrayNotHasKey('send_sms', $this->actions()->available($this->nurse));
        $this->assertArrayHasKey('book_appointment', $this->actions()->available($this->nurse));
        $this->assertStringContainsString('turned this off', $this->confirm($action)['text']);

        settings()->setMany(['ai_actions_messages' => true, 'ai_actions_enabled' => false]);
        $this->assertSame([], $this->actions()->available($this->nurse));
        $this->assertFalse($this->confirm($action)['ok']);
        Http::assertNothingSent();
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    public function test_only_administrators_can_change_settings(): void
    {
        $this->nurse->givePermissionTo('manage-settings');
        $this->assertArrayNotHasKey('update_setting', $this->actions()->available($this->nurse));
        $this->assertStringContainsString('not allowed', $this->refusal('update_setting', ['key' => 'clinic_contact', 'value' => '0917 000 0000']));

        $this->assertArrayHasKey('update_setting', $this->actions()->available($this->admin));
    }

    public function test_admin_changes_a_setting_and_it_is_audited_with_old_and_new_values(): void
    {
        settings()->set('clinic_contact', '(02) 8123 4567');

        $action = $this->propose('update_setting', ['key' => 'clinic_contact', 'value' => '0917 555 1234'], $this->admin);
        $fields = collect($this->actions()->card($action)['fields'])->pluck('value', 'label');
        $this->assertSame('(02) 8123 4567', $fields['Now']);
        $this->assertSame('0917 555 1234', $fields['Change to']);
        $this->assertSame('(02) 8123 4567', settings('clinic_contact'));

        $result = $this->confirm($action, [], $this->admin);

        $this->assertTrue($result['ok']);
        $this->assertSame('0917 555 1234', settings('clinic_contact'));
        $audit = AuditLog::where('action', 'confirmed')->sole();
        $this->assertStringStartsWith('Coco, confirmed by Ana Admin: Change Clinic Phone (Clinic)', $audit->description);
        $this->assertSame(['clinic_contact' => '(02) 8123 4567'], $audit->old_values);
        $this->assertSame(['clinic_contact' => '0917 555 1234'], $audit->new_values);
    }

    public function test_secret_and_security_settings_can_never_be_changed(): void
    {
        foreach (['ai_groq_api_key', 'otp_enabled', 'mail_from_address', 'ai_actions_enabled', 'ai_read_patients', 'ai_enabled', 'brand_logo'] as $key) {
            $this->assertStringContainsString('cannot be changed from the chat', $this->refusal('update_setting', ['key' => $key, 'value' => 'x'], $this->admin), $key);
        }
        $this->assertStringContainsString('no setting called', $this->refusal('update_setting', ['key' => 'made_up_key', 'value' => '1'], $this->admin));

        $offered = $this->actions()->handler('update_setting')->tool()['function']['parameters']['properties']['key']['enum'];
        $this->assertContains('clinic_weekly_hours', $offered);
        $this->assertContains('sms_template_reminder', $offered);
        foreach ($offered as $key) {
            $this->assertDoesNotMatchRegularExpression('/api_key|otp|mail_from|ai_actions|ai_read|password|secret/', $key);
        }
    }

    public function test_setting_values_are_checked_with_the_settings_page_rules(): void
    {
        $this->assertStringContainsString('whole number', $this->refusal('update_setting', ['key' => 'max_daily_appointments', 'value' => 'lots'], $this->admin));
        $this->assertStringContainsString('must not be greater than 9999', $this->refusal('update_setting', ['key' => 'max_daily_appointments', 'value' => '100000'], $this->admin));
        $this->assertStringContainsString('Unknown placeholder {bogus}', $this->refusal('update_setting', ['key' => 'sms_template_reminder', 'value' => 'Hi {bogus}'], $this->admin));
        $this->assertStringContainsString('"on" or "off"', $this->refusal('update_setting', ['key' => 'sms_enabled', 'value' => 'maybe'], $this->admin));
        $this->assertStringContainsString('already set to that', $this->refusal('update_setting', ['key' => 'sms_enabled', 'value' => 'on'], $this->admin));
        $this->assertStringContainsString('closing time must be after', $this->refusal('update_setting', ['key' => 'clinic_weekly_hours', 'value' => '{"saturday": "12:00-08:00"}'], $this->admin));
    }

    public function test_weekly_hours_change_only_the_days_given(): void
    {
        $action = $this->propose('update_setting', ['key' => 'clinic_weekly_hours', 'value' => '{"saturday": "8:00-12:00"}'], $this->admin);
        $this->assertTrue($this->confirm($action, [], $this->admin)['ok']);

        $hours = settings()->options('clinic_weekly_hours');
        $this->assertSame('08:00-12:00', $hours['saturday']);
        $this->assertSame('07:30-17:00', $hours['monday']);
        $this->assertSame('closed', $hours['sunday']);
    }

    // ── Endpoints ─────────────────────────────────────────────────────────────

    public function test_confirm_and_cancel_endpoints_only_work_for_the_owner(): void
    {
        $this->fakeSemaphore();
        $action = $this->propose('send_sms', $this->sms());

        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('nurse');
        $this->actingAs($other)->postJson(route('ai-assistant.actions.confirm', $action))->assertNotFound();
        $this->actingAs($other)->postJson(route('ai-assistant.actions.cancel', $action))->assertNotFound();
        $this->assertSame(AiPendingAction::PENDING, $action->fresh()->status);

        $this->actingAs($this->nurse)
            ->postJson(route('ai-assistant.actions.confirm', $action), ['message' => 'Juan is fine and resting. - Clinic'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('card.status', 'confirmed')
            ->assertJsonPath('card.result.ok', true);

        Http::assertSentCount(1);
    }
}
