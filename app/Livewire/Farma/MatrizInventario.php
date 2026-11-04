<?php

namespace App\Livewire\Farma;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\InventoryMatrixApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MatrizInventario extends Component
{
    use RemembersSelectorChoice;

    public const TRANSFER_ABILITIES = ['inventory-matrix.list', 'inventory-matrix.filter', 'transfers.list', 'transfers.send', 'transfers.options', 'transfers.lots'];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $selectedProducts = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $records = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $stocks = [];

    /** @var array<string, string> */
    #[Locked]
    public array $filters = [];

    #[Locked]
    public int $page = 1;

    #[Locked]
    public int $lastPage = 1;

    #[Locked]
    public int $total = 0;

    public string $stockId = '';

    public string $search = '';

    public ?string $loadError = null;

    public function mount(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'inventory-matrix.list');
        $this->goToPage();
    }

    public function updatedStockId(): void
    {
        $this->selectedProducts = [];
        $this->resetValidation('selectedProducts');
        $this->applyFilters();
    }

    public function updatedSearch(): void
    {
        $this->applyFilters();
    }

    public function applyFilters(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'inventory-matrix.filter');
        $this->validate(['stockId' => 'nullable|integer|min:1', 'search' => 'nullable|string|max:200']);
        $this->filters = array_filter(['stock_id' => $this->stockId, 'buscar' => trim($this->search)], fn ($v) => $v !== '');
        $this->goToPage();
    }

    public function clearFilters(): void
    {
        $this->selectedProducts = [];
        $this->filters = [];
        $this->stockId = $this->search = '';
        $this->goToPage();
    }

    public function toggleProduct(int $recordId): void
    {
        $this->authorizeTransfer();
        $row = collect($this->records)->firstWhere('id', $recordId);
        abort_unless(is_array($row) && $this->stockId !== '' && (int) ($row['stock_id'] ?? 0) === (int) $this->stockId, 422);
        $productId = (int) ($row['producto_id'] ?? 0);
        abort_unless($productId > 0, 422);
        if (isset($this->selectedProducts[$productId])) {
            unset($this->selectedProducts[$productId]);
        } elseif (count($this->selectedProducts) >= 200) {
            $this->addError('selectedProducts', 'Selecciona como máximo 200 productos por transferencia.');
        } else {
            $this->selectedProducts[$productId] = [
                'id' => $productId, 'codigo' => $row['codigo'] ?? '', 'descripcion' => $row['descripcion'],
                'punto_reorden' => $row['punto_reorden'], 'stock_id' => (int) $row['stock_id'],
            ];
            $this->resetValidation('selectedProducts');
        }
    }

    public function selectPage(): void
    {
        $this->authorizeTransfer();
        foreach ($this->records as $row) {
            if (! isset($this->selectedProducts[(int) ($row['producto_id'] ?? 0)])) {
                $this->toggleProduct((int) $row['id']);
            }
        }
    }

    public function prepareTransfer(): void
    {
        $this->authorizeTransfer();
        $this->validate(['stockId' => ['required', 'integer', 'min:1'], 'selectedProducts' => ['required', 'array', 'min:1', 'max:200']]);
        foreach ($this->selectedProducts as $product) {
            abort_unless((int) $product['stock_id'] === (int) $this->stockId, 422);
        }
        $token = (string) Str::uuid();
        session()->put('inventory-matrix.transfer-drafts.'.$token, [
            'stock_id' => (int) $this->stockId, 'products' => $this->selectedProducts,
        ]);
        $this->redirectRoute('farma.transferencias', ['matriz' => $token]);
    }

    private function authorizeTransfer(): void
    {
        foreach (self::TRANSFER_ABILITIES as $ability) {
            Gate::authorize(CurrentUserPermissions::GATE, $ability);
        }
    }

    public function goToPage(int $page = 1): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'inventory-matrix.list');
        if ($this->filters !== []) {
            Gate::authorize(CurrentUserPermissions::GATE, 'inventory-matrix.filter');
        }
        $this->records = [];
        $this->total = 0;
        $this->loadError = null;
        try {
            $result = app(InventoryMatrixApiService::class)->list(max(1, min(100000, $page)), $this->filters);
            $this->records = $result->items;
            $this->total = $result->total;
            $this->page = $result->currentPage;
            $this->lastPage = $result->lastPage;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->loadError = $error->getMessage();
        }
    }

    public function render(): View
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'inventory-matrix.list');

        $permissions = app(CurrentUserPermissions::class);

        return view('livewire.farma.matriz-inventario', [
            'canFilter' => $permissions->allows('inventory-matrix.filter'),
            'canTransfer' => $permissions->allowsAll(self::TRANSFER_ABILITIES),
        ]);
    }
}
