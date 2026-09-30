<?php

namespace Tests\Feature\Dashboard;

use App\Models\Appointment;
use App\Models\Medicine;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardBriefTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        config(['services.groq.api_key' => '']);
        Http::preventStrayRequests();

        Medicine::factory()->create(['name' => 'Paracetamol 500mg', 'quantity' => 0, 'low_stock_threshold' => 20]);
        Appointment::factory()->create(['status' => 'pending']);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_guests_cannot_see_the_brief(): void
    {
        $this->getJson(route('dashboard.brief'))->assertUnauthorized();
        $this->get(route('dashboard.brief'))->assertRedirect(route('login'));
    }

    public function test_rules_brief_when_no_api_key(): void
    {
        $response = $this->actingAs($this->user('nurse'))
            ->getJson(route('dashboard.brief'))
            ->assertOk()
            ->assertJsonStructure(['lines' => [['text', 'href']], 'source', 'generated_at'])
            ->assertJsonPath('source', 'rules');

        $lines = $response->json('lines');
        $this->assertGreaterThanOrEqual(3, count($lines));
        $this->assertLessThanOrEqual(5, count($lines));
        // Most important first: out of stock, then the waiting request.
        $this->assertSame('1 medicine is out of stock: Paracetamol 500mg.', $lines[0]['text']);
        $this->assertSame(route('medicines.low-stock'), $lines[0]['href']);
        $this->assertStringStartsWith('1 appointment request is waiting for approval', $lines[1]['text']);
        $this->assertSame(route('appointments.index', ['status' => 'pending']), $lines[1]['href']);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(120, mb_strlen($line['text']));
            $this->assertDoesNotMatchRegularExpression('/[\x{2013}\x{2014}]/u', $line['text']);
        }
        $this->assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $response->json('generated_at')));
    }

    public function test_brief_only_includes_what_the_user_may_see(): void
    {
        // Staff cannot view medicines.
        $json = $this->actingAs($this->user('staff'))->getJson(route('dashboard.brief'))->assertOk()->json();

        $text = implode(' ', array_column($json['lines'], 'text'));
        $this->assertStringNotContainsString('Paracetamol', $text);
        $this->assertStringNotContainsString('medicine', strtolower($text));
        $this->assertNotContains(route('medicines.low-stock'), array_column($json['lines'], 'href'));
        $this->assertStringContainsString('appointment request', $text);
    }

    public function test_ai_brief_is_validated_and_links_come_from_the_facts(): void
    {
        config(['services.groq.api_key' => 'env-test-key']);
        Http::fake(['https://api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['lines' => [
            ['text' => 'Paracetamol is out of stock — reorder today', 'fact' => 'f1', 'href' => 'https://evil.example'],
            ['text' => 'One appointment request is waiting for approval.', 'fact' => 'f2'],
            ['text' => 'No clinic visits logged yet today.', 'fact' => 'f999'],
        ]])]]]])]);

        $json = $this->actingAs($this->user('nurse'))->getJson(route('dashboard.brief'))->assertOk()->json();

        $this->assertSame('ai', $json['source']);
        $this->assertSame('Paracetamol is out of stock, reorder today.', $json['lines'][0]['text']);
        $this->assertSame(route('medicines.low-stock'), $json['lines'][0]['href']);
        $this->assertSame(route('appointments.index', ['status' => 'pending']), $json['lines'][1]['href']);
        $this->assertNull($json['lines'][2]['href']);
        Http::assertSent(fn ($r) => $r['reasoning_effort'] === 'low' && $r['response_format'] === ['type' => 'json_object']);
    }

    public function test_bad_ai_answer_falls_back_to_rules(): void
    {
        config(['services.groq.api_key' => 'env-test-key']);
        Http::fake(['https://api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '{"lines":[{"text":"Only one line"}]}']]]])]);

        $this->actingAs($this->user('nurse'))
            ->getJson(route('dashboard.brief'))
            ->assertOk()
            ->assertJsonPath('source', 'rules');
    }
}
