<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalLoaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_layout_allows_native_livewire_progress_without_a_global_loader(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('wire:navigate', false)
            ->assertDontSee('data-no-progress-bar', false)
            ->assertDontSee('id="global-loader"', false)
            ->assertDontSee('x-persist="global-loader"', false);
    }

    public function test_login_layout_allows_native_livewire_progress_without_a_global_loader(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('wire:navigate', false)
            ->assertDontSee('data-no-progress-bar', false)
            ->assertDontSee('id="global-loader"', false)
            ->assertDontSee('x-persist="global-loader"', false);
    }
}
