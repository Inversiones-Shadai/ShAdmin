<?php

namespace App\Livewire\Pos;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\ClienteposApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Clientes extends Component
{
    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $clients = [];

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public string $search = '';

    public ?string $loadError = null;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $deletingId = null;

    public string $deletingName = '';

    public string $nombre = '';

    public string $rtn = '';

    public string $tipo_documento = '';

    public string $fecha_nacimiento = '';

    #[Locked]
    public bool $legacyDocument = false;

    #[Locked]
    public string $originalDocument = '';

    public string $telefono = '';

    public string $direccion = '';

    public function mount(): void
    {
        $this->loadPage();
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr(trim($this->search), 0, 180);
        $this->loadPage();
    }

    public function openCreate(): void
    {
        $this->authorizeAbility('clientepos.create');
        $this->resetValidation();
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAbility('clientepos.search');
        $this->authorizeAbility('clientepos.update');
        $this->resetValidation();
        $this->resetForm();

        try {
            $client = $this->api()->find($id);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');

            return;
        } catch (ApiOperationException $exception) {
            $this->alert($exception->getMessage());

            return;
        }

        $this->editingId = $id;
        foreach (['nombre', 'rtn', 'telefono', 'direccion', 'tipo_documento', 'fecha_nacimiento'] as $field) {
            $this->{$field} = (string) ($client[$field] ?? '');
        }
        $this->legacyDocument = ($client['tipo_documento'] ?? null) === null;
        $this->originalDocument = $this->rtn;
        $this->showFormModal = true;
    }

    public function closeForm(): void
    {
        $this->showFormModal = false;
        $this->resetValidation();
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorizeAbility($this->editingId === null ? 'clientepos.create' : 'clientepos.update');
        if ($this->editingId !== null) {
            $this->authorizeAbility('clientepos.search');
        }

        $preserveLegacy = $this->editingId !== null && $this->legacyDocument && $this->tipo_documento === '' && $this->rtn === $this->originalDocument;
        foreach (['nombre', 'rtn', 'telefono', 'direccion', 'tipo_documento', 'fecha_nacimiento'] as $field) {
            $this->{$field} = trim(strip_tags($this->{$field}));
        }
        $this->rtn = $preserveLegacy ? $this->originalDocument : strtoupper(preg_replace('/[\s-]+/u', '', $this->rtn) ?? '');
        $validated = $this->validate([
            'nombre' => ['required', 'string', 'max:180'],
            'tipo_documento' => [$preserveLegacy ? 'nullable' : 'required', 'in:IDENTIDAD,RTN'],
            'rtn' => $preserveLegacy ? ['nullable', 'string', 'max:20'] : ['required', 'string', 'regex:'.($this->tipo_documento === 'IDENTIDAD' ? '/\A(?!0+\z)[0-9]{13}\z/' : '/\A(?!0+\z)[0-9]{14}\z/')],
            'fecha_nacimiento' => [$this->tipo_documento === 'IDENTIDAD' ? 'required' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Tegucigalpa')->toDateString()],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ], [
            'nombre.required' => 'El nombre completo o razón social es obligatorio.',
            'tipo_documento.required' => 'Selecciona si el documento es Identidad o RTN.',
            'rtn.regex' => 'La identidad debe tener 13 dígitos y el RTN 14, sin letras.',
            'fecha_nacimiento.required' => 'Ingresa la fecha de nacimiento del cliente con identidad.',
            'fecha_nacimiento.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
        ]);

        try {
            $message = $this->editingId === null
                ? $this->api()->create($validated)
                : $this->api()->update($this->editingId, $validated);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');

            return;
        } catch (ApiOperationException $exception) {
            foreach ($exception->errors as $field => $messages) {
                foreach ((array) $messages as $error) {
                    $this->addError($field, (string) $error);
                }
            }
            if ($exception->errors === []) {
                $this->alert($exception->getMessage());
            }

            return;
        }

        $isCreating = $this->editingId === null;
        $this->closeForm();
        $this->dispatch('sweetalert:toast', icon: 'success', title: $message);
        $this->loadPage($isCreating ? 1 : $this->page);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('clientepos.delete');
        $client = collect($this->clients)->first(fn (array $client): bool => (int) ($client['id'] ?? 0) === $id);
        $this->deletingId = $id;
        $this->deletingName = is_array($client) ? (string) ($client['nombre'] ?? '') : "#{$id}";
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
    }

    public function delete(): void
    {
        $this->authorizeAbility('clientepos.delete');
        if ($this->deletingId === null) {
            return;
        }

        try {
            $message = $this->api()->delete($this->deletingId);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');

            return;
        } catch (ApiOperationException $exception) {
            $this->alert($exception->getMessage());

            return;
        }

        $this->cancelDelete();
        $this->dispatch('sweetalert:toast', icon: 'success', title: $message);
        $this->loadPage(count($this->clients) === 1 && $this->page > 1 ? $this->page - 1 : $this->page);
    }

    public function goToPage(int $page): void
    {
        if ($page >= 1 && $page <= $this->lastPage && $page !== $this->page) {
            $this->loadPage($page);
        }
    }

    public function render(): View
    {
        $permissions = app(CurrentUserPermissions::class);

        return view('livewire.pos.clientes', ['access' => [
            'create' => $permissions->allows('clientepos.create'),
            'edit' => $permissions->allowsAll(['clientepos.search', 'clientepos.update']),
            'delete' => $permissions->allows('clientepos.delete'),
        ]]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('clientepos.list');
        $this->loadError = null;

        try {
            $this->applyPage($this->api()->list($page, $this->search));
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->clients = $page->items;
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->nombre = $this->rtn = $this->telefono = $this->direccion = $this->tipo_documento = $this->fecha_nacimiento = '';
        $this->legacyDocument = false;
        $this->originalDocument = '';
    }

    private function alert(string $message): void
    {
        $this->dispatch('sweetalert:alert', icon: 'error', title: 'No fue posible completar la solicitud', text: $message);
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }

    private function api(): ClienteposApiService
    {
        return app(ClienteposApiService::class);
    }
}
