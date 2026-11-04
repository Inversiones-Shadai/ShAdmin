<?php

namespace App\Livewire\Shared;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\LotApiService;
use App\Services\Api\ProductApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class AdvancedProductSearch extends Component
{
    use RemembersSelectorChoice;

    public bool $showModal = false;

    public string $context = 'default';

    public string $searchTerm = '';

    public string $laboratory = '';

    /** @var array<int, array{id: int, label: string}> */
    public array $laboratories = [];

    /** @var array<int, array<string, mixed>> */
    public array $products = [];

    /** @var array<int, int> */
    public array $excludedIds = [];

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public ?string $loadError = null;

    private bool $filtersLoaded = false;

    /** @param array<int, mixed> $excludedIds */
    #[On('advanced-product-search:open')]
    public function open(string $context = 'default', array $excludedIds = []): void
    {
        $this->context = mb_substr(trim($context), 0, 100) ?: 'default';
        if ($this->usesLots()) {
            $this->authorizeAbility('lots.list');
        } else {
            $this->authorizeAbility('products.search');
            $this->authorizeAbility('products.options');
        }
        $this->excludedIds = collect($excludedIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $this->searchTerm = '';
        $this->laboratory = '';
        if ($this->usesLots()) {
            $this->laboratories = [];
        }
        $this->loadError = null;

        if (! $this->usesLots() && ! $this->loadFilters()) {
            return;
        }

        $this->showModal = true;
        $this->loadProducts();
    }

    public function close(): void
    {
        $this->showModal = false;
        $this->loadError = null;
    }

    #[On('inventory-lots-updated')]
    public function refreshLotProducts(): void
    {
        if ($this->usesLots()) {
            if ($this->showModal) {
                $this->loadProducts($this->page);
            } else {
                $this->products = [];
                $this->total = 0;
                $this->page = 1;
                $this->lastPage = 1;
            }
        }
    }

    public function updatedSearchTerm(): void
    {
        $this->loadProducts();
    }

    public function updatedLaboratory(): void
    {
        $this->loadProducts();
    }

    public function clearFilters(): void
    {
        $this->searchTerm = '';
        $this->laboratory = '';
        $this->loadProducts();
    }

    public function previousPage(): void
    {
        $this->loadProducts(max(1, $this->page - 1));
    }

    public function nextPage(): void
    {
        $this->loadProducts(min($this->lastPage, $this->page + 1));
    }

    public function selectProduct(int $id): void
    {
        if (in_array($id, $this->excludedIds, true)) {
            return;
        }

        $product = collect($this->products)->first(
            static fn (array $product): bool => (int) ($product['id'] ?? 0) === $id,
        );

        if (! is_array($product)) {
            return;
        }

        $this->dispatch(
            'advanced-product-search:selected',
            context: $this->context,
            product: $product,
        );
        $this->showModal = false;
    }

    public function render(): View
    {
        return view('livewire.shared.advanced-product-search', [
            'canViewImages' => app(CurrentUserPermissions::class)->allows('products.image'),
            'productsWithLots' => $this->usesLots(),
        ]);
    }

    private function loadProducts(int $page = 1): void
    {
        $this->loadError = null;

        try {
            if ($this->usesLots()) {
                $this->authorizeAbility('lots.list');
                $this->applyPage(app(LotApiService::class)->list($page, $this->searchTerm));
            } else {
                $this->applyPage($this->api()->advancedSearch(
                    $page,
                    $this->searchTerm,
                    $this->identifier($this->laboratory),
                    null,
                ));
            }
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->products = [];
            $this->total = 0;
            $this->page = 1;
            $this->lastPage = 1;
            $this->loadError = $exception->getMessage();
        }
    }

    private function loadFilters(): bool
    {
        $this->filtersLoaded = true;

        return true;
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->products = collect($page->items)
            ->map(fn (array $product): array => $this->normalizeProduct($product))
            ->filter(static fn (array $product): bool => $product['id'] > 0)
            ->values()
            ->all();
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array{id: int, label: string}>
     */
    private function normalizeOptions(array $options): array
    {
        return collect($options)
            ->map(static fn (array $option): array => [
                'id' => (int) ($option['id'] ?? 0),
                'label' => trim((string) ($option['label'] ?? '')),
            ])
            ->filter(static fn (array $option): bool => $option['id'] > 0 && $option['label'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array{id: int, codigo: string, codigobar: string, descripcion: string, laboratorio_id: int, laboratorio: string, presentacion: string, unidad_compra: string, tiene_imagen: bool}
     */
    private function normalizeProduct(array $product): array
    {
        $laboratory = is_array($product['laboratorio'] ?? null) ? $product['laboratorio'] : [];

        return [
            'id' => (int) ($this->usesLots() ? ($product['producto_id'] ?? 0) : ($product['id'] ?? 0)),
            'codigo' => trim((string) ($product['codigo'] ?? '')),
            'codigobar' => trim((string) ($product['codigobar'] ?? '')),
            'descripcion' => trim((string) ($product['descripcion'] ?? '')),
            'laboratorio_id' => (int) ($product['laboratorio_id'] ?? $laboratory['id'] ?? 0),
            'laboratorio' => trim((string) (is_string($product['laboratorio'] ?? null) ? $product['laboratorio'] : ($laboratory['nombre'] ?? $laboratory['label'] ?? 'Sin laboratorio'))),
            'presentacion' => trim((string) data_get($product, 'familia.presentacion.descripcion', $product['presentacion'] ?? '')),
            'unidad_compra' => (string) data_get($product, 'unidad.cantidad_c', ''),
            'tiene_imagen' => (bool) ($product['tiene_imagen'] ?? false),
            'imagen_url' => $product['imagen_url'] ?? null,
        ];
    }

    private function identifier(string $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function usesLots(): bool
    {
        return $this->context === 'transfer-detail';
    }

    private function api(): ProductApiService
    {
        return app(ProductApiService::class);
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }
}
