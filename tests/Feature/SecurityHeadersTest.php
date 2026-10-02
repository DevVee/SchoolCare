<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * The Content-Security-Policy lets the page load from the Vite dev server only
 * while it runs locally (`npm run dev`). Before, it was blocked, so the page had
 * no styles or scripts and the sidebar and topbar did nothing.
 */
class SecurityHeadersTest extends TestCase
{
    private string $hot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hot = sys_get_temp_dir().'/vite-hot-'.uniqid();
        File::put($this->hot, 'http://127.0.0.1:5173');
        Vite::useHotFile($this->hot);
    }

    protected function tearDown(): void
    {
        File::delete($this->hot);
        parent::tearDown();
    }

    private function csp(): string
    {
        return (string) $this->get(route('login'))->assertOk()->headers->get('Content-Security-Policy');
    }

    public function test_local_dev_server_is_allowed_while_it_runs(): void
    {
        $this->app['env'] = 'local';

        $csp = $this->csp();

        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net http://127.0.0.1:5173", $csp);
        $this->assertStringContainsString('style-src ', $csp);
        $this->assertMatchesRegularExpression('/style-src [^;]*http:\/\/127\.0\.0\.1:5173/', $csp);
        $this->assertMatchesRegularExpression('/connect-src [^;]*ws:\/\/127\.0\.0\.1:5173/', $csp);
    }

    public function test_dev_server_is_never_allowed_outside_local(): void
    {
        foreach (['production', 'testing'] as $env) {
            $this->app['env'] = $env;
            $this->assertStringNotContainsString('5173', $this->csp(), $env);
        }
    }

    public function test_nothing_is_added_when_the_dev_server_is_not_running(): void
    {
        $this->app['env'] = 'local';
        File::delete($this->hot);

        $this->assertStringNotContainsString('5173', $this->csp());
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;", $this->csp());
    }
}
