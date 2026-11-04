<?php

namespace App\Livewire\Farma;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\ExpiryApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReglasProveedores extends Component
{
    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $providers = [];

    public string $search = '';

    public int $page = 1;

    public int $lastPage = 1;

    public int $total = 0;

    public ?string $loadError = null;

    public ?string $saveError = null;

    public bool $showForm = false;

    #[Locked]
    public ?int $providerId = null;

    #[Locked]
    public string $providerName = '';

    #[Locked]
    public int $revision = 0;

    public string $months = '';

    public bool $acceptsChildren = false;

    public string $returnType = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->loadProviders();
    }

    public function updatedSearch(): void
    {
        $this->loadProviders();
    }

    public function loadProviders(int $page = 1): void
    {
        $this->authorizeAbility('supplier-rules.list');
        $this->loadError = null;
        $this->providers = [];
        $this->page = max(1, $page);
        $this->lastPage = 1;
        $this->total = 0;
        try {
            $data = $this->api()->providers($this->page, $this->search);
            $this->providers = $data->items;
            $this->page = $data->currentPage;
            $this->lastPage = $data->lastPage;
            $this->total = $data->total;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->loadError = $error->getMessage();
        }
    }

    public function openPolicy(int $provider): void
    {
        $this->authorizeAbility('supplier-rules.list');
        $this->resetValidation();
        $this->saveError = null;
        $this->showForm = false;
        $this->providerId = null;
        try {
            $data = $this->api()->policy($provider);
            $rule = is_array($data['regla'] ?? null) ? $data['regla'] : [];
            $this->providerId = (int) data_get($data, 'proveedor.id');
            $this->providerName = (string) data_get($data, 'proveedor.nombre', '');
            $this->revision = (int) ($rule['revision'] ?? 0);
            $this->months = (string) ($rule['meses_anticipacion'] ?? '');
            $this->acceptsChildren = (bool) ($rule['acepta_hijos'] ?? false);
            $this->returnType = (string) ($rule['tipo_devolucion'] ?? '');
            $this->notes = (string) ($rule['notas'] ?? '');
            $this->showForm = true;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->loadError = $error->getMessage();
        }
    }

    public function save(): void
    {
        $this->authorizeAbility('supplier-rules.save');
        $this->saveError = null;
        if ($this->providerId === null) {
            return;
        }
        $this->validate([
            'months' => ['required', 'integer', 'min:0', 'max:60'],
            'returnType' => ['required', 'in:directa,autorizacion,no_acepta'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $this->api()->savePolicy($this->providerId, [
                'meses_anticipacion' => (int) $this->months, 'acepta_hijos' => $this->acceptsChildren,
                'tipo_devolucion' => $this->returnType, 'notas' => trim($this->notes), 'revision' => $this->revision,
            ]);
            $this->showForm = false;
            $this->providerId = null;
            $this->dispatch('sweetalert:toast', icon: 'success', title: 'Reglas de devolución guardadas.');
            $this->loadProviders($this->page);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->saveError = $error->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.farma.reglas-proveedores', ['canSave' => app(CurrentUserPermissions::class)->allows('supplier-rules.save')]);
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }

    private function api(): ExpiryApiService
    {
        return app(ExpiryApiService::class);
    }
}
