<?php

namespace Tests\Feature\AiAssistant;

use App\Models\AiConversation;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiAssistantService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.groq.com/*';

    private User $nurse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        config(['services.groq.api_key' => 'env-test-key']);
        Http::preventStrayRequests();
        settings()->set('ai_web_search', true); // off by default

        $this->nurse = User::factory()->create(['name' => 'Maria Santos', 'is_active' => true]);
        $this->nurse->assignRole('nurse');
        $this->actingAs($this->nurse);
    }

    private function fakeAnswer(string $content = 'Canberra is the capital of Australia.'): void
    {
        Http::fake([self::API => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']],
            'usage'   => ['total_tokens' => 120],
        ])]);
    }

    private function chat(string $message = 'What is the capital of Australia?'): array
    {
        return app(AiAssistantService::class)->chat($message, AiConversation::where('user_id', $this->nurse->id)->latest()->get());
    }

    private function sentPayload(int $index = 0): array
    {
        return Http::recorded()[$index][0]->data();
    }

    public function test_retired_saved_model_falls_back_to_the_default(): void
    {
        Setting::create(['key' => 'ai_model', 'value' => 'llama-3.3-70b-versatile', 'type' => 'string', 'group' => 'ai']);
        settings()->flush();

        $this->assertSame('openai/gpt-oss-120b', app(AiAssistantService::class)->model());

        settings()->set('ai_model', 'openai/gpt-oss-20b');
        $this->assertSame('openai/gpt-oss-20b', app(AiAssistantService::class)->model());
    }

    public function test_gpt_oss_request_uses_reasoning_and_web_search(): void
    {
        $this->fakeAnswer();

        $result = $this->chat();

        $this->assertSame('Canberra is the capital of Australia.', $result['response']);
        $this->assertSame(120, $result['tokens']);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer env-test-key'));
        $payload = $this->sentPayload();
        $this->assertSame('openai/gpt-oss-120b', $payload['model']);
        $this->assertSame('medium', $payload['reasoning_effort']);
        $this->assertFalse($payload['include_reasoning']);
        $this->assertSame(8192, $payload['max_tokens']);
        $this->assertSame([['type' => 'browser_search']], $payload['tools']);
        $this->assertArrayNotHasKey('reasoning_format', $payload);
    }

    public function test_web_search_is_off_by_default_and_can_be_turned_off(): void
    {
        $this->assertFalse(config('settings.groups.ai.fields.ai_web_search.default'));
        settings()->set('ai_web_search', false);
        $this->fakeAnswer();

        $this->chat();

        $this->assertArrayNotHasKey('tools', $this->sentPayload());
    }

    public function test_qwen_gets_hidden_reasoning_and_no_web_search(): void
    {
        settings()->set('ai_model', 'qwen/qwen3.8-27b');
        $this->fakeAnswer();

        $this->chat();

        $payload = $this->sentPayload();
        $this->assertSame('hidden', $payload['reasoning_format']);
        $this->assertArrayNotHasKey('reasoning_effort', $payload);
        $this->assertArrayNotHasKey('include_reasoning', $payload);
        $this->assertArrayNotHasKey('tools', $payload);
    }

    public function test_failed_web_search_is_answered_again_without_it(): void
    {
        Http::fake([self::API => Http::sequence()
            ->push(['error' => ['message' => 'tool call failed']], 400)
            ->push(['choices' => [['message' => ['content' => 'Answer without search.']]], 'usage' => ['total_tokens' => 50]])]);

        $result = $this->chat();

        $this->assertSame('Answer without search.', $result['response']);
        Http::assertSentCount(2);
        $this->assertArrayHasKey('tools', $this->sentPayload(0));
        $this->assertArrayNotHasKey('tools', $this->sentPayload(1));
    }

    public function test_slow_web_search_is_dropped_and_answered_without_it(): void
    {
        Http::fake([self::API => Http::sequence()
            ->pushFailedConnection('cURL error 28: Operation timed out after 30001 milliseconds')
            ->push(['choices' => [['message' => ['content' => 'Answer without search.']]], 'usage' => ['total_tokens' => 50]])]);

        $result = $this->chat();

        $this->assertSame('Answer without search.', $result['response']);
        Http::assertSentCount(2);
        $this->assertArrayNotHasKey('tools', $this->sentPayload(1));
    }

    public function test_reply_is_cleaned_of_reasoning_citations_and_dashes(): void
    {
        $this->fakeAnswer("<think>private notes</think>Give 10–15 mg/kg — every 6 hours.【3†L4-L7】\nOpen 9:00\u{202F}am – 12:00 pm.\n\n```\nkeep — this\n```");

        $response = $this->chat()['response'];

        $this->assertStringNotContainsString('private notes', $response);
        $this->assertStringNotContainsString('【', $response);
        $this->assertStringContainsString('Give 10-15 mg/kg, every 6 hours.', $response);
        $this->assertStringContainsString("Open 9:00\u{202F}am to 12:00 pm.", $response);
        $this->assertStringContainsString('keep — this', $response); // code is left as written
    }

    public function test_daily_limit_after_a_failed_search_still_tries_the_other_model(): void
    {
        Http::fake([self::API => Http::sequence()
            ->pushFailedConnection('cURL error 28: Operation timed out')
            ->push(['error' => ['message' => 'Rate limit reached (TPD)']], 429)
            ->push(['choices' => [['message' => ['content' => 'Answer from the other model.']]], 'usage' => ['total_tokens' => 30]])]);

        $this->assertSame('Answer from the other model.', $this->chat()['response']);
        $this->assertSame('openai/gpt-oss-120b', $this->sentPayload(1)['model']);
        $this->assertSame('openai/gpt-oss-20b', $this->sentPayload(2)['model']);
        $this->assertArrayNotHasKey('tools', $this->sentPayload(2));
    }

    public function test_errors_are_short_and_plain(): void
    {
        Http::fake([self::API => Http::response(['error' => ['message' => 'Rate limit']], 429)]);

        $result = $this->chat();

        $this->assertSame(0, $result['tokens']);
        $this->assertSame('The assistant is busy right now. Please wait a minute and try again.', $result['response']);
        $this->assertDoesNotMatchRegularExpression('/[\x{2013}\x{2014}\x{26A0}]/u', $result['response']);
    }

    public function test_rate_limited_model_falls_back_to_the_other_gpt_oss_model(): void
    {
        Http::fake([self::API => Http::sequence()
            ->push(['error' => ['message' => 'Rate limit reached']], 429)
            ->push(['choices' => [['message' => ['content' => 'Answer from the faster model.']]], 'usage' => ['total_tokens' => 40]])]);

        $result = $this->chat();

        $this->assertSame('Answer from the faster model.', $result['response']);
        $this->assertSame('openai/gpt-oss-120b', $this->sentPayload(0)['model']);
        $this->assertSame('openai/gpt-oss-20b', $this->sentPayload(1)['model']);
        $this->assertArrayHasKey('tools', $this->sentPayload(1));
    }

    public function test_missing_key_gives_a_setup_message_without_calling_the_api(): void
    {
        config(['services.groq.api_key' => '']);
        Http::fake();

        $result = $this->chat();

        $this->assertStringContainsString('not set up yet', $result['response']);
        $this->assertStringContainsString('Groq API key in Settings', $result['response']);
        Http::assertNothingSent();
    }

    public function test_key_saved_in_settings_is_preferred_over_the_server_key(): void
    {
        settings()->set('ai_groq_api_key', 'gsk_from_settings_1234');
        $this->fakeAnswer();

        $this->chat();

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer gsk_from_settings_1234'));
        $this->assertSame('settings', AiAssistantService::apiKeySource());
    }

    public function test_history_is_capped_by_total_length_and_sent_oldest_first(): void
    {
        foreach (range(1, 20) as $i) {
            AiConversation::create([
                'user_id' => $this->nurse->id, 'message' => "Question {$i}",
                'response' => str_repeat('x', 7000), 'tokens_used' => 10,
            ])->forceFill(['created_at' => now()->subMinutes(100 - $i)])->save();
        }
        // A failed answer (0 tokens) is never sent back as context.
        AiConversation::create(['user_id' => $this->nurse->id, 'message' => 'Broken', 'response' => 'Error', 'tokens_used' => 0]);
        $this->fakeAnswer();

        $this->chat('Newest question');

        $messages = $this->sentPayload()['messages'];
        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('Newest question', end($messages)['content']);

        $history = array_slice($messages, 1, -1);
        $this->assertCount(6, $history); // 3 turns of about 6,000 characters fit in 24,000
        $this->assertSame('Question 18', $history[0]['content']);
        $this->assertSame('Question 20', $history[4]['content']);
        $this->assertLessThanOrEqual(24000, array_sum(array_map(fn ($m) => mb_strlen($m['content']), $history)));
        $this->assertStringNotContainsString('Broken', json_encode($history));
    }

    public function test_live_context_has_clinic_numbers_but_no_patient_details(): void
    {
        $patient = Patient::factory()->create(['first_name' => 'Juanita', 'last_name' => 'Zamorano', 'contact_number' => '09171234567']);
        PatientLog::factory()->create(['patient_id' => $patient->id, 'chief_complaint' => 'Severe migraine']);
        Medicine::factory()->create(['name' => 'Paracetamol 500mg', 'quantity' => 2, 'low_stock_threshold' => 20]);
        $this->fakeAnswer();

        $this->chat();

        $system = $this->sentPayload()['messages'][0]['content'];
        $this->assertStringContainsString('## Right now', $system);
        $this->assertStringContainsString('Talking with: Maria, role Nurse', $system);
        $this->assertStringContainsString('Clinic visits today: 1, with 1 in the clinic now', $system);
        $this->assertStringContainsString('Paracetamol 500mg: 2 tablet left, reorder at 20', $system);
        $this->assertStringContainsString('You are Coco', $system);
        foreach (['Juanita', 'Zamorano', '09171234567', 'Severe migraine', $patient->patient_number] as $private) {
            $this->assertStringNotContainsString($private, $system);
        }
    }

    public function test_live_context_follows_permissions(): void
    {
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('staff'); // no view-medicines
        $staff->givePermissionTo('use-ai-assistant');
        $this->actingAs($staff);
        Medicine::factory()->create(['name' => 'Cetirizine 10mg', 'quantity' => 0]);
        $this->fakeAnswer();

        app(AiAssistantService::class)->chat('Hi', collect());

        $system = $this->sentPayload()['messages'][0]['content'];
        $this->assertStringContainsString('Appointments today:', $system);
        $this->assertStringNotContainsString('Cetirizine', $system);
        $this->assertStringNotContainsString('Low stock', $system);
    }
}
