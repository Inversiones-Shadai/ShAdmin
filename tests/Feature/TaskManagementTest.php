<?php

namespace Tests\Feature;

use App\Livewire\Farma\Tareas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $failure = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.shadmin_api.base_url' => 'https://api.example.test/api', 'services.shadmin_api.device_header_secret' => 'test-secret']);
        $this->actingAs(User::factory()->create());
        session()->put('auth.api.token', 'test-token');
        $this->permissions([1, 2, 3, 4, 5, 6]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            '*/farma/tareas/listar*' => fn (Request $r) => Http::response(['code' => 200, 'data' => [[
                'id' => 1, 'stock_id' => 2, 'usuario_pos_id' => 7, 'stock_nombre' => 'TEST stock', 'usuario_nombre' => 'TEST user',
                'titulo' => 'TEST tarea', 'estado' => 'PENDIENTE', 'revision' => 1, 'updated_at' => '2026-10-17 10:00:00',
            ]], 'meta' => ['current_page' => (int) $r['page'], 'last_page' => 2, 'total' => 51]]),
            '*/farma/tareas/opciones' => Http::response(['code' => 200, 'data' => ['stocks' => [['id' => 2, 'descripcion' => 'TEST stock']], 'usuarios' => [['id' => 7, 'nombre' => 'TEST user']]]]),
            '*/farma/tareas/crear' => fn () => $this->mutation(),
            '*/farma/tareas/buscar/1' => Http::response(['code' => 200, 'data' => ['id' => 1, 'stock_id' => 2, 'usuario_pos_id' => 7,
                'titulo' => 'TEST tarea', 'descripcion' => 'TEST <script>bad</script>', 'revision' => 1, 'estado' => 'PENDIENTE']]),
            '*/farma/tareas/modificar/1' => fn () => $this->mutation(),
            '*/farma/tareas/cancelar/1' => fn () => $this->mutation(),
        ]);
    }

    private function mutation()
    {
        if ($this->failure > 0) {
            $status = $this->failure;
            $this->failure = 0;

            return Http::response(['code' => $status, 'message' => 'TEST fallo', 'data' => null], $status);
        }

        return Http::response(['code' => 200, 'data' => ['id' => 1]]);
    }

    private function permissions(array $actions): void
    {
        session()->put('auth.api.spermisos', [['modulo_l' => ['codigo' => 'frm'], 'vistas_l' => [['vistai' => ['codigo' => 'tar'],
            'accionesi' => array_map(fn (int $id): array => ['codigo' => 'tarbtn0'.$id], $actions)]]]]);
    }

    public function test_farma_menu_and_screen_require_task_list_permission(): void
    {
        $this->get(route('farma.tareas'))->assertOk()->assertSee('Tareas')->assertSee('TEST tarea');
        $this->get(route('dashboard'))->assertSee('permission-view-frm-tar');
        $this->permissions([]);
        $this->get(route('farma.tareas'))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee('permission-view-frm-tar');
    }

    public function test_creation_sends_only_the_selected_user_and_stock_and_a_request_identity(): void
    {
        Livewire::test(Tareas::class)->call('openCreate')->set('stockId', '2')->set('userId', '7')
            ->set('title', ' TEST nueva ')->set('description', ' TEST instrucciones ')->call('save')
            ->assertHasNoErrors()->assertSet('showEditor', false)->assertSet('pendingMutation', null);
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/crear') && $r->hasHeader('Action-Btn', 'tarbtn02')
            && $r['stock_id'] === 2 && $r['usuario_pos_id'] === 7 && $r['titulo'] === 'TEST nueva' && Str::isUuid($r['request_id']));
    }

    public function test_edit_and_cancel_use_independent_permissions_and_the_original_revision(): void
    {
        Livewire::test(Tareas::class)->call('openEdit', 1)->set('title', 'TEST editada')->call('save')->assertHasNoErrors()
            ->call('confirmCancel', 1)->call('cancelTask')->assertHasNoErrors()->assertSet('showCancel', false);
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/modificar/1') && $r['revision'] === 1 && $r->hasHeader('Action-Btn', 'tarbtn04'));
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/buscar/1') && $r->hasHeader('Action-Btn', 'tarbtn04'));
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/cancelar/1') && $r['revision'] === 1 && $r->hasHeader('Action-Btn', 'tarbtn05'));
        $this->permissions([1]);
        Livewire::test(Tareas::class)->assertDontSee('Crear tarea')->call('openCreate')->assertForbidden();
        Livewire::test(Tareas::class)->call('openEdit', 1)->assertForbidden();
        Livewire::test(Tareas::class)->call('confirmCancel', 1)->assertForbidden();
    }

    public function test_filters_are_live_reset_the_page_and_remain_applied_when_paginating(): void
    {
        Livewire::test(Tareas::class)->assertSee('data-remote-select="task-stocks"', false)
            ->assertSee('wire:model="search"', false)
            ->call('goToPage', 2)->assertSet('page', 2)->set('stockFilter', '2')->assertSet('page', 1)
            ->set('userFilter', '7')->set('stateFilter', 'EN_PROCESO')->set('search', 'TEST')->call('goToPage', 2);
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/listar') && ($r['stock_id'] ?? null) === '2' && ($r['usuario_pos_id'] ?? null) === '7'
            && ($r['estado'] ?? null) === 'EN_PROCESO' && ($r['buscar'] ?? null) === 'TEST' && $r['page'] === 2);
        $this->permissions([1]);
        Livewire::test(Tareas::class)->assertDontSee('Buscar tarea')->set('stockFilter', '2')->assertForbidden();
    }

    public function test_grouped_filters_wait_for_apply_and_remain_when_paginating(): void
    {
        Livewire::test(Tareas::class)->assertSee('data-test="task-filters-button"', false)
            ->call('goToPage', 2)->call('openFilters')->assertSet('showFiltersModal', true)
            ->set('stockFilter', '2')->set('userFilter', '7')->set('stateFilter', 'EN_PROCESO')->set('search', 'TEST')
            ->assertSet('page', 2)->call('applyFilters')->assertHasNoErrors()
            ->assertSet('page', 1)->assertSet('showFiltersModal', false)->call('goToPage', 2);
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/listar') && $r['page'] === 2
            && ($r['stock_id'] ?? null) === '2' && ($r['usuario_pos_id'] ?? null) === '7'
            && ($r['estado'] ?? null) === 'EN_PROCESO' && ($r['buscar'] ?? null) === 'TEST');
        $this->assertCount(4, Http::recorded());
    }

    public function test_cancel_restores_filters_and_clear_is_applied_only_when_confirmed(): void
    {
        $component = Livewire::test(Tareas::class)->call('openFilters')->set('stockFilter', '2')->set('search', 'TEST')
            ->call('cancelFilters')->assertSet('stockFilter', '')->assertSet('search', '')->assertSet('showFiltersModal', false);
        Http::assertSentCount(1);
        $component->call('openFilters')->set('stockFilter', '2')->set('search', 'TEST')->call('applyFilters')
            ->call('openFilters')->call('clearFilters')->assertSet('stockFilter', '')->call('cancelFilters')
            ->assertSet('stockFilter', '2')->assertSet('search', 'TEST')
            ->call('openFilters')->call('clearFilters')->call('applyFilters')->assertHasNoErrors()->assertSet('search', '');
        Http::assertSentCount(3);
    }

    public function test_filter_dialog_requires_its_permission_and_rejects_invalid_filters(): void
    {
        Livewire::test(Tareas::class)->call('openFilters')->set('stateFilter', 'INVALIDO')->call('applyFilters')
            ->assertHasErrors('stateFilter')->assertSet('showFiltersModal', true);
        Http::assertSentCount(1);
        $this->permissions([1]);
        Livewire::test(Tareas::class)->assertDontSee('data-test="task-filters-button"', false)->call('openFilters')->assertForbidden();
    }

    public function test_uncertain_result_retries_the_exact_request_even_if_the_browser_changes_the_fields(): void
    {
        $this->failure = 503;
        $component = Livewire::test(Tareas::class)->call('openCreate')->set('stockId', '2')->set('userId', '7')
            ->set('title', 'TEST original')->set('description', 'TEST instrucciones')->call('save')->assertHasErrors('operation');
        $request = $component->get('pendingMutation');
        $this->assertNotNull($request);
        $component->set('title', 'TEST otro')->call('save')->assertSet('pendingMutation', null)->assertSet('showEditor', false);
        $sent = Http::recorded(fn (Request $r): bool => $r->method() === 'POST')->values();
        $this->assertCount(2, $sent);
        $this->assertSame($sent[0][0]->data(), $sent[1][0]->data());
    }

    public function test_validation_and_confirmed_rejection_keep_the_form_editable(): void
    {
        $this->failure = 422;
        Livewire::test(Tareas::class)->call('openCreate')->call('save')->assertHasErrors(['stockId', 'userId', 'title', 'description'])
            ->set('stockId', '2')->set('userId', '7')->set('title', 'TEST')->set('description', 'TEST')
            ->call('save')->assertHasErrors('operation')->assertSet('pendingMutation', null)->assertSet('showEditor', true);
    }

    public function test_detail_escapes_task_instructions_and_requires_its_permission(): void
    {
        Livewire::test(Tareas::class)->call('openDetail', 1)->assertSee('TEST &lt;script&gt;bad&lt;/script&gt;', false)->assertDontSee('<script>bad</script>', false);
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/buscar/1') && $r->hasHeader('Action-Btn', 'tarbtn03'));
        $this->permissions([1]);
        Livewire::test(Tareas::class)->call('openDetail', 1)->assertForbidden();
    }

    public function test_calendar_selects_multiple_dates_across_months_and_can_remove_a_day(): void
    {
        Livewire::test(Tareas::class)->call('openCreate')->set('schedule', 'FECHAS')->set('calendarMonth', '2026-12')
            ->assertSee('task-calendar')->call('toggleDate', '2026-12-24')->call('toggleDate', '2026-12-25')
            ->call('changeMonth', 1)->assertSet('calendarMonth', '2027-01')->call('toggleDate', '2027-01-01')
            ->call('toggleDate', '2026-12-25')->assertSet('scheduledDates', ['2026-12-24', '2027-01-01'])
            ->set('stockId', '2')->set('userId', '7')->set('title', 'TEST agenda')->set('description', 'TEST fechas')
            ->call('save')->assertHasNoErrors();
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/crear') && $r['programacion'] === 'FECHAS'
            && $r['fechas'] === ['2026-12-24', '2027-01-01'] && $r['fecha_fin'] === null);
    }

    public function test_daily_schedule_has_an_optional_end_and_invalid_dates_do_not_reach_the_api(): void
    {
        Livewire::test(Tareas::class)->call('openCreate')->set('stockId', '2')->set('userId', '7')
            ->set('title', 'TEST diaria')->set('description', 'TEST')->set('schedule', 'DIARIA')
            ->set('startDate', '2026-12-01')->set('endDate', '2026-11-30')->call('save')->assertHasErrors('endDate')
            ->set('endDate', '')->call('save')->assertHasNoErrors();
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/crear') && $r['programacion'] === 'DIARIA'
            && $r['fecha_inicio'] === '2026-12-01' && $r['fecha_fin'] === null);
        Livewire::test(Tareas::class)->call('openCreate')->set('stockId', '2')->set('userId', '7')
            ->set('title', 'TEST fechas')->set('description', 'TEST')->set('schedule', 'FECHAS')->call('save')->assertHasErrors('scheduledDates');
    }

    public function test_uncertain_schedule_retry_keeps_original_dates(): void
    {
        $this->failure = 503;
        $component = Livewire::test(Tareas::class)->call('openCreate')->set('stockId', '2')->set('userId', '7')
            ->set('title', 'TEST')->set('description', 'TEST')->set('schedule', 'FECHAS')->call('toggleDate', '2026-12-24')
            ->call('save')->assertHasErrors('operation');
        $component->call('toggleDate', '2026-12-25')->assertSet('scheduledDates', ['2026-12-24'])
            ->set('scheduledDates', ['2027-01-01'])->call('save')->assertHasNoErrors();
        $sent = Http::recorded(fn (Request $r): bool => $r->method() === 'POST')->values();
        $this->assertSame($sent[0][0]->data(), $sent[1][0]->data());
    }
}
