<?php

namespace App\Livewire\Pos;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\PosManagementApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Catalogos extends Component
{
    use RemembersSelectorChoice;

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $records = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $stocks = [];

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $deactivatingId = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $originalIdentity = null;

    #[Locked]
    public ?string $pendingId = null;

    #[Locked]
    public ?string $pendingDigest = null;

    public string $stockId = '';

    public string $codigo = '';

    public string $descripcion = '';

    public bool $esEfectivo = false;

    public bool $requiereReferencia = false;

    public bool $activo = true;

    public bool $showFormModal = false;

    public bool $showDeactivateModal = false;

    #[Locked]
    public string $deactivatingName = '';

    #[Locked]
    public int $page = 1;

    #[Locked]
    public int $lastPage = 1;

    #[Locked]
    public int $total = 0;

    public ?string $loadError = null;

    public function mount(): void
    {
        $this->goToPage();
    }

    public function goToPage(int $page = 1): void
    {
        $this->authorizeAction('list');
        $this->loadError = null;
        $this->records = [];
        try {
            $result = $this->api()->list('catalogos', max(1, $page));
            $this->records = $result->items;
            $this->page = $result->currentPage;
            $this->lastPage = $result->lastPage;
            $this->total = $result->total;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    public function openCreate(): void
    {
        $this->authorizeAction('create');
        if (! $this->canStart()) {
            return;
        }
        $this->resetForm();
        $this->showFormModal = true;
        if (! app(CurrentUserPermissions::class)->allows('stocks.list')) {
            $this->addError('form', 'Necesitas el permiso de listar Stocks para seleccionar el establecimiento.');

            return;
        }
        $this->stocks = [];
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAction('search');
        $this->authorizeAction('update');
        if (! $this->canStart()) {
            return;
        }
        $this->resetForm();
        try {
            $record = $this->api()->find('catalogos', $id);
            abort_unless(($record['tipo'] ?? '') === 'MEDIO_PAGO', 404);
            $this->editingId = $id;
            $this->stockId = (string) $record['stock_id'];
            $this->codigo = (string) $record['datos']['codigo'];
            $this->originalIdentity = ['stock_id' => (int) $record['stock_id'], 'codigo' => $record['codigo'], 'payment_code' => $this->codigo];
            $this->descripcion = (string) $record['datos']['descripcion'];
            $this->esEfectivo = (bool) $record['datos']['es_efectivo'];
            $this->requiereReferencia = (bool) $record['datos']['requiere_referencia'];
            $this->activo = (bool) $record['activo'];
            $this->showFormModal = true;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->reportError($exception);
        }
    }

    public function save(): void
    {
        $this->authorizeAction($this->editingId === null ? 'create' : 'update');
        $this->resetValidation();
        $this->descripcion = trim(strip_tags($this->descripcion));
        if ($this->editingId === null) {
            Gate::authorize(CurrentUserPermissions::GATE, 'stocks.list');
            $this->codigo = trim($this->codigo);
            $this->validate(['stockId' => ['required', 'integer', Rule::in(array_column($this->stocks, 'id'))],
                'codigo' => ['required', 'string', 'max:30', 'regex:/\A[A-Za-z0-9._-]+\z/']]);
        } else {
            $this->authorizeAction('search');
            abort_unless($this->originalIdentity !== null, 409);
        }
        $this->validate(['descripcion' => ['required', 'string', 'max:100'], 'activo' => ['boolean'],
            'esEfectivo' => ['boolean'], 'requiereReferencia' => ['boolean']]);
        $payload = ['tipo' => 'MEDIO_PAGO', 'stock_id' => $this->originalIdentity['stock_id'] ?? (int) $this->stockId,
            'codigo' => $this->originalIdentity['codigo'] ?? $this->codigo, 'activo' => $this->activo,
            'datos' => ['codigo' => $this->originalIdentity['payment_code'] ?? $this->codigo,
                'descripcion' => $this->descripcion, 'es_efectivo' => $this->esEfectivo, 'requiere_referencia' => $this->requiereReferencia]];
        try {
            $message = $this->api()->save('catalogos', $this->editingId, $payload, $this->requestId('save:'.($this->editingId ?? 'new'), $payload));
            $this->clearPending();
            $this->closeForm();
            $this->dispatch('sweetalert:toast', icon: 'success', title: $message);
            $this->goToPage($this->page);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->reportError($exception, true);
        }
    }

    public function closeForm(): void
    {
        if (! $this->canStart()) {
            $this->showFormModal = true;

            return;
        }
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function updatedShowFormModal(): void
    {
        if (! $this->showFormModal) {
            $this->closeForm();
        }
    }

    public function confirmDeactivate(int $id): void
    {
        $this->authorizeAction('deactivate');
        if (! $this->canStart()) {
            return;
        }
        $record = collect($this->records)->first(fn (array $item): bool => (int) $item['id'] === $id);
        abort_unless(is_array($record) && ($record['tipo'] ?? '') === 'MEDIO_PAGO', 404);
        $this->deactivatingId = $id;
        $this->deactivatingName = (string) $record['datos']['descripcion'];
        $this->resetValidation();
        $this->showDeactivateModal = true;
    }

    public function deactivate(): void
    {
        $this->authorizeAction('deactivate');
        if ($this->deactivatingId === null) {
            return;
        }
        try {
            $message = $this->api()->deactivate('catalogos', $this->deactivatingId, $this->requestId('deactivate:'.$this->deactivatingId, []));
            $this->clearPending();
            $this->cancelDeactivate();
            $this->dispatch('sweetalert:toast', icon: 'success', title: $message);
            $this->goToPage($this->page);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->reportError($exception, true);
        }
    }

    public function cancelDeactivate(): void
    {
        if (! $this->canStart()) {
            $this->showDeactivateModal = true;

            return;
        }
        $this->showDeactivateModal = false;
        $this->deactivatingId = null;
        $this->resetValidation();
    }

    public function updatedShowDeactivateModal(): void
    {
        if (! $this->showDeactivateModal) {
            $this->cancelDeactivate();
        }
    }

    public function render(): View
    {
        $permissions = app(CurrentUserPermissions::class);

        return view('livewire.pos.catalogos', ['access' => [
            'create' => $permissions->allows('catalogospos.create'),
            'edit' => $permissions->allowsAll(['catalogospos.search', 'catalogospos.update']),
            'deactivate' => $permissions->allows('catalogospos.deactivate'),
        ]]);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->originalIdentity = null;
        $this->stocks = [];
        $this->stockId = $this->codigo = $this->descripcion = '';
        $this->esEfectivo = $this->requiereReferencia = false;
        $this->activo = true;
    }

    private function authorizeAction(string $action): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'catalogospos.'.$action);
    }

    private function api(): PosManagementApiService
    {
        return app(PosManagementApiService::class);
    }

    /** @param array<string, mixed> $payload */
    private function requestId(string $operation, array $payload): string
    {
        $digest = hash('sha256', $operation.json_encode($payload, JSON_THROW_ON_ERROR));
        if ($this->pendingId !== null && $this->pendingDigest !== $digest) {
            throw new ApiOperationException('La solicitud sigue sin confirmar. Reintenta con los datos originales.', status: 409);
        }
        $this->pendingDigest = $digest;

        return $this->pendingId ??= (string) Str::uuid();
    }

    private function clearPending(): void
    {
        $this->pendingId = $this->pendingDigest = null;
    }

    private function canStart(): bool
    {
        if ($this->pendingId !== null) {
            $this->addError('form', 'La solicitud sigue sin confirmar. Reintenta con los datos originales antes de continuar.');

            return false;
        }

        return true;
    }

    private function reportError(ApiOperationException $exception, bool $writing = false): void
    {
        if ($writing && $exception->status >= 400 && $exception->status < 500 && ! in_array($exception->status, [408, 409], true)) {
            $this->clearPending();
        }
        foreach ($exception->errors as $field => $messages) {
            $field = match ($field) {
                'stock_id' => 'stockId', 'datos.codigo' => 'codigo', 'datos.descripcion' => 'descripcion',
                'datos.es_efectivo' => 'esEfectivo', 'datos.requiere_referencia' => 'requiereReferencia', default => $field,
            };
            $this->addError($field, (string) ((array) $messages)[0]);
        }
        if ($exception->errors === []) {
            $this->addError('form', $exception->getMessage());
        }
    }
}
