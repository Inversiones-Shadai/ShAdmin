<?php

namespace App\Livewire\Farma;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\TaskApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Tareas extends Component
{
    use RemembersSelectorChoice;

    public const STATES = ['PENDIENTE' => 'Pendiente', 'EN_PROCESO' => 'En proceso', 'COMPLETADA' => 'Completada', 'CANCELADA' => 'Cancelada'];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $records = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $options = [];

    #[Locked]
    public int $page = 1;

    #[Locked]
    public int $lastPage = 1;

    #[Locked]
    public int $total = 0;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public int $revision = 0;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $pendingMutation = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $selectedTask = null;

    public string $stockFilter = '';

    public string $userFilter = '';

    public string $stateFilter = '';

    public string $search = '';

    public bool $showFiltersModal = false;

    #[Locked]
    public array $filterSnapshot = [];

    public string $stockId = '';

    public string $userId = '';

    public string $title = '';

    public string $description = '';

    public string $schedule = 'UNICA';

    public string $startDate = '';

    public string $endDate = '';

    /** @var array<int, string> */
    public array $scheduledDates = [];

    public string $calendarMonth = '';

    public bool $showEditor = false;

    public bool $showCancel = false;

    public bool $showDetail = false;

    public ?string $loadError = null;

    public function mount(): void
    {
        $this->goToPage();
        if (app(CurrentUserPermissions::class)->allows('tasks.filter')) {
            $this->loadOptions('tarbtn06');
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['stockFilter', 'userFilter', 'stateFilter', 'search'], true)) {
            $this->authorizeTask('filter');
            if (! $this->showFiltersModal) {
                $this->goToPage();
            }
        }
    }

    public function openFilters(): void
    {
        $this->authorizeTask('filter');
        $this->filterSnapshot = ['stockFilter' => $this->stockFilter, 'userFilter' => $this->userFilter,
            'stateFilter' => $this->stateFilter, 'search' => $this->search];
        $this->resetValidation(array_keys($this->filterSnapshot));
        $this->showFiltersModal = true;
    }

    public function cancelFilters(): void
    {
        foreach ($this->filterSnapshot as $field => $value) {
            $this->{$field} = $value;
        }
        $this->filterSnapshot = [];
        $this->showFiltersModal = false;
        $this->resetValidation(['stockFilter', 'userFilter', 'stateFilter', 'search']);
    }

    public function clearFilters(): void
    {
        $this->authorizeTask('filter');
        $this->stockFilter = $this->userFilter = $this->stateFilter = $this->search = '';
        $this->resetValidation(['stockFilter', 'userFilter', 'stateFilter', 'search']);
    }

    public function applyFilters(): void
    {
        $this->authorizeTask('filter');
        $this->validate(['stockFilter' => ['nullable', 'integer', 'min:1'], 'userFilter' => ['nullable', 'integer', 'min:1'],
            'stateFilter' => ['nullable', 'in:'.implode(',', array_keys(self::STATES))], 'search' => ['string', 'max:200']]);
        $this->goToPage();
        if ($this->loadError !== null) {
            return;
        }
        $this->filterSnapshot = [];
        $this->showFiltersModal = false;
    }

    public function goToPage(int $page = 1): void
    {
        $this->authorizeTask('list');
        $filters = array_filter(['stock_id' => $this->stockFilter, 'usuario_pos_id' => $this->userFilter,
            'estado' => $this->stateFilter, 'buscar' => trim($this->search)], fn ($v): bool => $v !== '');
        if ($filters !== []) {
            $this->authorizeTask('filter');
        }
        $this->loadError = null;
        try {
            $result = app(TaskApiService::class)->list(max(1, min(100000, $page)), $filters);
            $this->records = $result->items;
            $this->page = $result->currentPage;
            $this->lastPage = $result->lastPage;
            $this->total = $result->total;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->records = [];
            $this->total = 0;
            $this->loadError = $error->getMessage();
        }
    }

    public function openCreate(): void
    {
        $this->authorizeTask('create');
        if ($this->pendingMutation !== null || ! $this->loadOptions('tarbtn02')) {
            return;
        }
        $this->resetValidation();
        $this->editingId = null;
        $this->revision = 0;
        $this->stockId = $this->stockFilter;
        $this->userId = $this->title = $this->description = '';
        $this->schedule = 'UNICA';
        $this->startDate = now('America/Tegucigalpa')->toDateString();
        $this->endDate = '';
        $this->scheduledDates = [];
        $this->calendarMonth = substr($this->startDate, 0, 7);
        $this->showEditor = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeTask('update');
        if ($this->pendingMutation !== null || ! $this->loadOptions('tarbtn04')) {
            return;
        }
        $task = $this->detailedRecord($id, 'tarbtn04');
        if ($task === null) {
            return;
        }
        abort_if($task['estado'] === 'CANCELADA', 409);
        $this->resetValidation();
        $this->editingId = $id;
        $this->revision = (int) $task['revision'];
        $this->stockId = (string) $task['stock_id'];
        $this->userId = (string) $task['usuario_pos_id'];
        $this->title = $task['titulo'];
        $this->description = $task['descripcion'];
        $this->schedule = $task['programacion'] ?? 'UNICA';
        $this->startDate = $task['fecha_inicio'] ?? now('America/Tegucigalpa')->toDateString();
        $this->endDate = $task['fecha_fin'] ?? '';
        $this->scheduledDates = $task['fechas'] ?? [];
        $this->calendarMonth = substr($this->startDate, 0, 7);
        $this->showEditor = true;
    }

    public function openDetail(int $id): void
    {
        $this->authorizeTask('show');
        $this->selectedTask = $this->detailedRecord($id, 'tarbtn03');
        $this->showDetail = $this->selectedTask !== null;
    }

    public function confirmCancel(int $id): void
    {
        $this->authorizeTask('cancel');
        if ($this->pendingMutation !== null) {
            return;
        }
        $this->selectedTask = $this->record($id);
        abort_if($this->selectedTask['estado'] === 'CANCELADA', 409);
        $this->showCancel = true;
    }

    public function save(): void
    {
        $this->authorizeTask($this->editingId === null ? 'create' : 'update');
        if (! $this->showEditor) {
            return;
        }
        if ($this->pendingMutation === null) {
            $this->validate(['stockId' => ['required', 'integer', 'min:1'], 'userId' => ['required', 'integer', 'min:1'],
                'title' => ['required', 'string', 'max:200', 'regex:/\S/u'], 'description' => ['required', 'string', 'max:4000', 'regex:/\S/u'],
                'schedule' => ['required', 'in:UNICA,DIARIA,FECHAS'], 'startDate' => ['required', 'date_format:Y-m-d'],
                'endDate' => $this->schedule === 'DIARIA' ? ['nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'] : ['nullable', 'date_format:Y-m-d'],
                'scheduledDates' => $this->schedule === 'FECHAS' ? ['required', 'array', 'min:1', 'max:366'] : ['array'],
                'scheduledDates.*' => ['required', 'date_format:Y-m-d', 'distinct']]);
            $data = ['request_id' => (string) Str::uuid(), 'stock_id' => (int) $this->stockId, 'usuario_pos_id' => (int) $this->userId,
                'titulo' => trim($this->title), 'descripcion' => trim($this->description),
                'programacion' => $this->schedule, 'fecha_inicio' => $this->startDate,
                'fecha_fin' => $this->schedule === 'DIARIA' && $this->endDate !== '' ? $this->endDate : null,
                'fechas' => $this->schedule === 'FECHAS' ? $this->scheduledDates : []];
            if ($this->editingId !== null) {
                $data['revision'] = $this->revision;
            }
            $this->pendingMutation = ['action' => $this->editingId === null ? 'crear' : 'modificar', 'id' => $this->editingId, 'data' => $data];
        }
        $this->sendPending();
    }

    public function cancelTask(): void
    {
        $this->authorizeTask('cancel');
        if (! $this->showCancel || $this->selectedTask === null) {
            return;
        }
        $this->pendingMutation ??= ['action' => 'cancelar', 'id' => (int) $this->selectedTask['id'],
            'data' => ['request_id' => (string) Str::uuid(), 'revision' => (int) $this->selectedTask['revision']]];
        $this->sendPending();
    }

    public function changeMonth(int $offset): void
    {
        $this->authorizeTask($this->editingId === null ? 'create' : 'update');
        if ($this->pendingMutation !== null || ! $this->showEditor || ! in_array($offset, [-1, 1], true)) {
            return;
        }
        $this->validate(['calendarMonth' => ['required', 'date_format:Y-m']]);
        $this->calendarMonth = Carbon::createFromFormat('!Y-m', $this->calendarMonth)->addMonths($offset)->format('Y-m');
    }

    public function toggleDate(string $date): void
    {
        $this->authorizeTask($this->editingId === null ? 'create' : 'update');
        if ($this->pendingMutation !== null || ! $this->showEditor || $this->schedule !== 'FECHAS') {
            return;
        }
        validator(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->validate();
        if (in_array($date, $this->scheduledDates, true)) {
            $this->scheduledDates = array_values(array_diff($this->scheduledDates, [$date]));
        } elseif (count($this->scheduledDates) < 366) {
            $this->scheduledDates[] = $date;
            sort($this->scheduledDates);
        }
        $this->resetValidation('scheduledDates');
    }

    private function sendPending(): void
    {
        $pending = $this->pendingMutation;
        try {
            app(TaskApiService::class)->mutate($pending['action'], $pending['id'], $pending['data']);
            $this->pendingMutation = null;
            $this->showEditor = $this->showCancel = false;
            $this->dispatch('sweetalert:toast', icon: 'success', title: 'Tarea guardada correctamente.');
            $this->goToPage($this->page);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            if (in_array($error->getCode(), [400, 403, 404, 409, 422], true)) {
                $this->pendingMutation = null;
            }
            $this->addError('operation', $error->getMessage());
        }
    }

    private function loadOptions(string $action): bool
    {
        return true;
    }

    /** @return array<string, mixed>|null */
    private function detailedRecord(int $id, string $action): ?array
    {
        $record = $this->record($id);
        try {
            return [...$record, ...app(TaskApiService::class)->find($id, $action)];
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->loadError = $error->getMessage();
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function record(int $id): array
    {
        $task = collect($this->records)->firstWhere('id', $id);
        abort_unless(is_array($task), 404);

        return $task;
    }

    private function authorizeTask(string $action): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'tasks.'.$action);
    }

    public function render(): View
    {
        $this->authorizeTask('list');
        $permissions = app(CurrentUserPermissions::class);
        $access = [];
        foreach (['create', 'show', 'update', 'cancel', 'filter'] as $action) {
            $access[$action] = $permissions->allows('tasks.'.$action);
        }

        $calendar = [];
        $month = preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $this->calendarMonth) ? $this->calendarMonth : now('America/Tegucigalpa')->format('Y-m');
        $first = Carbon::createFromFormat('!Y-m', $month);
        $first->locale('es');
        $day = $first->copy()->startOfWeek(Carbon::MONDAY);
        for ($i = 0; $i < 42; $i++, $day->addDay()) {
            $calendar[] = ['date' => $day->toDateString(), 'day' => $day->day, 'inMonth' => $day->format('Y-m') === $month,
                'selected' => in_array($day->toDateString(), $this->scheduledDates, true)];
        }

        return view('livewire.farma.tareas', ['access' => $access, 'states' => self::STATES,
            'calendar' => $calendar, 'calendarLabel' => $first->translatedFormat('F Y')]);
    }
}
