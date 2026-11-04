<?php

namespace App\Livewire\Config;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\DesktopReleaseApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DesktopReleases extends Component
{
    #[Locked]
    public array $releases = [];

    public int $page = 1;

    public int $lastPage = 1;

    public string $product = '';

    public string $status = '';

    public string $search = '';

    public ?string $error = null;

    public string $message = '';

    #[Locked]
    public ?string $detailId = null;

    public bool $showForm = false;

    #[Locked]
    public ?string $editingId = null;

    #[Locked]
    public int $formRevision = 1;

    #[Locked]
    public array $artifacts = [];

    public int $artifactPage = 1;

    public int $artifactLastPage = 1;

    public string $artifactSearch = '';

    public string $artifactId = '';

    public string $notes = '';

    public int $minApi = 1;

    public int $minSchema = 0;

    public string $maxSchema = '';

    public bool $requiredUpdate = false;

    public bool $showPilots = false;

    #[Locked]
    public ?string $pilotId = null;

    #[Locked]
    public int $pilotRevision = 1;

    #[Locked]
    public array $devices = [];

    public array $selectedDevices = [];

    public int $devicePage = 1;

    public int $deviceLastPage = 1;

    public string $deviceSearch = '';

    public bool $showTransition = false;

    #[Locked]
    public ?string $transitionId = null;

    #[Locked]
    public string $transitionAction = '';

    #[Locked]
    public int $transitionRevision = 1;

    public string $reason = '';

    #[Locked]
    public ?string $auditId = null;

    #[Locked]
    public array $events = [];

    public int $eventPage = 1;

    public int $eventLastPage = 1;

    #[Locked]
    public ?array $pending = null;

    public function mount(): void
    {
        $this->authorizeAbility('list');
        $this->pending = session($this->pendingKey());
        $this->loadPage();
    }

    public function updatedProduct(): void
    {
        $this->page = 1;
        $this->loadPage();
    }

    public function updatedStatus(): void
    {
        $this->page = 1;
        $this->loadPage();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->loadPage();
    }

    public function updatedArtifactSearch(): void
    {
        $this->artifactPage = 1;
        $this->loadArtifacts();
    }

    public function updatedDeviceSearch(): void
    {
        $this->devicePage = 1;
        $this->loadDevices(false);
    }

    public function changePage(int $delta): void
    {
        $this->page = max(1, min($this->lastPage, $this->page + $delta));
        $this->loadPage();
    }

    public function create(): void
    {
        $this->authorizeAbility('create');
        $this->assertNoPending();
        $this->resetValidation();
        $this->editingId = null;
        $this->artifactId = '';
        $this->notes = '';
        $this->minApi = 1;
        $this->minSchema = 0;
        $this->maxSchema = '';
        $this->requiredUpdate = false;
        $this->artifactPage = 1;
        $this->artifactSearch = '';
        $this->showForm = true;
        $this->loadArtifacts();
    }

    public function edit(string $id): void
    {
        $this->authorizeAbility('edit');
        $this->assertNoPending();
        $row = $this->row($id);
        abort_unless($row['status'] === 'draft', 409);
        $this->resetValidation();
        $this->editingId = $id;
        $this->formRevision = (int) $row['revision'];
        $this->notes = (string) $row['notes'];
        $this->minApi = (int) $row['min_api_contract'];
        $this->minSchema = (int) $row['min_schema_version'];
        $this->maxSchema = isset($row['max_schema_version']) ? (string) $row['max_schema_version'] : '';
        $this->requiredUpdate = (bool) $row['required'];
        $this->showForm = true;
    }

    public function changeArtifactPage(int $delta): void
    {
        $this->artifactPage = max(1, min($this->artifactLastPage, $this->artifactPage + $delta));
        $this->loadArtifacts();
    }

    public function save(): void
    {
        $action = $this->editingId ? 'edit' : 'create';
        $this->authorizeAbility($action);
        $this->assertNoPending();
        $this->validate(['notes' => ['required', 'string', 'min:3', 'max:10000'],
            'minApi' => ['required', 'integer', 'min:1', 'max:65535'], 'minSchema' => ['required', 'integer', 'min:0', 'max:65535'],
            'maxSchema' => ['nullable', 'integer', 'gte:minSchema', 'max:65535']]
            + ($action === 'create' ? ['artifactId' => ['required', 'uuid']] : []));
        $payload = ['notes' => $this->notes, 'min_api_contract' => $this->minApi, 'min_schema_version' => $this->minSchema,
            'max_schema_version' => $this->maxSchema === '' ? null : (int) $this->maxSchema, 'required' => $this->requiredUpdate];
        $payload += $action === 'create' ? ['artifact_id' => $this->artifactId] : ['expected_revision' => $this->formRevision];
        $this->start($action, $this->editingId, $payload);
    }

    public function pilots(string $id): void
    {
        $this->authorizeAbility('pilots');
        $this->assertNoPending();
        $row = $this->row($id);
        abort_unless(in_array($row['status'], ['draft', 'pilot'], true), 409);
        $this->pilotId = $id;
        $this->pilotRevision = (int) $row['revision'];
        $this->devicePage = 1;
        $this->deviceSearch = '';
        $this->showPilots = false;
        $this->selectedDevices = [];
        $this->loadDevices(true);
    }

    public function changeDevicePage(int $delta): void
    {
        $this->devicePage = max(1, min($this->deviceLastPage, $this->devicePage + $delta));
        $this->loadDevices(false);
    }

    public function removeDevice(string $id): void
    {
        $this->authorizeAbility('pilots');
        $this->assertNoPending();
        $this->selectedDevices = array_values(array_diff($this->selectedDevices, [$id]));
    }

    public function savePilots(): void
    {
        $this->authorizeAbility('pilots');
        $this->assertNoPending();
        $this->validate(['selectedDevices' => ['array', 'max:500'], 'selectedDevices.*' => ['uuid', 'distinct']]);
        abort_unless($this->pilotId !== null, 422);
        $this->start('pilots', $this->pilotId, ['activation_ids' => array_values($this->selectedDevices), 'expected_revision' => $this->pilotRevision]);
    }

    public function requestTransition(string $id, string $action): void
    {
        abort_unless(in_array($action, ['pilot', 'stable', 'withdraw'], true), 422);
        $this->authorizeAbility($action);
        $this->assertNoPending();
        $row = $this->row($id);
        $this->transitionId = $id;
        $this->transitionRevision = (int) $row['revision'];
        $this->transitionAction = $action;
        $this->reason = '';
        $this->showTransition = true;
    }

    public function confirmTransition(): void
    {
        $this->authorizeAbility($this->transitionAction);
        $this->assertNoPending();
        abort_unless($this->transitionId !== null, 422);
        $payload = ['expected_revision' => $this->transitionRevision];
        if ($this->transitionAction === 'withdraw') {
            $this->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
            $payload['reason'] = $this->reason;
        }
        $this->start($this->transitionAction, $this->transitionId, $payload);
    }

    public function audit(string $id): void
    {
        $this->authorizeAbility('audit');
        $this->row($id);
        $this->auditId = $id;
        $this->eventPage = 1;
        $this->loadEvents();
    }

    public function changeEventPage(int $delta): void
    {
        $this->eventPage = max(1, min($this->eventLastPage, $this->eventPage + $delta));
        $this->loadEvents();
    }

    public function closeAudit(): void
    {
        $this->auditId = null;
        $this->events = [];
    }

    public function detail(string $id): void
    {
        $this->authorizeAbility('list');
        $this->row($id);
        $this->detailId = $id;
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    public function retry(): void
    {
        abort_unless($this->pending !== null, 422);
        $this->authorizeAbility($this->pending['action']);
        $this->error = null;
        try {
            app(DesktopReleaseApiService::class)->execute($this->pending['action'], $this->pending['id'], $this->pending['payload']);
            session()->forget($this->pendingKey());
            $this->pending = null;
            $this->showForm = $this->showPilots = $this->showTransition = false;
            $this->message = 'Cambio confirmado y registrado en auditoría.';
            $this->loadPage();
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login', navigate: true);
        } catch (ApiOperationException $error) {
            $this->error = $error->getMessage();
            if (in_array($error->status, [400, 404, 409, 422], true)) {
                session()->forget($this->pendingKey());
                $this->pending = null;
                if ($error->status === 409) {
                    $this->showForm = $this->showPilots = $this->showTransition = false;
                    $this->loadPage();
                }
            }
        }
    }

    private function start(string $action, ?string $id, array $payload): void
    {
        $this->message = '';
        $this->pending = ['action' => $action, 'id' => $id, 'payload' => $payload + ['request_id' => (string) Str::uuid()]];
        session()->put($this->pendingKey(), $this->pending);
        $this->retry();
    }

    private function pendingKey(): string
    {
        return 'desktop_release_pending.'.auth()->id();
    }

    private function assertNoPending(): void
    {
        abort_if($this->pending !== null, 409, 'Primero confirma la solicitud pendiente con Reintentar.');
    }

    private function authorizeAbility(string $action): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'desktop-releases.'.$action);
    }

    private function row(string $id): array
    {
        $row = collect($this->releases)->firstWhere('id', $id);
        abort_unless(is_array($row), 404);

        return $row;
    }

    private function read(callable $operation): void
    {
        try {
            $operation();
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login', navigate: true);
        } catch (ApiOperationException $error) {
            $this->error = $error->getMessage();
        }
    }

    private function loadPage(): void
    {
        $this->authorizeAbility('list');
        $this->releases = [];
        $this->detailId = $this->auditId = null;
        $this->events = [];
        $this->read(function (): void {
            $result = app(DesktopReleaseApiService::class)->listing($this->page, $this->product, $this->status, $this->search);
            $this->releases = $result->items;
            $this->page = $result->currentPage;
            $this->lastPage = $result->lastPage;
        });
    }

    private function loadArtifacts(): void
    {
        $this->authorizeAbility('create');
        $this->artifacts = [];
        $this->read(function (): void {
            $result = app(DesktopReleaseApiService::class)->artifacts($this->artifactPage, $this->product, $this->artifactSearch);
            $this->artifacts = $result->items;
            $this->artifactPage = $result->currentPage;
            $this->artifactLastPage = $result->lastPage;
        });
    }

    private function loadDevices(bool $initial): void
    {
        $this->authorizeAbility('pilots');
        $this->devices = [];
        if ($this->pilotId === null) {
            return;
        }
        $this->read(function () use ($initial): void {
            $result = app(DesktopReleaseApiService::class)->pilots($this->pilotId, $this->devicePage, $this->deviceSearch);
            $this->devices = (array) data_get($result, 'devices.data', []);
            $this->devicePage = (int) data_get($result, 'devices.current_page', 1);
            $this->deviceLastPage = (int) data_get($result, 'devices.last_page', 1);
            if ($initial) {
                $this->selectedDevices = (array) ($result['selected'] ?? []);
                $this->pilotRevision = (int) $result['revision'];
                $this->showPilots = true;
            }
        });
    }

    private function loadEvents(): void
    {
        $this->authorizeAbility('audit');
        $this->events = [];
        if ($this->auditId === null) {
            return;
        }
        $this->read(function (): void {
            $result = app(DesktopReleaseApiService::class)->events($this->auditId, $this->eventPage);
            $this->events = $result->items;
            $this->eventPage = $result->currentPage;
            $this->eventLastPage = $result->lastPage;
        });
    }

    public function render(): View
    {
        $this->authorizeAbility('list');

        return view('livewire.config.desktop-releases', ['access' => app(CurrentUserPermissions::class)]);
    }
}
