<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_remote_accounts_can_change_appearance_but_not_local_credentials(): void
    {
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api']);
        $this->actingAs(User::factory()->create());
        $this->get(route('settings'))->assertRedirect(route('appearance.edit'));
        $this->get(route('appearance.edit'))->assertOk()->assertDontSee(route('profile.edit'));
        $this->get(route('profile.edit'))->assertNotFound();
        $this->get(route('security.edit'))->assertNotFound();
        $this->get(route('well-known.passkeys'))->assertNotFound();
    }

    public function test_api_configuration_disables_local_registration_recovery_and_passkeys(): void
    {
        $previous = $_ENV['SHADMIN_API_URL'] ?? '';
        try {
            $_ENV['SHADMIN_API_URL'] = 'https://api.example.test/api';
            $_SERVER['SHADMIN_API_URL'] = $_ENV['SHADMIN_API_URL'];
            $this->assertSame([], (require config_path('fortify.php'))['features']);
        } finally {
            $_ENV['SHADMIN_API_URL'] = $previous;
            $_SERVER['SHADMIN_API_URL'] = $previous;
        }
    }
}
