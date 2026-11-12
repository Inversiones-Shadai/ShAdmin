<?php

namespace App\Livewire\Farma;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\DistributionApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Distribucion extends Component
{
    use RemembersSelectorChoice;

    /** @var array<int, array<string, mixed>> */
    public array $products = [];

    /** @var array<int, array<string, mixed>> */
    public array $lots = [];

    /** @var array<string, mixed> */
    public array $selectedProduct = [];

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public string $search = '';

    public ?string $loadError = null;

    public bool $showPricingModal = false;

    public string $selectedLot = '';

    public string $precio = '';

    public bool $isv = false;

    public string $dto1 = '0.00';

    public string $dto2 = '0.00';

    public string $dto3 = '0.00';

    public string $dto4 = '0.00';

    public bool $promocion_activa = false;

    public string $promocion_porcentaje = '';

    public string $promocion_hasta = '';

    public bool $mostrar_encabezado = false;

    public string $encabezado_texto = '';

    public bool $promocion_todos_stocks = true;

    /** @var array<int, int> */
    public array $promocion_stock_ids = [];

    public string $promotionStock = '';

    /** @var array<int, array<string, mixed>> */
    public array $promotionStocks = [];

    /** @var array<int, string> */
    public array $promotionStockLabels = [];

    public bool $showCatalogLotModal = false;

    public string $catalogProduct = '';

    /** @var array<int, array<string, mixed>> */
    public array $catalogProductChoices = [];

    public string $catalogLot = '';

    public string $catalogManufactured = '';

    public string $catalogExpires = '';

    public string $catalogCost = '';

    public bool $catalogIsv = false;

    public ?string $catalogLotError = null;

    public function openCatalogLot(): void
    {
        $this->authorizeAbility('distribution.list');
        $this->authorizeAbility('distribution.create-lot');
        $this->closeCatalogLot();
        $this->showCatalogLotModal = true;
    }

    public function closeCatalogLot(): void
    {
        $this->reset('showCatalogLotModal', 'catalogProduct', 'catalogProductChoices', 'catalogLot', 'catalogManufactured', 'catalogExpires', 'catalogCost', 'catalogIsv', 'catalogLotError');
        $this->resetValidation();
    }

    public function saveCatalogLot(): void
    {
        $this->authorizeAbility('distribution.list');
        $this->authorizeAbility('distribution.create-lot');
        $this->catalogLotError = null;
        $this->catalogLot = trim($this->catalogLot);
        $validated = $this->validate([
            'catalogProduct' => ['required', 'integer', 'min:1'],
            'catalogLot' => ['required', 'string', 'max:100'],
            'catalogManufactured' => ['nullable', 'date_format:Y-m-d', ...($this->catalogExpires !== '' ? ['before_or_equal:catalogExpires'] : [])],
            'catalogExpires' => ['nullable', 'date_format:Y-m-d'],
            'catalogCost' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999', 'decimal:0,4'],
            'catalogIsv' => ['boolean'],
        ], [
            'catalogProduct.required' => 'Selecciona un producto.',
            'catalogLot.required' => 'Ingresa el número de lote.',
            'catalogLot.max' => 'El lote no puede exceder 100 caracteres.',
            'catalogManufactured.before_or_equal' => 'La elaboración no puede ser posterior a la expiración.',
            'catalogCost.min' => 'El costo no puede ser negativo.',
            'catalogCost.decimal' => 'Usa como máximo cuatro decimales para el costo.',
        ]);

        try {
            $lot = $this->api()->createCatalogLot([
                'producto' => (int) $validated['catalogProduct'], 'lote' => $validated['catalogLot'],
                'fecha_elab' => $validated['catalogManufactured'] ?: null, 'fecha_exp' => $validated['catalogExpires'] ?: null,
                'costo' => $validated['catalogCost'] !== '' ? $validated['catalogCost'] : null, 'isv' => $validated['catalogIsv'],
            ]);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->catalogLotError = $exception->getMessage();
            $fields = ['producto' => 'catalogProduct', 'lote' => 'catalogLot', 'fecha_elab' => 'catalogManufactured', 'fecha_exp' => 'catalogExpires', 'costo' => 'catalogCost', 'isv' => 'catalogIsv'];
            foreach ($exception->errors as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError($fields[$field] ?? $field, $message);
                }
            }

            return;
        }

        $productId = (int) $validated['catalogProduct'];
        $product = collect($this->catalogProductChoices)->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === $productId);
        $this->closeCatalogLot();
        $this->search = '';
        $this->loadPage(1);
        $this->dispatch('sweetalert:toast', icon: 'success', title: 'Lote registrado. Las existencias de los stocks no se modificaron.');

        if (app(CurrentUserPermissions::class)->allows('distribution.detail')) {
            $this->selectedProduct = collect($this->products)->firstWhere('producto_id', $productId)
                ?? ['producto_id' => $productId, 'codigo' => $product['codigo'] ?? '', 'descripcion' => $product['descripcion'] ?? 'Producto', 'laboratorio' => ''];
            $this->lots = [$lot + ['label' => $lot['lote'] ?? 'Lote']];
            $this->selectedLot = (string) $lot['id'];
            $this->fillPricingFromSelectedLot();
            $this->showPricingModal = true;
        }
    }

    public function addPromotionStock(): void
    {
        $this->authorizeAbility('distribution.save');
        $stock = collect($this->promotionStocks)->first(fn (array $item): bool => (string) $item['id'] === $this->promotionStock);
        if (! is_array($stock)) {
            $this->addError('promotionStock', 'Selecciona un stock.');

            return;
        }
        $id = (int) $stock['id'];
        $this->promocion_stock_ids = array_values(array_unique([...$this->promocion_stock_ids, $id]));
        $this->promotionStockLabels[$id] = (string) ($stock['descripcion'] ?? $stock['label'] ?? 'Stock '.$id);
        $this->promotionStock = '';
        $this->promotionStocks = [];
        $this->resetValidation(['promotionStock', 'promocion_stock_ids']);
    }

    public function removePromotionStock(int $id): void
    {
        $this->authorizeAbility('distribution.save');
        $this->promocion_stock_ids = array_values(array_filter($this->promocion_stock_ids, fn ($selected): bool => (int) $selected !== $id));
        unset($this->promotionStockLabels[$id]);
    }

    public function mount(): void
    {
        $this->loadPage();
    }

    public function updatedSearch(): void
    {
        $this->loadPage(1);
    }

    public function openPricing(int $productId): void
    {
        $this->authorizeAbility('distribution.detail');
        $product = collect($this->products)->first(
            static fn (array $item): bool => (int) ($item['producto_id'] ?? 0) === $productId,
        );

        if (! is_array($product)) {
            $this->alert('No fue posible cargar el producto', 'El producto ya no se encuentra en la lista actual.');

            return;
        }

        $this->lots = [];
        $this->selectedProduct = $product;
        $this->showPricingModal = true;
        $this->selectedLot = '';
        $this->fillPricingFromSelectedLot();
    }

    public function updatedSelectedLot(): void
    {
        $this->resetValidation();
        $this->fillPricingFromSelectedLot();
    }

    public function savePricing(): void
    {
        $this->authorizeAbility('distribution.save');
        $validated = $this->validate([
            'selectedLot' => ['required', 'integer', 'min:1'],
            'precio' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'dto1' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'dto2' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'dto3' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'dto4' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'promocion_activa' => ['boolean'],
            'promocion_porcentaje' => ['required_if:promocion_activa,true', 'nullable', 'numeric', 'gt:0', 'max:99.99'],
            'promocion_hasta' => ['required_if:promocion_activa,true', 'nullable', 'date_format:Y-m-d'],
            'mostrar_encabezado' => ['boolean'],
            'encabezado_texto' => ['nullable', 'string', 'max:160'],
            'promocion_todos_stocks' => ['boolean'],
            'promocion_stock_ids' => ['array', 'max:100', $this->promocion_todos_stocks ? 'min:0' : 'min:1'],
            'promocion_stock_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ], [
            'selectedLot.required' => 'Selecciona un lote.',
            'precio.gt' => 'El precio debe ser mayor que cero.',
            'dto*.max' => 'El descuento máximo es 99.99%.',
        ]);

        $lotId = (int) $validated['selectedLot'];
        $payload = [
            'precio' => (float) $validated['precio'],
            'dto1' => (float) $validated['dto1'],
            'dto2' => (float) $validated['dto2'],
            'dto3' => (float) $validated['dto3'],
            'dto4' => (float) $validated['dto4'],
            'promocion_activa' => $validated['promocion_activa'],
            'promocion_porcentaje' => $validated['promocion_porcentaje'] !== '' && $validated['promocion_porcentaje'] !== null ? (float) $validated['promocion_porcentaje'] : null,
            'promocion_hasta' => $validated['promocion_hasta'] ?: null,
            'mostrar_encabezado' => $validated['mostrar_encabezado'],
            'encabezado_texto' => trim($validated['encabezado_texto'] ?? '') ?: null,
            'promocion_todos_stocks' => $validated['promocion_todos_stocks'],
            'promocion_stock_ids' => $validated['promocion_todos_stocks'] ? null : array_values(array_map('intval', $validated['promocion_stock_ids'])),
        ];

        try {
            $saved = $this->api()->saveLot($lotId, $payload);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible guardar la distribución', $exception->getMessage());

            return;
        }

        foreach ($this->lots as $index => $lot) {
            if ((int) ($lot['id'] ?? 0) !== $lotId) {
                continue;
            }

            $wasConfigured = (bool) ($lot['configurado'] ?? false);
            $this->lots[$index]['configurado'] = true;
            $this->lots[$index]['distribucion'] = $saved;

            if (! $wasConfigured) {
                $this->increaseConfiguredCount();
            }
            break;
        }

        $this->dispatch('sweetalert:toast', icon: 'success', title: 'Precios y descuentos guardados.');
    }

    public function closePricing(): void
    {
        $this->showPricingModal = false;
        $this->lots = [];
        $this->selectedProduct = [];
        $this->selectedLot = '';
        $this->resetPricing();
        $this->resetValidation();
    }

    public function previousPage(): void
    {
        $this->loadPage(max(1, $this->page - 1));
    }

    public function nextPage(): void
    {
        $this->loadPage(min($this->lastPage, $this->page + 1));
    }

    public function render(): View
    {
        return view('livewire.farma.distribucion', [
            'access' => [
                'detail' => app(CurrentUserPermissions::class)->allows('distribution.detail'),
                'save' => app(CurrentUserPermissions::class)->allows('distribution.save'),
                'image' => app(CurrentUserPermissions::class)->allows('products.image'),
                'createLot' => app(CurrentUserPermissions::class)->allows('distribution.create-lot'),
            ],
            'activeLot' => $this->selectedLotRecord(),
            'unitCostWithTax' => $this->unitCostWithTax(),
            'discountReferences' => $this->discountReferences(),
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('distribution.list');
        $this->loadError = null;

        try {
            $this->applyPage($this->api()->list($page, $this->search));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->products = $page->items;
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    private function fillPricingFromSelectedLot(): void
    {
        $lot = $this->selectedLotRecord();
        $distribution = is_array($lot) && is_array($lot['distribucion'] ?? null) ? $lot['distribucion'] : null;
        $lotTax = is_array($lot) && array_key_exists('isv', $lot) && $lot['isv'] !== null
            ? (bool) $lot['isv']
            : null;

        if ($distribution === null) {
            $this->resetPricing();
            $this->isv = $lotTax ?? false;

            return;
        }

        $this->precio = number_format((float) ($distribution['precio'] ?? 0), 2, '.', '');
        $this->isv = $lotTax ?? (bool) ($distribution['isv'] ?? false);
        $this->dto1 = number_format((float) ($distribution['dto1'] ?? 0), 2, '.', '');
        $this->dto2 = number_format((float) ($distribution['dto2'] ?? 0), 2, '.', '');
        $this->dto3 = number_format((float) ($distribution['dto3'] ?? 0), 2, '.', '');
        $this->dto4 = number_format((float) ($distribution['dto4'] ?? 0), 2, '.', '');
        $this->promocion_activa = (bool) ($distribution['promocion_activa'] ?? false);
        $this->promocion_porcentaje = isset($distribution['promocion_porcentaje']) ? number_format((float) $distribution['promocion_porcentaje'], 2, '.', '') : '';
        $this->promocion_hasta = (string) ($distribution['promocion_hasta'] ?? '');
        $this->mostrar_encabezado = (bool) ($distribution['mostrar_encabezado'] ?? false);
        $this->encabezado_texto = (string) ($distribution['encabezado_texto'] ?? '');
        $this->promocion_todos_stocks = (bool) ($distribution['promocion_todos_stocks'] ?? true);
        $this->promocion_stock_ids = array_values(array_map('intval', $distribution['promocion_stock_ids'] ?? []));
        $stocks = $distribution['promocion_stocks'] ?? [];
        $this->promotionStockLabels = is_array($stocks) ? collect($stocks)->pluck('descripcion', 'id')->all() : [];
        $this->promotionStock = '';
        $this->promotionStocks = [];
    }

    private function resetPricing(): void
    {
        $this->precio = '';
        $this->isv = false;
        $this->dto1 = $this->dto2 = $this->dto3 = $this->dto4 = '0.00';
        $this->promocion_activa = $this->mostrar_encabezado = false;
        $this->promocion_porcentaje = $this->promocion_hasta = $this->encabezado_texto = '';
        $this->promocion_todos_stocks = true;
        $this->promocion_stock_ids = $this->promotionStockLabels = $this->promotionStocks = [];
        $this->promotionStock = '';
    }

    private function selectedLotTaxRate(): ?float
    {
        $lot = $this->selectedLotRecord();
        $taxApplies = is_array($lot) && ($lot['isv'] ?? null) !== null
            ? (bool) $lot['isv']
            : $this->isv;

        return $taxApplies ? max(0, min(1, (float) config('farma.purchase_isv_rate', 0.15))) : null;
    }

    private function unitCostWithTax(): ?float
    {
        $lot = $this->selectedLotRecord();

        if (! is_array($lot) || ! is_numeric($lot['costo'] ?? null)) {
            return null;
        }

        return round(max(0, (float) $lot['costo']) * (1 + ($this->selectedLotTaxRate() ?? 0)), 2);
    }

    /** @return array<string, array{subtotal: float, descuento: float, isv: ?float, total: float, ganancia: ?float}> */
    private function discountReferences(): array
    {
        $price = is_numeric($this->precio) ? round(max(0, (float) $this->precio), 2) : 0.0;
        $taxRate = $this->selectedLotTaxRate();
        $totalCost = $this->unitCostWithTax();
        $references = [];

        foreach (['dto1', 'dto2', 'dto3', 'dto4'] as $discountField) {
            $discount = is_numeric($this->{$discountField}) ? (float) $this->{$discountField} : 0;
            $discount = min(99.99, max(0, $discount));
            $discountAmount = round($price * ($discount / 100), 2);
            $discountedPrice = round($price - $discountAmount, 2);
            $tax = $taxRate !== null ? round($discountedPrice * $taxRate, 2) : null;
            $total = round($discountedPrice + ($tax ?? 0), 2);

            $references[$discountField] = [
                'subtotal' => $price,
                'descuento' => $discountAmount,
                'isv' => $tax,
                'total' => $total,
                'ganancia' => $totalCost > 0 ? round((($total - $totalCost) / $totalCost) * 100, 2) : null,
            ];
        }

        return $references;
    }

    /** @return array<string, mixed>|null */
    private function selectedLotRecord(): ?array
    {
        $lot = collect($this->lots)->first(
            fn (array $item): bool => (string) ($item['id'] ?? '') === $this->selectedLot,
        );

        return is_array($lot) ? $lot : null;
    }

    private function increaseConfiguredCount(): void
    {
        $productId = (int) ($this->selectedProduct['producto_id'] ?? 0);
        foreach ($this->products as $index => $product) {
            if ((int) ($product['producto_id'] ?? 0) === $productId) {
                $this->products[$index]['lotes_configurados'] = (int) ($product['lotes_configurados'] ?? 0) + 1;
                $this->selectedProduct = $this->products[$index];

                return;
            }
        }
    }

    private function api(): DistributionApiService
    {
        return app(DistributionApiService::class);
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }

    private function redirectToLogin(): void
    {
        $this->redirectRoute('login');
    }

    private function alert(string $title, string $message): void
    {
        $this->dispatch('sweetalert:alert', icon: 'error', title: $title, text: $message);
    }
}
