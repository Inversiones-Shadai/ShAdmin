<?php

namespace App\Livewire\Auth;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\WorkLunchApiService;
use App\Services\Auth\CurrentUserPermissions;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Throwable;

class WorkLunchControls extends Component
{
    /** @var array<string, int|string|null>|null */
    public ?array $workLunch = null;

    public bool $enabled = false;

    public bool $canWork = false;

    public bool $canLunch = false;

    public ?string $loadError = null;

    public function mount(): void
    {
        $permissions = app(CurrentUserPermissions::class);
        $this->canWork = $permissions->allows('worklunch.work');
        $this->canLunch = $permissions->allows('worklunch.lunch');
        $this->enabled = filled(config('services.shadmin_api.base_url'))
            && filled(session('auth.api.token'))
            && ($this->canWork || $this->canLunch);

        if ($this->enabled) {
            $this->refreshState();
        }
    }

    public function refreshState(): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->loadError = null;

        try {
            $this->workLunch = $this->api()->today()?->toArray();
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    public function registerWork(): void
    {
        $this->authorizeAbility('worklunch.work');
        $this->register('work');
    }

    public function registerLunch(): void
    {
        $this->authorizeAbility('worklunch.lunch');
        $this->register('lunch');
    }

    public function render(): View
    {
        $workStarted = filled($this->workLunch['wkstart_time'] ?? null);
        $workCompleted = filled($this->workLunch['wkend_time'] ?? null);
        $lunchStarted = filled($this->workLunch['lunch_start_time'] ?? null);
        $lunchCompleted = filled($this->workLunch['lunch_end_time'] ?? null);
        $lunchOpen = $lunchStarted && ! $lunchCompleted;

        return view('livewire.auth.work-lunch-controls', [
            'statusLabel' => match (true) {
                $this->loadError !== null => 'Estado no disponible',
                $workCompleted => 'Jornada finalizada',
                $lunchOpen => 'En almuerzo',
                $workStarted => 'Jornada activa',
                default => 'Sin entrada',
            },
            'workLabel' => match (true) {
                $workCompleted => 'Jornada completada',
                $workStarted => 'Registrar salida',
                default => 'Registrar entrada',
            },
            'lunchLabel' => match (true) {
                $lunchCompleted => 'Almuerzo completado',
                $lunchStarted => 'Finalizar almuerzo',
                default => 'Iniciar almuerzo',
            },
            'workDisabled' => $this->loadError !== null || $workCompleted || $lunchOpen,
            'lunchDisabled' => $this->loadError !== null || ! $workStarted || $workCompleted || $lunchCompleted,
            'workStart' => $this->formatTime($this->workLunch['wkstart_time'] ?? null),
            'workEnd' => $this->formatTime($this->workLunch['wkend_time'] ?? null),
            'lunchStart' => $this->formatTime($this->workLunch['lunch_start_time'] ?? null),
            'lunchEnd' => $this->formatTime($this->workLunch['lunch_end_time'] ?? null),
        ]);
    }

    private function register(string $action): void
    {
        $this->loadError = null;

        try {
            $result = $action === 'work'
                ? $this->api()->work()
                : $this->api()->lunch();
            $this->workLunch = $result['session']->toArray();
            $this->dispatch(
                'sweetalert:toast',
                icon: 'success',
                title: $result['message'] !== '' ? $result['message'] : 'Horario registrado correctamente.',
            );
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->dispatch(
                'sweetalert:alert',
                icon: 'error',
                title: 'No fue posible registrar el horario',
                text: $exception->getMessage(),
            );
        }
    }

    private function formatTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('g:i A');
        } catch (Throwable) {
            return null;
        }
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }

    private function api(): WorkLunchApiService
    {
        return app(WorkLunchApiService::class);
    }
}
