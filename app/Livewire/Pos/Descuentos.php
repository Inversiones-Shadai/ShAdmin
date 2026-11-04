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
use Livewire\Attributes\Locked;
use Livewire\Component;

class Descuentos extends Component
{
    use RemembersSelectorChoice;

    #[Locked]
    public array $records = [];

    #[Locked]
    public array $references = [];

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?array $original = null;

    #[Locked]
    public ?array $pending = null;

    #[Locked]
    public int $page = 1;

    #[Locked]
    public int $lastPage = 1;

    #[Locked]
    public int $referencePage = 1;

    #[Locked]
    public int $referenceLastPage = 1;

    #[Locked]
    public ?array $selectedProduct = null;

    public bool $showProductPicker = false;

    public string $alcance = 'CATEGORIA';

    public string $referenceId = '';

    public string $search = '';

    public string $mode = 'HEREDAR';

    public bool $activo = true;

    public bool $showFormModal = false;

    public bool $readOnly = false;

    public ?string $loadError = null;

    public function mount(): void
    {
        $this->goToPage();
        $this->pending = session($this->pendingKey());
    }

    public function goToPage(int $page = 1): void
    {
        $this->authorizeAction('list');
        $this->records = [];
        $this->loadError = null;
        try {
            $result = $this->api()->list('descuentos', max(1, $page));
            $this->records = $result->items;
            $this->page = $result->currentPage;
            $this->lastPage = $result->lastPage;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    public function openCreate(): void
    {
        $this->authorizeAction('create');
        if ($this->pending !== null) {
            return;
        }
        $this->resetForm();
        $this->showFormModal = true;
        try {
            $this->searchReferences();
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->addError('form', $exception->getMessage());
        }
    }

    public function searchReferences(int $page = 1): void
    {
        $this->authorizeAction('create');
        abort_if($this->editingId !== null || $this->pending !== null, 409);
        $this->validate(['alcance' => 'required|in:CATEGORIA,PRODUCTO', 'search' => 'nullable|string|max:200']);
        try {
            if ($this->alcance === 'CATEGORIA') {
                $this->referenceId = '';
            }
            $this->references = [];
            $result = $this->api()->discountChoices($this->alcance, $this->search, max(1, $page));
            $this->references = $result->items;
            $this->referencePage = $result->currentPage;
            $this->referenceLastPage = $result->lastPage;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->addError('form', $exception->getMessage());
        }
    }

    public function updatedSearch(): void
    {
        if ($this->showFormModal && $this->editingId === null && $this->pending === null
            && ($this->alcance === 'CATEGORIA' || $this->showProductPicker)) {
            $this->searchReferences();
        }
    }

    public function openProductPicker(): void
    {
        $this->authorizeAction('create');
        abort_if($this->editingId !== null || $this->pending !== null || $this->alcance !== 'PRODUCTO', 409);
        $this->showProductPicker = true;
        $this->searchReferences();
    }

    public function selectProduct(int $id): void
    {
        $this->authorizeAction('create');
        abort_if($this->editingId !== null || $this->pending !== null || $this->alcance !== 'PRODUCTO' || ! $this->showProductPicker, 409);
        $product = collect($this->references)->first(fn (array $row): bool => (int) $row['id'] === $id);
        abort_unless($product !== null, 404);
        $this->selectedProduct = $product;
        $this->referenceId = (string) $product['id'];
        $this->showProductPicker = false;
        $this->resetValidation('referenceId');
    }

    public function closeProductPicker(): void
    {
        $this->showProductPicker = false;
    }

    public function updatedAlcance(): void
    {
        if ($this->editingId === null && $this->pending === null) {
            $this->referenceId = $this->search = '';
            $this->references = [];
            $this->selectedProduct = null;
            $this->showProductPicker = false;
            if ($this->alcance === 'CATEGORIA') {
                $this->searchReferences();
            }
        }
    }

    public function openRecord(int $id, bool $edit = false): void
    {
        $this->authorizeAction('search');
        if ($edit) {
            $this->authorizeAction('update');
        }
        if ($this->pending !== null) {
            return;
        }
        $this->resetForm();
        try {
            $record = $this->api()->find('descuentos', $id);
            abort_unless(($record['tipo'] ?? '') === 'DESCUENTO', 404);
            $this->editingId = $id;
            $this->original = $record;
            $this->readOnly = ! $edit;
            $this->alcance = $record['datos']['alcance'];
            $this->referenceId = (string) $record['datos']['referencia_id'];
            $this->activo = (bool) $record['activo'];
            $this->mode = self::generalMode($record['datos']);
            $this->showFormModal = true;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->addError('form', $exception->getMessage());
        }
    }

    public function save(): void
    {
        abort_if($this->readOnly, 403);
        $this->authorizeAction($this->editingId === null ? 'create' : 'update');
        if ($this->pending !== null) {
            $this->retry();

            return;
        }
        $this->validate(['alcance' => 'required|in:CATEGORIA,PRODUCTO',
            'referenceId' => 'required|integer|min:1', 'activo' => 'boolean',
            'mode' => 'required|in:HEREDAR,LOTE,EXCLUIR']);
        $payload = ['alcance' => $this->original['datos']['alcance'] ?? $this->alcance,
            'referencia_id' => $this->original['datos']['referencia_id'] ?? $this->referenceId,
            'activo' => $this->activo, 'regla' => ['modo' => $this->mode]];
        if ($this->editingId !== null) {
            $payload['revision_esperada'] = $this->original['datos']['revision'];
        }
        $this->queue('save', $this->editingId, $payload);
        $this->retry();
    }

    public function deactivate(int $id): void
    {
        $this->authorizeAction('deactivate');
        if ($this->pending !== null) {
            return;
        }
        $record = collect($this->records)->first(fn (array $r): bool => (int) $r['id'] === $id);
        abort_unless($record !== null, 404);
        $this->queue('deactivate', $id, ['revision_esperada' => $record['datos']['revision']]);
        $this->retry();
    }

    public function delete(int $id): void
    {
        $this->authorizeAction('delete');
        if ($this->pending !== null) {
            return;
        }
        $record = collect($this->records)->first(fn (array $r): bool => (int) $r['id'] === $id);
        abort_unless($record !== null, 404);
        $this->queue('delete', $id, ['revision_esperada' => $record['datos']['revision']]);
        $this->retry();
    }

    public function retry(): void
    {
        if ($this->pending === null) {
            return;
        }
        $operation = $this->pending;
        $this->resetValidation();
        $this->authorizeAction(match ($operation['operation']) {
            'deactivate' => 'deactivate',
            'delete' => 'delete',
            default => $operation['id'] === null ? 'create' : 'update',
        });
        try {
            $message = match ($operation['operation']) {
                'deactivate' => $this->api()->deactivate('descuentos', $operation['id'], $operation['request'], $operation['payload']),
                'delete' => $this->api()->delete('descuentos', $operation['id'], $operation['request'], $operation['payload']),
                default => $this->api()->save('descuentos', $operation['id'], $operation['payload'], $operation['request']),
            };
            $this->clearPending();
            $this->closeForm();
            $this->dispatch('sweetalert:toast', icon: 'success', title: $message);
            $this->goToPage($this->page);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            if ($exception->status >= 400 && $exception->status < 500 && ! in_array($exception->status, [408, 409], true)) {
                $this->clearPending();
            }
            $this->addError('form', $exception->getMessage());
        }
    }

    public function closeForm(): void
    {
        if ($this->pending !== null) {
            $this->showFormModal = true;

            return;
        }
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function updatedShowFormModal(): void
    {
        if (! $this->showFormModal && $this->pending !== null) {
            $this->showFormModal = true;
        }
    }

    public function render(): View
    {
        $permissions = app(CurrentUserPermissions::class);

        return view('livewire.pos.descuentos', ['access' => [
            'create' => $permissions->allows('discountspos.create'), 'show' => $permissions->allows('discountspos.search'),
            'edit' => $permissions->allowsAll(['discountspos.search', 'discountspos.update']),
            'deactivate' => $permissions->allows('discountspos.deactivate'),
            'delete' => $permissions->allows('discountspos.delete')]]);
    }

    public static function generalMode(array $data): string
    {
        if (($data['version'] ?? 1) === 2) {
            return $data['regla']['modo'] ?? '';
        }
        $modes = array_column($data['reglas'] ?? [], 'modo');

        return count($modes) === 3 && count(array_unique($modes)) === 1
            && in_array($modes[0], ['HEREDAR', 'LOTE', 'EXCLUIR'], true) ? $modes[0] : '';
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->original = null;
        $this->readOnly = false;
        $this->references = [];
        $this->selectedProduct = null;
        $this->showProductPicker = false;
        $this->referenceId = $this->search = '';
        $this->alcance = 'CATEGORIA';
        $this->mode = 'HEREDAR';
        $this->activo = true;
    }

    private function queue(string $operation, ?int $id, array $payload): void
    {
        $this->pending = ['operation' => $operation, 'id' => $id, 'payload' => $payload, 'request' => (string) Str::uuid()];
        session()->put($this->pendingKey(), $this->pending);
    }

    private function clearPending(): void
    {
        $this->pending = null;
        session()->forget($this->pendingKey());
    }

    private function pendingKey(): string
    {
        return 'pos.discount.pending.'.hash('sha256', (string) auth()->id());
    }

    private function authorizeAction(string $action): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'discountspos.'.$action);
    }

    private function api(): PosManagementApiService
    {
        return app(PosManagementApiService::class);
    }
}
