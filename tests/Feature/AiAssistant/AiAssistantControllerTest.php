<?php

namespace Tests\Feature\AiAssistant;

use App\Models\AiConversation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $nurse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        config(['services.groq.api_key' => 'env-test-key']);
        Http::preventStrayRequests();

        $this->nurse = User::factory()->create(['is_active' => true]);
        $this->nurse->assignRole('nurse');
    }

    private function conversation(User $user): AiConversation
    {
        return AiConversation::create(['user_id' => $user->id, 'message' => 'Hello', 'response' => 'Hi there.', 'tokens_used' => 12]);
    }

    public function test_page_shows_suggestions_and_delete_buttons(): void
    {
        $this->actingAs($this->nurse)
            ->get(route('ai-assistant.index'))
            ->assertOk()
            ->assertSee('Ask Coco anything')
            ->assertSee('Write a parent notice');

        $convo = $this->conversation($this->nurse);

        $this->actingAs($this->nurse)
            ->get(route('ai-assistant.index', ['q' => 'What is today?']))
            ->assertOk()
            ->assertSee('Chat with Coco')
            ->assertSee(route('ai-assistant.destroy', $convo), false);
    }

    public function test_chat_returns_the_answer_and_a_delete_url(): void
    {
        Http::fake(['https://api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello!']]], 'usage' => ['total_tokens' => 9],
        ])]);

        $response = $this->actingAs($this->nurse)
            ->postJson(route('ai-assistant.chat'), ['message' => 'Hi'])
            ->assertOk()
            ->assertJsonPath('response', 'Hello!');

        $id = $response->json('id');
        $response->assertJsonPath('delete_url', route('ai-assistant.destroy', $id));
        $this->assertDatabaseHas('ai_conversations', ['id' => $id, 'user_id' => $this->nurse->id, 'tokens_used' => 9]);
    }

    public function test_user_can_delete_own_conversation(): void
    {
        $convo = $this->conversation($this->nurse);
        $keep  = $this->conversation($this->nurse);

        $this->actingAs($this->nurse)
            ->deleteJson(route('ai-assistant.destroy', $convo))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('ai_conversations', ['id' => $convo->id]);
        $this->assertDatabaseHas('ai_conversations', ['id' => $keep->id]);
    }

    public function test_delete_without_javascript_redirects_back_with_a_message(): void
    {
        $convo = $this->conversation($this->nurse);

        $this->actingAs($this->nurse)
            ->delete(route('ai-assistant.destroy', $convo))
            ->assertRedirect(route('ai-assistant.index'))
            ->assertSessionHas('success', 'Question deleted.');
    }

    public function test_user_cannot_delete_someone_elses_conversation(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('nurse');
        $theirs = $this->conversation($other);

        $this->actingAs($this->nurse)
            ->deleteJson(route('ai-assistant.destroy', $theirs))
            ->assertNotFound();

        $this->assertDatabaseHas('ai_conversations', ['id' => $theirs->id]);
    }

    public function test_guest_and_users_without_the_permission_cannot_delete(): void
    {
        $convo = $this->conversation($this->nurse);

        $this->deleteJson(route('ai-assistant.destroy', $convo))->assertUnauthorized();

        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('staff'); // no use-ai-assistant
        $this->actingAs($staff)->deleteJson(route('ai-assistant.destroy', $convo))->assertForbidden();

        $this->assertDatabaseHas('ai_conversations', ['id' => $convo->id]);
    }
}
