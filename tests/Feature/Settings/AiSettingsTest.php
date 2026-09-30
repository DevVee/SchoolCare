<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'gsk_test_secret_value_WXYZ';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        config(['services.groq.api_key' => '']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('administrator');
    }

    private function saveAi(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->put(route('admin.settings.update', 'ai'), $overrides + [
            'ai_enabled'        => '1',
            'ai_assistant_name' => 'Coco',
            'ai_model'          => 'openai/gpt-oss-120b',
            'ai_web_search'     => '1',
        ]);
    }

    public function test_groq_key_is_stored_encrypted(): void
    {
        $this->saveAi(['ai_groq_api_key' => '  '.self::KEY.' '])->assertSessionHasNoErrors();

        $stored = Setting::where('key', 'ai_groq_api_key')->value('value');
        $this->assertNotSame(self::KEY, $stored);
        $this->assertStringNotContainsString(self::KEY, $stored);
        $this->assertSame(self::KEY, Crypt::decryptString($stored));
        $this->assertSame(self::KEY, settings('ai_groq_api_key'));
    }

    public function test_groq_key_is_never_rendered_or_audited(): void
    {
        $this->saveAi(['ai_groq_api_key' => self::KEY])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('admin.settings.edit', 'ai'))
            ->assertOk()
            ->assertDontSee(self::KEY)
            ->assertDontSee(Setting::where('key', 'ai_groq_api_key')->value('value'))
            ->assertSee('Set, ends in ...WXYZ')
            ->assertSee('Coco is on');

        $log = AuditLog::where('module', 'settings')->latest('id')->first();
        $this->assertSame('[hidden]', $log->new_values['ai_groq_api_key']);
        $this->assertStringNotContainsString(self::KEY, json_encode($log->toArray()));
    }

    public function test_empty_box_keeps_the_key_and_remove_clears_it(): void
    {
        $this->saveAi(['ai_groq_api_key' => self::KEY]);

        $this->saveAi(['ai_groq_api_key' => ''])->assertSessionHasNoErrors();
        $this->assertSame(self::KEY, settings('ai_groq_api_key'));

        $this->saveAi(['ai_groq_api_key' => '', 'remove_ai_groq_api_key' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('', settings('ai_groq_api_key'));
        $this->assertSame('', Setting::where('key', 'ai_groq_api_key')->value('value'));
    }

    public function test_status_says_where_the_key_comes_from(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'ai'))
            ->assertSee('is on, but not connected')
            ->assertSee('No Groq API key is set');

        config(['services.groq.api_key' => 'server-key']);
        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'ai'))
            ->assertSee('comes from the server setting', false)
            ->assertDontSee('server-key');
    }

    public function test_only_offered_models_can_be_saved(): void
    {
        $this->saveAi(['ai_model' => 'llama-3.3-70b-versatile'])->assertSessionHasErrors('ai_model');
        $this->saveAi(['ai_model' => 'qwen/qwen3.8-27b'])->assertSessionHasNoErrors();
        $this->assertSame('qwen/qwen3.8-27b', settings('ai_model'));
    }
}
