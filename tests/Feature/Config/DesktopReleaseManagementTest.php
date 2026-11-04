<?php

namespace Tests\Feature\Config;

use App\Livewire\Config\DesktopReleases;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DesktopReleaseManagementTest extends TestCase
{
    use RefreshDatabase;

    private const string RELEASE = '10000000-0000-4000-8000-000000000001';

    private const string ARTIFACT = '20000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'synthetic-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'synthetic-token');
    }

    private function permissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [['modulo_l' => ['codigo' => 'cfg'], 'vistas_l' => [[
            'vistai' => ['codigo' => 'upd'], 'accionesi' => array_map(fn ($code) => ['codigo' => $code], $actions),
        ]]]]);
    }

    private function row(array $changes = []): array
    {
        return array_replace(['id' => self::RELEASE, 'artifact_id' => self::ARTIFACT, 'product' => 'shpos',
            'version' => '1.0.1.0', 'architecture' => 'x64', 'status' => 'draft', 'revision' => 1,
            'notes' => 'Synthetic release notes', 'min_api_contract' => 1, 'min_schema_version' => 258,
            'max_schema_version' => null, 'required' => false, 'pilot_count' => 0, 'current_pilot' => false, 'current_stable' => false], $changes);
    }

    private function fake(?callable $mutation = null, array $row = [], array $reads = []): void
    {
        Http::fake(['api.example.test/api/config/desktop-releases*' => function ($request) use ($mutation, $row, $reads) {
            if ($request->method() !== 'GET') {
                return $mutation ? $mutation($request) : Http::response(['code' => 200, 'data' => ['id' => self::RELEASE, 'revision' => 2, 'status' => 'draft']]);
            }
            foreach ($reads as $suffix => $response) {
                if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), $suffix)) {
                    return is_callable($response) ? $response($request) : Http::response($response);
                }
            }
            if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/artifacts')) {
                return Http::response(['code' => 200, 'data' => [['id' => self::ARTIFACT, 'product' => 'shpos', 'version' => '1.0.1.0', 'architecture' => 'x64']], 'meta' => ['current_page' => 1, 'last_page' => 1]]);
            }
            if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/pilots')) {
                return Http::response(['code' => 200, 'data' => ['revision' => 1, 'selected' => [], 'devices' => ['data' => [], 'current_page' => 1, 'last_page' => 1]]]);
            }
            if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/events')) {
                return Http::response(['code' => 200, 'data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1]]);
            }

            return Http::response(['code' => 200, 'data' => [$this->row($row)], 'meta' => ['current_page' => 1, 'last_page' => 1]]);
        }]);
    }

    public function test_route_and_direct_component_require_update_permission(): void
    {
        $this->permissions([]);
        $this->get(route('config.desktop-releases'))->assertForbidden();
        Livewire::test(DesktopReleases::class)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_list_permission_shows_menu_and_notes_but_does_not_grant_writes(): void
    {
        $this->permissions(['updbtn01']);
        $this->fake();
        $this->get(route('dashboard'))->assertOk()->assertSee('Actualizaciones de escritorio');
        Livewire::test(DesktopReleases::class)->assertSee('1.0.1.0')->assertDontSee('Nuevo borrador')
            ->call('detail', self::RELEASE)->assertSee('Synthetic release notes')
            ->call('create')->assertForbidden();
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_creates_only_from_registered_artifact_without_accepting_storage_metadata(): void
    {
        $this->permissions(['updbtn01', 'updbtn02']);
        $this->fake();
        Livewire::test(DesktopReleases::class)->call('create')->set('artifactId', self::ARTIFACT)
            ->set('notes', 'Synthetic draft notes')->set('minSchema', 258)->call('save')->assertHasNoErrors()->assertSet('pending', null);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->hasHeader('Action-Btn', 'updbtn02')
            && $request['artifact_id'] === self::ARTIFACT && Str::isUuid($request['request_id'])
            && ! array_key_exists('version', $request->data()) && ! array_key_exists('object_id', $request->data()));
    }

    public function test_pilot_stable_and_audit_are_independent_permissions(): void
    {
        $this->permissions(['updbtn01', 'updbtn05']);
        $this->fake();
        Livewire::test(DesktopReleases::class)->assertSee('Publicar en piloto')->assertDontSee('Auditoría')
            ->call('requestTransition', self::RELEASE, 'stable')->assertForbidden();
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_uncertain_result_preserves_original_request_payload_and_uuid_across_reload(): void
    {
        $this->permissions(['updbtn01', 'updbtn02']);
        $attempts = [];
        $this->fake(function ($request) use (&$attempts) {
            $attempts[] = $request->data();
            if (count($attempts) === 1) {
                throw new ConnectionException('Synthetic timeout');
            }

            return Http::response(['code' => 200, 'data' => ['id' => self::RELEASE, 'revision' => 1, 'status' => 'draft']]);
        });
        Livewire::test(DesktopReleases::class)->call('create')->set('artifactId', self::ARTIFACT)
            ->set('notes', 'Frozen original notes')->call('save')->assertSee('Confirmación pendiente');
        Livewire::test(DesktopReleases::class)->set('notes', 'Must not replace pending notes')->call('retry')->assertSet('pending', null);
        $this->assertCount(2, $attempts);
        $this->assertSame($attempts[0], $attempts[1]);
        $this->assertSame('Frozen original notes', $attempts[1]['notes']);
    }

    public function test_revision_conflict_closes_stale_form_and_requires_fresh_selection(): void
    {
        $this->permissions(['updbtn01', 'updbtn03']);
        $this->fake(fn () => Http::response(['code' => 409, 'message' => 'La versión cambió.'], 409));
        Livewire::test(DesktopReleases::class)->call('edit', self::RELEASE)->set('notes', 'Changed notes')->call('save')
            ->assertSet('pending', null)->assertSet('showForm', false)->assertSee('La versión cambió.');
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request['expected_revision'] === 1);
    }

    public function test_withdrawal_requires_reason_and_uses_separate_action(): void
    {
        $this->permissions(['updbtn01', 'updbtn07']);
        $this->fake();
        Livewire::test(DesktopReleases::class)->call('requestTransition', self::RELEASE, 'withdraw')->call('confirmTransition')->assertHasErrors('reason')
            ->set('reason', 'Synthetic withdrawal reason')->call('confirmTransition')->assertHasNoErrors();
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/withdraw') && $request->hasHeader('Action-Btn', 'updbtn07')
            && $request['reason'] === 'Synthetic withdrawal reason');
    }

    public function test_pilot_selection_preserves_other_pages_and_uses_revision_from_device_response(): void
    {
        $this->permissions(['updbtn01', 'updbtn04']);
        $first = '30000000-0000-4000-8000-000000000001';
        $other = '30000000-0000-4000-8000-000000000002';
        $this->fake(reads: ['/pilots' => ['code' => 200, 'data' => ['revision' => 7, 'selected' => [$first, $other],
            'devices' => ['data' => [['id' => $first, 'device_name' => 'Synthetic Pilot PC', 'license_label' => 'Synthetic Pilot License']], 'current_page' => 1, 'last_page' => 2]]]]);
        Livewire::test(DesktopReleases::class)->call('pilots', self::RELEASE)->assertSet('showPilots', true)
            ->assertSee('Synthetic Pilot PC')->assertSee('Synthetic Pilot License')->assertSee($other)
            ->call('removeDevice', $first)->call('savePilots')->assertSet('pending', null);
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request->hasHeader('Action-Btn', 'updbtn04')
            && $request['activation_ids'] === [$other] && $request['expected_revision'] === 7);
    }

    public function test_failed_initial_pilot_read_does_not_open_form_with_previous_selection(): void
    {
        $this->permissions(['updbtn01', 'updbtn04']);
        $this->fake(reads: ['/pilots' => fn () => Http::response(['code' => 503, 'message' => 'Synthetic read unavailable'], 503)]);
        Livewire::test(DesktopReleases::class)->set('selectedDevices', ['30000000-0000-4000-8000-000000000001'])
            ->call('pilots', self::RELEASE)->assertSet('showPilots', false)->assertSet('selectedDevices', [])
            ->assertSet('devices', [])->assertSee('Synthetic read unavailable');
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_audit_renders_actor_local_time_reason_and_escapes_snapshot_content(): void
    {
        $this->permissions(['updbtn01', 'updbtn08']);
        $this->fake(reads: ['/events' => ['code' => 200, 'data' => [['id' => 1, 'action' => 'withdraw',
            'revision' => 4, 'actor_id' => 7, 'created_at' => '2026-10-28 17:00:00',
            'snapshot' => json_encode(['reason' => 'Synthetic event reason', 'notes' => '<script>alert(1)</script>'])]],
            'meta' => ['current_page' => 1, 'last_page' => 1]]]);
        Livewire::test(DesktopReleases::class)->call('audit', self::RELEASE)->assertSee('Synthetic event reason')
            ->assertSee('Usuario 7')->assertSee('28/10/2026 11:00')->assertDontSee('<script>alert(1)', false);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/events') && $request->hasHeader('Action-Btn', 'updbtn08'));
    }
}
