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

class Vencimientos extends Component
{
    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $products = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $lots = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $activeProduct = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $selectedLots = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $results = [];

    public string $search = '';

    public string $months = '';

    public int $page = 1;

    public int $lastPage = 1;

    public int $total = 0;

    public int $lotPage = 1;

    public int $lotLastPage = 1;

    public bool $showLots = false;

    public ?string $loadError = null;

    public ?string $lotError = null;

    public ?string $verifyError = null;

    #[Locked]
    public ?string $verifiedAt = null;

    public function mount(): void
    {
        $this->loadProducts();
    }

    public function updatedSearch(): void
    {
        $this->loadProducts();
    }

    public function updatedMonths(): void
    {
        $this->validate(['months' => ['nullable', 'in:3,6,12,24']]);
        $this->loadProducts();
    }

    public function loadProducts(int $page = 1): void
    {
        $this->authorizeAbility('expiry.list');
        $this->products = [];
        $this->loadError = null;
        $this->page = max(1, $page);
        $this->lastPage = 1;
        $this->total = 0;
        try {
            $result = $this->api()->products($this->page, $this->search, $this->months);
            $this->products = $result->items;
            $this->page = $result->currentPage;
            $this->lastPage = $result->lastPage;
            $this->total = $result->total;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->loadError = $error->getMessage();
        }
    }

    public function openLots(int $product): void
    {
        $this->authorizeAbility('expiry.lots');
        $this->activeProduct = collect($this->products)->firstWhere('producto_id', $product) ?? [];
        if ($this->activeProduct === []) {
            return;
        }
        $this->showLots = true;
        $this->loadLots();
    }

    public function loadLots(int $page = 1): void
    {
        $this->authorizeAbility('expiry.lots');
        $this->lots = [];
        $this->lotError = null;
        $this->lotPage = max(1, $page);
        $this->lotLastPage = 1;
        if ($this->activeProduct === []) {
            return;
        }
        try {
            $result = $this->api()->lots((int) $this->activeProduct['producto_id'], $this->lotPage, $this->months);
            $this->lots = $result->items;
            $this->lotPage = $result->currentPage;
            $this->lotLastPage = $result->lastPage;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->lotError = $error->getMessage();
        }
    }

    public function addLot(int $id): void
    {
        $this->authorizeAbility('expiry.lots');
        $lot = collect($this->lots)->firstWhere('id', $id);
        if (! is_array($lot) || isset($this->selectedLots[$id])) {
            return;
        }
        if (count($this->selectedLots) >= 100) {
            $this->lotError = 'Verifica como máximo 100 lotes a la vez.';

            return;
        }
        $this->selectedLots[$id] = $lot;
        $this->clearResults();
    }

    public function removeLot(int $id): void
    {
        $this->authorizeAbility('expiry.lots');
        unset($this->selectedLots[$id]);
        $this->clearResults();
    }

    public function clearSelection(): void
    {
        $this->authorizeAbility('expiry.lots');
        $this->selectedLots = [];
        $this->clearResults();
    }

    public function verify(): void
    {
        $this->authorizeAbility('expiry.verify');
        $this->clearResults();
        if ($this->selectedLots === []) {
            $this->verifyError = 'Selecciona al menos un lote.';

            return;
        }
        $items = [];
        foreach (array_keys($this->selectedLots) as $id) {
            $items[] = ['id' => (int) $id];
        }
        try {
            $data = $this->api()->verify($items);
            $this->results = array_values(array_filter($data['resultados'], is_array(...)));
            $this->verifiedAt = $data['verificado_el'];
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->verifyError = $error->getMessage();
        }
    }

    public function render(): View
    {
        $permissions = app(CurrentUserPermissions::class);

        return view('livewire.farma.vencimientos', ['access' => [
            'lots' => $permissions->allows('expiry.lots'), 'verify' => $permissions->allows('expiry.verify'),
            'image' => $permissions->allows('products.image'), 'rules' => $permissions->allows('supplier-rules.list'),
        ]]);
    }

    private function clearResults(): void
    {
        $this->results = [];
        $this->verifiedAt = $this->verifyError = null;
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
