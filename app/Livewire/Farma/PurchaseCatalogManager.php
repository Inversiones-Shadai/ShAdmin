<?php

namespace App\Livewire\Farma;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\PurchaseCatalogApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class PurchaseCatalogManager extends Component
{
    public string $catalog;

    /** @var array<int, array<string, mixed>> */
    public array $records = [];

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public string $search = '';

    public string $descripcion = '';

    public ?string $loadError = null;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public function mount(string $catalog): void
    {
        abort_unless(is_array(config('farma_purchase_catalogs.'.$catalog)), 404);
        $this->catalog = $catalog;
        $this->loadPage();
    }

    public function openCreate(): void
    {
        $this->authorizeAction('create');
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAction('search');
        $this->authorizeAction('update');
        $this->resetForm();
        try {
            $record = $this->api()->find($this->catalog, $id);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');

            return;
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible cargar el registro', $exception->getMessage());

            return;
        }
        $this->editingId = $id;
        $this->descripcion = (string) ($record['descripcion'] ?? '');
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->authorizeAction($this->editingId === null ? 'create' : 'update');
        $validated = $this->validate(['descripcion' => ['required', 'string', 'max:255']], ['descripcion.required' => 'La descripción es obligatoria.']);
        $description = mb_substr(trim(strip_tags($validated['descripcion'])), 0, 255);
        try {
            $message = $this->editingId === null ? $this->api()->create($this->catalog, $description) : $this->api()->update($this->catalog, $this->editingId, $description);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');

            return;
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible guardar el registro', $exception->getMessage());

            return;
        }
        $this->showFormModal = false;
        $this->toast($message ?: 'Registro guardado correctamente.');
        $this->resetForm();
        $this->loadPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAction('delete');
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $this->authorizeAction('delete');
        if ($this->deletingId === null) {
            return;
        }
        try {
            $message = $this->api()->delete($this->catalog, $this->deletingId);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');

            return;
        } catch (ApiOperationException $exception) {
            $this->showDeleteModal = false;
            $this->alert('No fue posible eliminar el registro', $exception->getMessage());

            return;
        }
        $this->showDeleteModal = false;
        $this->toast($message ?: 'Registro eliminado correctamente.');
        $this->loadPage();
    }

    public function closeForm(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
    }

    public function previousPage(): void
    {
        $this->loadPage(max(1, $this->page - 1));
    }

    public function nextPage(): void
    {
        $this->loadPage(min($this->lastPage, $this->page + 1));
    }

    public function updatedSearch(): void
    {
        $this->loadPage(1);
    }

    public function render(): View
    {
        $definition = config('farma_purchase_catalogs.'.$this->catalog);
        $visible = $this->records;
        $permissions = app(CurrentUserPermissions::class);

        return view('livewire.farma.purchase-catalog-manager', ['definition' => $definition, 'visibleRecords' => $visible, 'access' => [
            'create' => $permissions->allows($this->ability('create')),
            'edit' => $permissions->allowsAll([$this->ability('search'), $this->ability('update')]),
            'delete' => $permissions->allows($this->ability('delete')),
        ]]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAction('list');
        $this->loadError = null;
        try {
            $result = $this->api()->list($this->catalog, $page, $this->search);
            $this->applyPage($result);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->records = $page->items;
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->descripcion = '';
    }

    private function ability(string $action): string
    {
        return 'purchase-catalogs.'.$this->catalog.'.'.$action;
    }

    private function authorizeAction(string $action): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $this->ability($action));
    }

    private function api(): PurchaseCatalogApiService
    {
        return app(PurchaseCatalogApiService::class);
    }

    private function toast(string $message): void
    {
        $this->dispatch('sweetalert:toast', icon: 'success', title: $message);
    }

    private function alert(string $title, string $message): void
    {
        $this->dispatch('sweetalert:alert', icon: 'error', title: $title, text: $message);
    }
}
