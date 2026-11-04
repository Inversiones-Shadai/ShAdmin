<?php

namespace Tests\Feature;

use Illuminate\Routing\Router;
use Laravel\Boost\BoostServiceProvider;
use Laravel\Boost\Middleware\InjectBoost;
use Tests\TestCase;

class BrowserInstrumentationTest extends TestCase
{
    public function test_browser_instrumentation_is_disabled_by_default_for_normal_and_livewire_navigation(): void
    {
        $this->assertFalse(config('boost.browser_logs_watcher'));
        $this->bootBoostLocally();

        $this->assertNotContains(InjectBoost::class, app(Router::class)->getMiddlewareGroups()['web']);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('wire:navigate', false)
            ->assertDontSee('browser-logger-active', false);

        $this->get(route('login'), ['X-Livewire-Navigate' => '1'])
            ->assertOk()
            ->assertDontSee('browser-logger-active', false);
    }

    public function test_disabling_browser_instrumentation_preserves_the_post_only_logs_endpoint(): void
    {
        $this->bootBoostLocally();

        $this->assertSame('single', config('logging.channels.browser.driver'));
        $this->assertSame(storage_path('logs/browser.log'), config('logging.channels.browser.path'));

        $this->postJson('/_boost/browser-logs', ['logs' => []])
            ->assertOk()
            ->assertJson(['status' => 'logged']);

        $this->getJson('/_boost/browser-logs')->assertStatus(405);
    }

    private function bootBoostLocally(): void
    {
        $this->app->instance('env', 'local');
        $provider = new BoostServiceProvider($this->app);
        $provider->register();
        $provider->boot(app(Router::class));
    }
}
