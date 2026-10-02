<?php

namespace Tests\Feature\AiAssistant;

use App\Models\AiConversation;
use App\Models\AiPendingAction;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientLog;
use App\Models\SmsLog;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AppointmentTimeSlotSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tool calling with Groq (mocked): which tools are offered, what the read
 * tools send, and that an action tool only ever produces a card.
 */
class CocoToolsTest extends TestCase
{
    use RefreshDatabase;

    private const GROQ = 'https://api.groq.com/*';

    private User $nurse;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AppointmentTimeSlotSeeder::class);
        config(['services.groq.api_key' => 'env-test-key', 'semaphore.api_key' => 'test-key']);
        Http::preventStrayRequests();

        $this->nurse = User::factory()->create(['name' => 'Maria Santos', 'is_active' => true]);
        $this->nurse->assignRole('nurse');

        $this->patient = Patient::factory()->create([
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'year_level' => 'Grade 7', 'section' => 'Rizal', 'program_strand' => null,
            'contact_number' => '09181112222', 'guardian_name' => 'Rosa Dela Cruz', 'guardian_contact' => '09171234567',
            'allergies' => 'Penicillin', 'medical_conditions' => 'Asthma',
        ]);
    }

    private function answer(string $content = 'Done.'): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']], 'usage' => ['total_tokens' => 40]];
    }

    private function toolCall(string $name, array $args, string $id = 'call_1'): array
    {
        return [
            'choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [
                ['id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($args)]],
            ]], 'finish_reason' => 'tool_calls']],
            'usage' => ['total_tokens' => 60],
        ];
    }

    private function payload(int $index): array
    {
        return Http::recorded(fn ($r) => str_contains($r->url(), 'groq.com'))[$index][0]->data();
    }

    /** The last message of a sent payload (the newest tool result). */
    private function lastMessage(int $index): array
    {
        return collect($this->payload($index)['messages'])->last();
    }

    /** Function tool names in a sent payload. */
    private function toolNames(array $payload): array
    {
        return collect($payload['tools'] ?? [])->pluck('function.name')->filter()->values()->all();
    }

    private function chat(string $message): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->nurse)->postJson(route('ai-assistant.chat'), ['message' => $message])->assertOk();
    }

    // ── What is offered ───────────────────────────────────────────────────────

    public function test_with_actions_off_only_read_tools_are_offered(): void
    {
        Http::fake([self::GROQ => Http::response($this->answer())]);

        $this->chat('Hello');

        $names = $this->toolNames($this->payload(0));
        $this->assertSame(['find_patient', 'get_patient_summary', 'list_appointment_slots', 'list_appointments', 'medicine_stock'], $names);
        $system = $this->payload(0)['messages'][0]['content'];
        $this->assertStringContainsString('## Records and actions', $system);
        $this->assertStringContainsString('You cannot send messages, book appointments or change anything yourself', $system);
    }

    public function test_with_actions_on_the_users_own_actions_are_offered(): void
    {
        settings()->set('ai_actions_enabled', true);
        Http::fake([self::GROQ => Http::response($this->answer())]);

        $this->chat('Hello');

        $names = $this->toolNames($this->payload(0));
        foreach (['send_sms', 'send_email', 'book_appointment', 'reschedule_appointment', 'cancel_appointment'] as $action) {
            $this->assertContains($action, $names);
        }
        $this->assertNotContains('update_setting', $names); // administrators only
        $system = $this->payload(0)['messages'][0]['content'];
        $this->assertStringContainsString('You never do an action yourself', $system);
        $this->assertStringContainsString('never say it was sent, booked or changed', $system);
    }

    public function test_with_patient_reading_off_no_patient_tools_are_offered(): void
    {
        settings()->set('ai_read_patients', false);
        Http::fake([self::GROQ => Http::response($this->answer())]);

        $this->chat('Hello');

        $names = $this->toolNames($this->payload(0));
        $this->assertNotContains('find_patient', $names);
        $this->assertNotContains('get_patient_summary', $names);
        $this->assertContains('medicine_stock', $names);
        $this->assertStringContainsString('remind them once', $this->payload(0)['messages'][0]['content']);
    }

    public function test_a_model_without_tool_support_gets_plain_chat(): void
    {
        config(['settings.groups.ai.fields.ai_model.options.some/new-model' => 'New']);
        $this->app->forgetInstance(\App\Services\SettingsService::class);
        settings()->set('ai_model', 'some/new-model');
        Http::fake([self::GROQ => Http::response($this->answer())]);

        $this->chat('Hello');

        $this->assertArrayNotHasKey('tools', $this->payload(0));
    }

    // ── Read tools ────────────────────────────────────────────────────────────

    public function test_patient_lookup_sends_only_the_minimal_details_and_is_audited(): void
    {
        PatientLog::factory()->create(['patient_id' => $this->patient->id, 'log_date' => '2026-10-02', 'chief_complaint' => 'Headache', 'treatment' => 'Paracetamol 500mg', 'disposition' => 'returned_to_class']);
        Http::fake([self::GROQ => Http::sequence()
            ->push($this->toolCall('find_patient', ['query' => 'Juan Dela Cruz']))
            ->push($this->toolCall('get_patient_summary', ['patient_id' => $this->patient->id], 'call_2'))
            ->push($this->answer('Juan is allergic to penicillin.'))]);

        $this->chat('Is Juan Dela Cruz allergic to anything?')->assertJsonPath('response', 'Juan is allergic to penicillin.');

        $found = json_decode($this->lastMessage(1)['content'], true);
        $this->assertSame([['id' => $this->patient->id, 'name' => 'Juan Dela Cruz', 'patient_no' => $this->patient->patient_number, 'placement' => 'Grade 7, Rizal']], $found['matches']);

        $summary = $this->lastMessage(2);
        $this->assertSame('tool', $summary['role']);
        $this->assertSame('call_2', $summary['tool_call_id']);
        $data = json_decode($summary['content'], true);
        $this->assertSame('Penicillin', $data['allergies']);
        $this->assertSame('Asthma', $data['conditions']);
        $this->assertSame(['date' => 'Oct 2, 2026', 'complaint' => 'Headache', 'action' => 'Paracetamol 500mg', 'outcome' => 'Returned to Class / Work'], $data['last_visits'][0]);
        foreach (['09171234567', '09181112222', 'Rosa'] as $private) {
            $this->assertStringNotContainsString($private, $summary['content']);
        }

        $this->assertSame(2, AuditLog::where('module', 'ai-assistant')->where('action', 'viewed')->count());
        $this->assertDatabaseHas('audit_logs', ['description' => "Coco looked up Juan Dela Cruz ({$this->patient->patient_number}) for Maria Santos", 'user_id' => $this->nurse->id]);
    }

    public function test_slots_tool_lists_free_times(): void
    {
        Http::fake([self::GROQ => Http::sequence()
            ->push($this->toolCall('list_appointment_slots', ['date' => '2026-10-06']))
            ->push($this->answer('Plenty of times are free.'))]);

        $this->chat('What times are free tomorrow?');

        $data = json_decode($this->lastMessage(1)['content'], true);
        $this->assertSame('Tuesday, October 6, 2026 (2026-10-06)', $data['date']);
        $this->assertSame('07:00 (5 left)', $data['free'][0]);
        $this->assertSame('07:30 to 17:00', $data['clinic_hours']);
    }

    // ── Action tools only make cards ──────────────────────────────────────────

    public function test_an_action_tool_only_prepares_a_card(): void
    {
        settings()->setMany(['ai_actions_enabled' => true, 'sms_enabled' => true]);
        Http::fake([self::GROQ => Http::sequence()
            ->push($this->toolCall('send_sms', ['to' => 'guardian', 'patient_name' => 'Juan Dela Cruz', 'message' => 'Juan is resting in the clinic. - School Clinic']))
            ->push($this->answer('I prepared the text to Juan\'s guardian. Please check it and tap Confirm.'))]);

        $response = $this->chat("Text Juan Dela Cruz's guardian that Juan is resting in the clinic");

        $response->assertJsonPath('actions.0.type', 'send_sms')
            ->assertJsonPath('actions.0.status', 'pending')
            ->assertJsonPath('actions.0.fields.1.value', '0917 *** 4567')
            ->assertJsonPath('actions.0.editable.message', 'Juan is resting in the clinic. - School Clinic');

        $action = AiPendingAction::sole();
        $this->assertSame($response->json('id'), $action->conversation_id);
        $this->assertSame(0, SmsLog::count());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'semaphore'));

        $note = json_decode($this->lastMessage(1)['content'], true);
        $this->assertSame('waiting_for_user', $note['status']);
        $this->assertStringContainsString('Do not say it is done', $note['next']);
    }

    public function test_the_model_cannot_use_an_action_that_is_not_offered(): void
    {
        // Actions are off: a send_sms call gets an error, and no card is made.
        Http::fake([self::GROQ => Http::sequence()
            ->push($this->toolCall('send_sms', ['to' => 'number', 'phone' => '09171234567', 'message' => 'Hi']))
            ->push($this->answer('I cannot send texts.'))]);

        $this->chat('Text 09171234567 hi')->assertJsonPath('actions', []);

        $this->assertSame(0, AiPendingAction::count());
        $this->assertStringContainsString('not available', $this->lastMessage(1)['content']);
    }

    public function test_at_most_three_cards_per_message(): void
    {
        settings()->set('ai_actions_enabled', true);
        $calls = collect(range(1, 4))->map(fn ($i) => ['id' => "c{$i}", 'type' => 'function', 'function' => ['name' => 'send_sms', 'arguments' => json_encode(['to' => 'number', 'phone' => '09171234567', 'message' => "Message {$i}"])]])->all();
        Http::fake([self::GROQ => Http::sequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => $calls]]], 'usage' => ['total_tokens' => 10]])
            ->push($this->answer('Three are ready.'))]);

        $this->chat('Send four texts')->assertJsonCount(3, 'actions');

        $this->assertSame(3, AiPendingAction::count());
        $this->assertStringContainsString('Only 3 cards', $this->lastMessage(1)['content']);
    }

    public function test_after_four_rounds_the_model_is_asked_for_the_answer_only(): void
    {
        Http::fake([self::GROQ => Http::sequence()
            ->push($this->toolCall('medicine_stock', ['name' => 'a'], 'c1'))
            ->push($this->toolCall('medicine_stock', ['name' => 'b'], 'c2'))
            ->push($this->toolCall('medicine_stock', ['name' => 'c'], 'c3'))
            ->push($this->toolCall('medicine_stock', ['name' => 'd'], 'c4'))
            ->push($this->answer('Here is the stock.'))]);

        $this->chat('Check the stock')->assertJsonPath('response', 'Here is the stock.');

        Http::assertSentCount(5);
        $this->assertArrayNotHasKey('tool_choice', $this->payload(3));
        $this->assertSame('none', $this->payload(4)['tool_choice']);
    }

    public function test_a_rejected_tool_request_is_answered_as_plain_chat(): void
    {
        Http::fake([self::GROQ => Http::sequence()
            ->push(['error' => ['message' => 'Failed to call a function', 'code' => 'tool_use_failed']], 400)
            ->push($this->answer('Plain answer.'))]);

        $this->chat('Hello')->assertJsonPath('response', 'Plain answer.');

        $this->assertArrayHasKey('tools', $this->payload(0));
        $this->assertArrayNotHasKey('tools', $this->payload(1));
    }

    public function test_history_tells_the_model_what_became_of_a_card(): void
    {
        $convo = AiConversation::create(['user_id' => $this->nurse->id, 'message' => 'Text Juan', 'response' => 'Prepared it.', 'tokens_used' => 20]);
        AiPendingAction::create([
            'user_id' => $this->nurse->id, 'conversation_id' => $convo->id, 'type' => 'send_sms',
            'payload' => [], 'preview' => ['title' => 'Send SMS'], 'status' => 'cancelled', 'expires_at' => now()->addMinutes(5),
        ]);
        Http::fake([self::GROQ => Http::response($this->answer())]);

        $this->chat('Did it go out?');

        $history = $this->payload(0)['messages'][2];
        $this->assertSame('assistant', $history['role']);
        $this->assertStringContainsString('[Send SMS card: cancelled by the user, nothing was done.]', $history['content']);
    }

    public function test_the_chat_page_shows_saved_cards_and_clearing_cancels_them(): void
    {
        $convo  = AiConversation::create(['user_id' => $this->nurse->id, 'message' => 'Text Juan', 'response' => 'Prepared it.', 'tokens_used' => 20]);
        $action = AiPendingAction::create([
            'user_id' => $this->nurse->id, 'conversation_id' => $convo->id, 'type' => 'send_sms',
            'payload' => ['candidates' => ['number']], 'preview' => ['title' => 'Send SMS', 'fields' => []], 'expires_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($this->nurse)->get(route('ai-assistant.index'))
            ->assertOk()
            ->assertSee('id="cocoCards"', false)
            ->assertSee($action->id)
            ->assertSee(str_replace('/', '\/', route('ai-assistant.actions.confirm', $action)), false);

        $this->actingAs($this->nurse)->deleteJson(route('ai-assistant.clear'))->assertOk();
        $this->assertSame(AiPendingAction::CANCELLED, $action->fresh()->status);
    }
}
