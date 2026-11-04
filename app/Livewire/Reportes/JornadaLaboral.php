<?php

namespace App\Livewire\Reportes;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\WorkHoursApiService;
use App\Services\Api\WorkLunchReportApiService;
use App\Services\Auth\CurrentUserPermissions;
use App\Services\Reports\WorkLunchReportExporter;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function is_numeric;
use function is_scalar;

class JornadaLaboral extends Component
{
    use RemembersSelectorChoice;

    /** @var array<int, array<string, mixed>> */
    public array $jornadas = [];

    /** @var array<int, array{id: int, label: string}> */
    public array $users = [];

    /** @var array{work_minutes: int, lunch_minutes: int}|null */
    public ?array $schedule = null;

    public string $selectedUser = '';

    public string $fromDate = '';

    public string $toDate = '';

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public int $shortWorkCount = 0;

    public int $longLunchCount = 0;

    public bool $filterActive = false;

    public bool $showExportModal = false;

    public string $exportFormat = 'pdf';

    public ?string $loadError = null;

    public ?string $scheduleError = null;

    public function mount(): void
    {
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();

        if ($this->permissions()->allows('reports.worklunch.list-users')) {
            $this->loadUsers();
        }
    }

    public function applyFilters(): void
    {
        $this->authorizeAbility('reports.worklunch.filter');
        $this->resetValidation();

        $validated = $this->validate([
            'selectedUser' => ['required', 'integer', 'min:1'],
            'fromDate' => ['required', 'date_format:Y-m-d'],
            'toDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:fromDate'],
        ], [
            'selectedUser.required' => 'Selecciona un trabajador.',
            'selectedUser.integer' => 'Selecciona un trabajador válido.',
            'fromDate.required' => 'La fecha inicial es obligatoria.',
            'fromDate.date_format' => 'La fecha inicial no es válida.',
            'toDate.required' => 'La fecha final es obligatoria.',
            'toDate.date_format' => 'La fecha final no es válida.',
            'toDate.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        ]);

        $this->filterActive = true;
        $this->loadSchedule((int) $validated['selectedUser']);
        $this->loadResults(1);
    }

    public function clearFilters(): void
    {
        $this->reset([
            'selectedUser',
            'jornadas',
            'schedule',
            'total',
            'shortWorkCount',
            'longLunchCount',
            'filterActive',
            'showExportModal',
            'loadError',
            'scheduleError',
        ]);
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
        $this->page = 1;
        $this->lastPage = 1;
        $this->resetValidation();

        if ($this->permissions()->allows('reports.worklunch.list-users')) {
            $this->loadUsers();
        }
    }

    public function openExport(): void
    {
        $this->authorizeAbility('reports.worklunch.export');

        if (! $this->filterActive || $this->total === 0 || ! $this->hasActiveReport()) {
            $this->dispatchAlert(
                'No hay resultados para exportar',
                'Genera primero un reporte con al menos un resultado.',
            );

            return;
        }

        $this->exportFormat = 'pdf';
        $this->showExportModal = true;
        $this->resetValidation('exportFormat');
    }

    public function closeExport(): void
    {
        $this->showExportModal = false;
        $this->resetValidation('exportFormat');
    }

    public function exportReport(): ?StreamedResponse
    {
        $this->authorizeAbility('reports.worklunch.export');

        $validated = $this->validate([
            'exportFormat' => ['required', 'in:pdf,xlsx'],
        ]);

        if (! $this->filterActive || ! $this->hasActiveReport()) {
            $this->dispatchAlert(
                'No hay un reporte activo',
                'Aplica los filtros nuevamente antes de exportar.',
            );

            return null;
        }

        try {
            $rows = array_map(
                $this->normalizeRow(...),
                $this->reportApi()->allFiltered(
                    (int) $this->selectedUser,
                    $this->fromDate,
                    $this->toDate,
                ),
            );
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');

            return null;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible preparar la exportación', $exception->getMessage());

            return null;
        }

        if ($rows === []) {
            $this->dispatchAlert('No hay resultados para exportar', 'El reporte filtrado no contiene registros.');

            return null;
        }

        try {
            $content = $validated['exportFormat'] === 'pdf'
                ? $this->exporter()->pdf($rows, $this->criteriaSummary($rows))
                : $this->exporter()->excel($rows, $this->criteriaSummary($rows));
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatchAlert(
                'No fue posible generar el archivo',
                'Ocurrió un error al construir la exportación. Inténtalo nuevamente.',
            );

            return null;
        }

        $extension = $validated['exportFormat'];
        $contentType = $extension === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $filename = 'reporte-jornada-laboral-'.now()->format('Ymd-His').".{$extension}";
        $this->showExportModal = false;

        return response()->streamDownload(
            static function () use ($content): void {
                echo $content;
            },
            $filename,
            ['Content-Type' => $contentType],
        );
    }

    public function previousPage(): void
    {
        if ($this->filterActive && $this->page > 1) {
            $this->loadResults($this->page - 1);
        }
    }

    public function nextPage(): void
    {
        if ($this->filterActive && $this->page < $this->lastPage) {
            $this->loadResults($this->page + 1);
        }
    }

    public function render(): View
    {
        $permissions = $this->permissions();

        return view('livewire.reportes.jornada-laboral', [
            'access' => [
                'export' => $permissions->allows('reports.worklunch.export'),
            ],
        ]);
    }

    private function loadUsers(): void
    {
        $this->users = [];
    }

    private function loadSchedule(int $userId): void
    {
        $this->scheduleError = null;

        try {
            $record = $this->workHoursApi()->findByUser($userId);
            $workHours = (int) ($record['horas_lab'] ?? 0);
            $lunchMinutes = (int) ($record['horas_lunch'] ?? 0);

            $this->schedule = $workHours > 0 && $lunchMinutes > 0
                ? [
                    'work_minutes' => $workHours * 60,
                    'lunch_minutes' => $lunchMinutes,
                ]
                : null;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->schedule = null;
            $this->scheduleError = $exception->getMessage();
        }
    }

    private function loadResults(int $page): void
    {
        $this->loadError = null;

        try {
            $result = $this->reportApi()->filter(
                (int) $this->selectedUser,
                $this->fromDate,
                $this->toDate,
                $page,
            );

            $this->jornadas = array_map($this->normalizeRow(...), $result->items);
            $this->total = $result->total;
            $this->page = $result->currentPage;
            $this->lastPage = $result->lastPage;
            $this->shortWorkCount = count(array_filter(
                $this->jornadas,
                fn (array $row): bool => $row['work_status'] === 'short',
            ));
            $this->longLunchCount = count(array_filter(
                $this->jornadas,
                fn (array $row): bool => $row['lunch_status'] === 'exceeded',
            ));
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->jornadas = [];
            $this->total = 0;
            $this->page = 1;
            $this->lastPage = 1;
            $this->shortWorkCount = 0;
            $this->longLunchCount = 0;
            $this->loadError = $exception->getMessage();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $workComplete = filled($row['wkend_time'] ?? null);
        $lunchComplete = filled($row['lunch_start_time'] ?? null)
            && filled($row['lunch_end_time'] ?? null);
        $netWorkMinutes = $this->minutes(
            $row['net_work_minutes'] ?? null,
            $row['wkstart_time'] ?? null,
            $row['wkend_time'] ?? null,
            $row['lunch_start_time'] ?? null,
            $row['lunch_end_time'] ?? null,
        );
        $lunchMinutes = $this->minutes(
            $row['lunch_minutes'] ?? null,
            $row['lunch_start_time'] ?? null,
            $row['lunch_end_time'] ?? null,
        );
        $expectedWork = $this->schedule['work_minutes'] ?? null;
        $expectedLunch = $this->schedule['lunch_minutes'] ?? null;
        $workDeficit = $workComplete && $netWorkMinutes !== null && $expectedWork !== null
            ? max(0, $expectedWork - $netWorkMinutes)
            : 0;
        $lunchExcess = $lunchComplete && $lunchMinutes !== null && $expectedLunch !== null
            ? max(0, $lunchMinutes - $expectedLunch)
            : 0;
        $workStatus = match (true) {
            $this->schedule === null => 'unconfigured',
            ! $workComplete => 'pending',
            $workDeficit > 0 => 'short',
            default => 'compliant',
        };
        $lunchStatus = match (true) {
            $this->schedule === null => 'unconfigured',
            ! filled($row['lunch_start_time'] ?? null) => 'not_taken',
            ! $lunchComplete => 'pending',
            $lunchExcess > 0 => 'exceeded',
            default => 'compliant',
        };

        return [
            'id' => (int) ($row['id'] ?? 0),
            'date' => $this->formatDate($row['work_date'] ?? null),
            'date_iso' => $this->value($row['work_date'] ?? null),
            'user' => $this->value(data_get($row, 'usuario.nombre')),
            'device' => $this->value(data_get($row, 'device.displayname')),
            'stock' => $this->value(data_get($row, 'device.stock.descripcion')),
            'work_start' => $this->formatTime($row['wkstart_time'] ?? null),
            'work_start_iso' => $this->value($row['wkstart_time'] ?? null),
            'work_end' => $this->formatTime($row['wkend_time'] ?? null),
            'work_end_iso' => $this->value($row['wkend_time'] ?? null),
            'lunch_start' => $this->formatTime($row['lunch_start_time'] ?? null),
            'lunch_start_iso' => $this->value($row['lunch_start_time'] ?? null),
            'lunch_end' => $this->formatTime($row['lunch_end_time'] ?? null),
            'lunch_end_iso' => $this->value($row['lunch_end_time'] ?? null),
            'net_work_minutes' => $netWorkMinutes,
            'net_work_duration' => $this->duration($netWorkMinutes),
            'work_expected_minutes' => $expectedWork,
            'work_expected_duration' => $this->duration($expectedWork),
            'lunch_minutes' => $lunchMinutes,
            'lunch_duration' => $this->duration($lunchMinutes),
            'lunch_expected_minutes' => $expectedLunch,
            'lunch_expected_duration' => $this->duration($expectedLunch),
            'work_deficit_minutes' => $workDeficit,
            'lunch_excess_minutes' => $lunchExcess,
            'work_status' => $workStatus,
            'work_status_label' => $this->workStatusLabel($workStatus, $workDeficit),
            'lunch_status' => $lunchStatus,
            'lunch_status_label' => $this->lunchStatusLabel($lunchStatus, $lunchExcess),
        ];
    }

    private function workStatusLabel(string $status, int $deficit): string
    {
        return match ($status) {
            'short' => "Incompleta - faltaron {$deficit} min",
            'compliant' => 'Cumplida',
            'pending' => 'En curso',
            default => 'Sin horario asignado',
        };
    }

    private function lunchStatusLabel(string $status, int $excess): string
    {
        return match ($status) {
            'exceeded' => "Excedido por {$excess} min",
            'compliant' => 'Dentro del tiempo',
            'pending' => 'En almuerzo',
            'not_taken' => 'Sin almuerzo',
            default => 'Sin horario asignado',
        };
    }

    private function minutes(
        mixed $reported,
        mixed $start,
        mixed $end,
        mixed $excludedStart = null,
        mixed $excludedEnd = null,
    ): ?int {
        if (is_numeric($reported)) {
            return max(0, (int) $reported);
        }

        if (! filled($start) || ! filled($end)) {
            return null;
        }

        try {
            $minutes = max(
                0,
                (int) Carbon::parse((string) $start)
                    ->diffInMinutes(Carbon::parse((string) $end), false),
            );

            if (filled($excludedStart) && filled($excludedEnd)) {
                $minutes -= max(
                    0,
                    (int) Carbon::parse((string) $excludedStart)
                        ->diffInMinutes(Carbon::parse((string) $excludedEnd), false),
                );
            }

            return max(0, $minutes);
        } catch (Throwable) {
            return null;
        }
    }

    private function duration(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function formatDate(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (Throwable) {
            return null;
        }
    }

    private function formatTime(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('g:i A');
        } catch (Throwable) {
            return null;
        }
    }

    private function value(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function setUsers(array $items): void
    {
        $this->users = $this->options($items, 'nombre');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{id: int, label: string}>
     */
    private function options(array $items, string $labelKey): array
    {
        $options = [];

        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            $label = trim((string) ($item[$labelKey] ?? ''));

            if ($id > 0 && $label !== '') {
                $options[$id] = ['id' => $id, 'label' => $label];
            }
        }

        return array_values($options);
    }

    private function hasActiveReport(): bool
    {
        return (int) $this->selectedUser > 0
            && $this->fromDate !== ''
            && $this->toDate !== '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function criteriaSummary(array $rows): array
    {
        $selectedUser = collect($this->users)
            ->first(fn (array $user): bool => $user['id'] === (int) $this->selectedUser);
        $userName = is_array($selectedUser)
            ? $selectedUser['label']
            : ($rows[0]['user'] ?? "ID {$this->selectedUser}");
        $criteria = [
            "Trabajador: {$userName}",
            'Desde: '.($this->formatDate($this->fromDate) ?? $this->fromDate),
            'Hasta: '.($this->formatDate($this->toDate) ?? $this->toDate),
        ];

        if ($this->schedule !== null) {
            $criteria[] = 'Jornada esperada: '.$this->duration($this->schedule['work_minutes']);
            $criteria[] = 'Almuerzo permitido: '.$this->schedule['lunch_minutes'].' min';
        } else {
            $criteria[] = 'Horario: sin configurar';
        }

        return $criteria;
    }

    private function dispatchAlert(string $title, string $text): void
    {
        $this->dispatch(
            'sweetalert:alert',
            icon: 'error',
            title: $title,
            text: $text,
        );
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }

    private function permissions(): CurrentUserPermissions
    {
        return app(CurrentUserPermissions::class);
    }

    private function reportApi(): WorkLunchReportApiService
    {
        return app(WorkLunchReportApiService::class);
    }

    private function workHoursApi(): WorkHoursApiService
    {
        return app(WorkHoursApiService::class);
    }

    private function exporter(): WorkLunchReportExporter
    {
        return app(WorkLunchReportExporter::class);
    }
}
