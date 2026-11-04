<?php

namespace App\Livewire\Farma;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\TransferApiService;
use App\Services\Auth\CurrentUserPermissions;
use App\Services\Printing\TransferPrinter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

class Transferencias extends Component
{
    use RemembersSelectorChoice;

    /** @var array<int, array<string, mixed>> */
    public array $transfers = [];

    /** @var array<string, mixed> */
    public array $selectedTransfer = [];

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public string $search = '';

    public string $direction = 'todas';

    public string $typeFilter = '';

    public string $stockFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $dateSummary = '';

    public bool $showFiltersModal = false;

    #[Locked]
    public array $filterSnapshot = [];

    public function openFilters(): void
    {
        $this->authorizeAbility('transfers.list');
        $this->filterSnapshot = collect(['search', 'direction', 'typeFilter', 'stockFilter', 'dateFrom', 'dateTo', 'transferStocks', 'transferTypes'])
            ->mapWithKeys(fn (string $field): array => [$field => $this->{$field}])->all();
        $this->resetValidation(['dateFrom', 'dateTo', 'search']);
        $this->showFiltersModal = true;
    }

    public function cancelFilters(): void
    {
        foreach ($this->filterSnapshot as $field => $value) {
            $this->{$field} = $value;
        }
        $this->filterSnapshot = [];
        $this->showFiltersModal = false;
        $this->resetValidation(['dateFrom', 'dateTo', 'search']);
    }

    public function resetFiltersToToday(): void
    {
        $this->authorizeAbility('transfers.list');
        $this->search = $this->typeFilter = $this->stockFilter = '';
        $this->direction = 'todas';
        $this->dateFrom = $this->dateTo = now('America/Tegucigalpa')->toDateString();
        $this->resetValidation(['dateFrom', 'dateTo', 'search']);
    }

    public function applyFilters(): void
    {
        $this->validate(['search' => ['string', 'max:100']]);
        $this->loadPage(1);
        if ($this->loadError !== null) {
            return;
        }
        $this->filterSnapshot = [];
        $this->showFiltersModal = false;
        $this->showDetailModal = false;
        $this->selectedTransfer = [];
    }

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $transferStocks = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $transferTypes = [];

    public ?string $loadError = null;

    public bool $showDetailModal = false;

    public bool $showReceiveModal = false;

    public ?int $receivingId = null;

    public bool $showSendModal = false;

    public bool $showLotSelectionModal = false;

    #[Locked]
    public bool $isPrepared = false;

    /** @var array<string, mixed> */
    public array $options = [];

    /** @var array<int, array<string, mixed>> */
    public array $lots = [];

    /** @var array<int, array{lote: int, cantidad: int}> */
    public array $details = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $selectedLots = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $availableLotsForProduct = [];

    /** @var array<int, int> */
    public array $lotQuantities = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $conversionOptions = [];

    public string $conversionChild = '';

    public int $conversionQuantity = 1;

    /** @var array<string, mixed> */
    #[Locked]
    public array $pendingConversion = [];

    /** @var array<string, mixed>|null */
    public ?array $pendingProduct = null;

    public string $tipo = '';

    public string $stockPara = '';

    public string $lotSearch = '';

    public int $lotSelectionPage = 1;

    public int $lotSelectionLastPage = 1;

    public int $lotSelectionTotal = 0;

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $matrixProducts = [];

    #[Locked]
    public ?int $matrixStockId = null;

    public function mount(): void
    {
        $this->dateFrom = $this->dateTo = now('America/Tegucigalpa')->toDateString();
        $this->loadPage();
        $token = request()->query('matriz');
        if (is_string($token) && Str::isUuid($token)) {
            $this->openMatrixDraft($token);
        }
    }

    private function openMatrixDraft(string $token): void
    {
        foreach (MatrizInventario::TRANSFER_ABILITIES as $ability) {
            $this->authorizeAbility($ability);
        }
        $draft = session()->get('inventory-matrix.transfer-drafts.'.$token);
        if (! is_array($draft) || empty($draft['products'])) {
            $this->alert('Preparación no disponible', 'Selecciona nuevamente los productos en la matriz de inventario.');

            return;
        }
        $this->openSend();
        if (! $this->showSendModal) {
            return;
        }
        $stockId = (int) $draft['stock_id'];
        $choice = $this->rememberSelectorChoice('transfer-stocks', (string) $stockId);
        $destinations = $this->options['stocks_destino'] ?? [];
        $destinationAvailable = false;
        if (is_array($destinations)) {
            foreach ($destinations as $stock) {
                if (is_array($stock) && (int) ($stock['id'] ?? 0) === $stockId) {
                    $destinationAvailable = true;
                    break;
                }
            }
        }
        if (! $destinationAvailable) {
            $this->closeSend();
            $this->alert('Destino no disponible', 'El stock de la matriz debe estar activo y ser diferente al stock de origen de esta sesión.');

            return;
        }
        $this->matrixProducts = $draft['products'];
        $this->matrixStockId = $stockId;
        $this->stockPara = (string) $stockId;
        session()->forget('inventory-matrix.transfer-drafts.'.$token);
    }

    public function openMatrixProduct(int $productId): void
    {
        $this->authorizeAbility('transfers.send');
        abort_unless(isset($this->matrixProducts[$productId]), 422);
        $this->handleProductSelected('transfer-detail', $this->matrixProducts[$productId]);
    }

    private function validMatrixDestination(): bool
    {
        if ($this->matrixStockId === null) {
            return true;
        }
        foreach (MatrizInventario::TRANSFER_ABILITIES as $ability) {
            $this->authorizeAbility($ability);
        }
        if ((string) $this->matrixStockId !== $this->stockPara) {
            $this->addError('stockPara', 'El destino debe ser el stock seleccionado en la matriz.');

            return false;
        }

        return true;
    }

    public function updatedSearch(): void
    {
        if (! $this->showFiltersModal) {
            $this->loadPage(1);
        }
    }

    public function updatedDirection(): void
    {
        if (! in_array($this->direction, ['todas', 'enviadas', 'recibidas'], true)) {
            $this->direction = 'todas';
        }
        if (! $this->showFiltersModal) {
            $this->loadPage(1);
        }
    }

    public function updatedTypeFilter(): void
    {
        $ids = array_map(static fn (array $type): string => (string) ($type['id'] ?? ''), $this->transferTypes);
        if ($this->typeFilter !== '' && ! in_array($this->typeFilter, $ids, true)) {
            $this->typeFilter = '';
        }
        if (! $this->showFiltersModal) {
            $this->loadPage(1);
        }
    }

    public function updatedStockFilter(): void
    {
        $this->authorizeAbility('transfers.filter-stocks');
        $ids = array_map(static fn (array $stock): string => (string) ($stock['id'] ?? ''), $this->transferStocks);

        if ($this->stockFilter !== '' && ! in_array($this->stockFilter, $ids, true)) {
            $this->stockFilter = '';
        }

        if (! $this->showFiltersModal) {
            $this->showDetailModal = false;
            $this->selectedTransfer = [];
            $this->loadPage(1);
        }
    }

    public function openDetail(int $id): void
    {
        $this->authorizeAbility('transfers.search');

        try {
            $this->selectedTransfer = $this->api()->find($id, $this->selectedStockId());
            $this->showDetailModal = true;
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible cargar la transferencia', $exception->getMessage());
        }
    }

    public function printTransfer(int $id, string $format): void
    {
        $this->authorizeAbility('transfers.search');
        if (! in_array($format, ['thermal', 'a4'], true)) {
            $this->alert('Formato inválido', 'Selecciona impresión térmica o papel A4.');

            return;
        }
        try {
            $transfer = $this->api()->find($id, $this->selectedStockId());
            $html = app(TransferPrinter::class)->render($transfer, $format);
            $this->dispatch('transfer:print', html: $html);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException|InvalidArgumentException|RuntimeException $exception) {
            $this->alert('No fue posible imprimir la transferencia', $exception->getMessage());
        }
    }

    public function openSend(): void
    {
        $this->authorizeAbility('transfers.send');
        $this->authorizeAbility('transfers.options');
        $this->authorizeAbility('transfers.lots');
        $this->resetSendForm();

        try {
            $this->options = $this->api()->options();
            $this->showSendModal = true;
            $pending = session('transfers.pending-conversion');
            if (is_array($pending) && $pending !== []) {
                $this->pendingConversion = $pending;
                $this->conversionChild = (string) $pending['producto_hijo'];
                $this->conversionQuantity = (int) $pending['cantidad'];
                $this->showLotSelectionModal = true;
            }
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible preparar la transferencia', $exception->getMessage());
        }
    }

    public function openEdit(int $id): void
    {
        // Existing transfers have already been sent and are read-only.
        $this->openDetail($id);
    }

    public function createHeader(): void
    {
        $this->authorizeAbility('transfers.send');
        if (! $this->showSendModal || $this->isPrepared) {
            return;
        }
        if (! $this->validMatrixDestination()) {
            return;
        }

        $this->validate([
            'tipo' => ['required', 'integer', 'min:1'],
            'stockPara' => ['required', 'integer', 'min:1'],
        ], [
            'tipo.required' => 'Selecciona el tipo de transferencia.',
            'stockPara.required' => 'Selecciona el stock de destino.',
        ]);

        $this->isPrepared = true;
    }

    public function openProductSearch(): void
    {
        $this->authorizeAbility('transfers.send');
        $this->authorizeAbility('lots.list');
        if (! $this->showSendModal || ! $this->isPrepared) {
            return;
        }
        if ($this->matrixStockId !== null) {
            return;
        }
        $this->dispatch('advanced-product-search:open', context: 'transfer-detail');
    }

    /** @param array<string, mixed> $product */
    #[On('advanced-product-search:selected')]
    public function handleProductSelected(string $context, array $product, int $page = 1): void
    {
        if ($context !== 'transfer-detail' || ! $this->showSendModal || ! $this->isPrepared || $this->pendingConversion !== []) {
            return;
        }
        $this->conversionOptions = [];

        $this->authorizeAbility('transfers.lots');
        $this->authorizeAbility('transfers.send');

        $productId = (int) ($product['id'] ?? 0);
        if ($productId < 1) {
            return;
        }
        if ($this->matrixStockId !== null) {
            if (! $this->validMatrixDestination()) {
                return;
            }
            abort_unless(isset($this->matrixProducts[$productId]), 422);
            $product = $this->matrixProducts[$productId];
        }

        try {
            $lotsPage = $this->api()->lots(max(1, $page), '', $productId);
            $availableLots = collect($lotsPage->items)
                ->filter(static fn (array $lot): bool => (int) ($lot['cantidad'] ?? 0) > 0)
                ->filter(fn (array $lot): bool => $this->matrixStockId === null || (int) ($lot['producto_id'] ?? 0) === $productId)
                ->filter(fn (array $lot): bool => $this->matrixStockId === null || ! isset($this->selectedLots[(int) $lot['id']]))
                ->map(static fn (array $lot): array => array_merge($lot, [
                    'producto_id' => $productId,
                    'producto' => is_array($lot['producto'] ?? null) ? $lot['producto'] : ($lot['productoDetalle'] ?? $lot['producto_detalle'] ?? $product),
                ]))
                ->values()
                ->all();

            if ($availableLots === [] && $lotsPage->lastPage <= 1) {
                $this->alert('Sin lotes disponibles', 'No hay más lotes con existencia para agregar de este producto en el stock de origen.');

                return;
            }

            if ((int) ($this->pendingProduct['id'] ?? 0) !== $productId) {
                $this->lotQuantities = [];
            }
            $this->pendingProduct = $product;
            $this->availableLotsForProduct = $availableLots;
            $this->lotSelectionPage = $lotsPage->currentPage;
            $this->lotSelectionLastPage = $lotsPage->lastPage;
            $this->lotSelectionTotal = $lotsPage->total;
            $assigned = array_sum(array_map(fn (array $detail): int => (int) ($this->selectedLots[$detail['lote']]['producto_id'] ?? 0) === $productId ? (int) $detail['cantidad'] : 0, $this->details));
            $suggested = $this->matrixStockId !== null ? max(1, (int) ceil((float) $product['punto_reorden']) - $assigned) : 1;
            foreach ($availableLots as $lot) {
                $this->lotQuantities[(int) $lot['id']] ??= min($suggested, (int) $lot['cantidad']);
            }
            $this->showLotSelectionModal = true;
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible consultar lotes del producto', $exception->getMessage());
        }
    }

    public function changeLotSelectionPage(int $delta): void
    {
        if ($this->showLotSelectionModal && $this->pendingProduct && $this->pendingConversion === []) {
            $page = max(1, min($this->lotSelectionLastPage, $this->lotSelectionPage + $delta));
            $this->handleProductSelected('transfer-detail', $this->pendingProduct, $page);
        }
    }

    public function selectLotFromModal(int $lotId): void
    {
        $this->authorizeAbility('transfers.send');
        if (! $this->showSendModal || ! $this->isPrepared) {
            return;
        }
        if (! $this->validMatrixDestination()) {
            return;
        }
        $this->validate([
            'lotQuantities.'.$lotId => ['required', 'integer', 'min:1'],
        ]);
        $lot = collect($this->availableLotsForProduct)->first(fn (array $l): bool => (int) ($l['id'] ?? 0) === $lotId);
        $qty = max(1, (int) ($this->lotQuantities[$lotId] ?? 1));

        if (is_array($lot)) {
            if ($qty > (int) ($lot['cantidad'] ?? 0)) {
                $this->addError('lotQuantities.'.$lotId, 'La cantidad supera la existencia disponible del lote.');

                return;
            }
            $this->addLotToDetails($lotId, $lot, $qty);
        }

        $this->showLotSelectionModal = false;
        $this->pendingProduct = null;
        $this->availableLotsForProduct = [];
        $this->lotQuantities = [];
    }

    public function closeLotSelection(): void
    {
        $this->showLotSelectionModal = false;
        $this->pendingProduct = null;
        $this->availableLotsForProduct = [];
        $this->lotQuantities = [];
        $this->conversionOptions = [];
    }

    public function openConversion(int $lotId): void
    {
        $this->authorizeAbility('transfers.send');
        $this->authorizeAbility('transfers.lots');
        if (! $this->showSendModal || ! $this->isPrepared || $this->pendingConversion !== []) {
            return;
        }
        abort_unless(collect($this->availableLotsForProduct)->contains(fn (array $lot): bool => (int) $lot['id'] === $lotId), 422);
        try {
            $options = $this->api()->conversionOptions($lotId);
            if ($this->matrixStockId !== null) {
                $options['hijos'] = array_values(array_filter($options['hijos'] ?? [], fn (array $child): bool => isset($this->matrixProducts[(int) $child['id']])));
            }
            if (empty($options['hijos']) && empty($options['hijos_total'])) {
                $this->alert('Sin hijos disponibles', 'El padre debe tener un hijo con unidades compatibles'.($this->matrixStockId !== null ? ' seleccionado en la matriz.' : '.'));

                return;
            }
            $this->conversionOptions = $options;
            $this->conversionChild = isset($options['hijos'][0]['id']) ? (string) $options['hijos'][0]['id'] : '';
            $this->conversionQuantity = 1;
            $this->resetValidation();
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible preparar la conversión', $exception->getMessage());
        }
    }

    public function cancelConversion(): void
    {
        if ($this->pendingConversion === []) {
            $this->conversionOptions = [];
            $this->resetValidation();
        }
    }

    public function convertProduct(): void
    {
        $this->authorizeAbility('transfers.send');
        $this->authorizeAbility('transfers.lots');
        if (! $this->showSendModal || ! $this->showLotSelectionModal) {
            return;
        }
        if ($this->pendingConversion === []) {
            if (! $this->isPrepared || $this->conversionOptions === [] || ! $this->validMatrixDestination()) {
                return;
            }
            $this->validate([
                'conversionChild' => ['required', 'integer', Rule::in(array_column($this->conversionOptions['hijos'], 'id'))],
                'conversionQuantity' => ['required', 'integer', 'min:1', 'max:999'],
            ]);
            $parentId = (int) $this->conversionOptions['lote'];
            $reserved = array_sum(array_map(fn (array $detail): int => (int) $detail['lote'] === $parentId ? (int) $detail['cantidad'] : 0, $this->details));
            if ($this->conversionQuantity > (int) $this->conversionOptions['disponible'] - $reserved) {
                $this->addError('conversionQuantity', 'La cantidad supera los padres disponibles, descontando los agregados a esta transferencia.');

                return;
            }
            $this->pendingConversion = [
                'request_id' => (string) Str::uuid(), 'lote_padre' => $parentId,
                'producto_hijo' => (int) $this->conversionChild, 'cantidad' => $this->conversionQuantity,
            ];
            session()->put('transfers.pending-conversion', $this->pendingConversion);
        }
        try {
            $result = $this->api()->convertProduct($this->pendingConversion);
            $parentId = (int) $result['lote_padre'];
            if (isset($this->selectedLots[$parentId])) {
                $this->selectedLots[$parentId]['cantidad'] = (int) $result['disponible_padre'];
            }
            $lot = $result['lote_hijo'];
            // A recovered conversion may belong to a previous draft; its lot can be selected again normally.
            if ($this->isPrepared && ($this->matrixStockId === null || isset($this->matrixProducts[(int) $lot['producto_id']]))) {
                $this->addLotToDetails((int) $lot['id'], $lot, (int) $result['unidades_generadas']);
            }
            session()->forget('transfers.pending-conversion');
            $this->pendingConversion = [];
            $this->closeLotSelection();
            $this->dispatch('inventory-lots-updated');
            $this->dispatch('sweetalert:toast', icon: 'success', title: 'Conversión completada: '.$result['unidades_generadas'].' unidades hijo disponibles.');
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            if ($exception->getCode() === 422) {
                session()->forget('transfers.pending-conversion');
                $this->pendingConversion = [];
            }
            $this->alert('No fue posible confirmar la conversión', $exception->getMessage());
        }
    }

    /** @param array<string, mixed> $lot */
    private function addLotToDetails(int $lotId, array $lot, int $quantity = 1): void
    {
        if (collect($this->details)->contains(fn (array $detail): bool => $detail['lote'] === $lotId)) {
            $this->alert('Lote ya agregado', 'El lote seleccionado ya fue agregado a la transferencia.');

            return;
        }

        $availableStock = (int) ($lot['cantidad'] ?? 0);
        if ($availableStock < 1 || ! is_numeric($lot['costo'] ?? null) || (float) $lot['costo'] < 0) {
            $this->alert('Lote no disponible', 'El lote debe tener existencia y costo registrado.');

            return;
        }
        $finalQuantity = min($quantity, $availableStock);

        $this->details[] = ['lote' => $lotId, 'cantidad' => max(1, $finalQuantity)];
        $this->selectedLots[$lotId] = $lot;

    }

    public function updatedDetails(): void
    {
        $this->resetValidation();
    }

    public function removeLot(int $index): void
    {
        $this->authorizeAbility('transfers.send');
        if (! $this->showSendModal || ! $this->isPrepared) {
            return;
        }

        $lotId = $this->details[$index]['lote'] ?? null;
        unset($this->details[$index]);
        $this->details = array_values($this->details);
        if (is_int($lotId)) {
            unset($this->selectedLots[$lotId]);
        }
        $this->resetValidation();
    }

    public function finishTransfer(): void
    {
        $this->authorizeAbility('transfers.send');
        if ($this->pendingConversion !== []) {
            $this->showLotSelectionModal = true;
            $this->alert('Conversión pendiente', 'Confirma el resultado de la conversión antes de enviar.');

            return;
        }
        if (! $this->showSendModal || ! $this->isPrepared) {
            return;
        }
        if (! $this->validMatrixDestination()) {
            return;
        }

        $validated = $this->validate([
            'tipo' => ['required', 'integer', 'min:1'],
            'stockPara' => ['required', 'integer', 'min:1'],
            'details' => ['required', 'array', 'min:1', 'max:200'],
            'details.*.lote' => ['required', 'integer', 'min:1', 'distinct'],
            'details.*.cantidad' => ['required', 'integer', 'min:1'],
        ], [
            'details.required' => 'Agrega al menos un producto antes de enviar la transferencia.',
            'details.*.cantidad.min' => 'La cantidad debe ser mayor que cero.',
            'details.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
        ]);

        foreach ($this->details as $index => $detail) {
            $lot = $this->selectedLots[$detail['lote']] ?? null;
            if ($lot === null || ! is_numeric($lot['costo'] ?? null) || (float) $lot['costo'] < 0) {
                $this->addError("details.{$index}.cantidad", 'Selecciona un lote con costo registrado.');
            } elseif ($this->matrixStockId !== null && ! isset($this->matrixProducts[(int) ($lot['producto_id'] ?? 0)])) {
                $this->addError("details.{$index}.cantidad", 'El lote debe pertenecer a un producto seleccionado en la matriz.');
            } elseif ($detail['cantidad'] > (int) ($lot['cantidad'] ?? 0)) {
                $this->addError("details.{$index}.cantidad", 'La cantidad supera la existencia disponible del lote.');
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        try {
            $this->api()->sendTransfer([
                'tipo' => (int) $validated['tipo'],
                'stock_para' => (int) $validated['stockPara'],
                'detalles' => array_map(static fn (array $detail): array => [
                    'lote' => (int) $detail['lote'],
                    'cantidad' => (int) $detail['cantidad'],
                ], $this->details),
            ]);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible enviar la transferencia', $exception->getMessage());

            return;
        }

        $this->showSendModal = false;
        $this->resetSendForm();
        $this->dispatch('inventory-lots-updated');
        $this->dispatch('sweetalert:toast', icon: 'success', title: 'Transferencia enviada correctamente. Disponible solo para consulta.');
        $this->loadPage(1);
    }

    public function closeSend(): void
    {
        $this->showSendModal = false;
        $this->resetSendForm();
    }

    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->selectedTransfer = [];
    }

    public function confirmReceive(int $id): void
    {
        $this->authorizeAbility('transfers.receive');
        $this->receivingId = $id;
        $this->showReceiveModal = true;
    }

    public function cancelReceive(): void
    {
        $this->showReceiveModal = false;
        $this->receivingId = null;
    }

    public function receive(): void
    {
        $this->authorizeAbility('transfers.receive');

        if ($this->receivingId === null) {
            return;
        }

        try {
            $message = $this->api()->receive($this->receivingId);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->showReceiveModal = false;
            $this->alert('No fue posible recibir la transferencia', $exception->getMessage());

            return;
        }

        $this->showReceiveModal = false;
        $this->receivingId = null;
        $this->dispatch('sweetalert:toast', icon: 'success', title: $message ?: 'Transferencia recibida correctamente.');
        $this->loadPage($this->page);
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
        $permissions = app(CurrentUserPermissions::class);
        $draftLines = array_map(fn (array $detail): array => $this->pricedLine(
            $detail,
            $this->selectedLots[$detail['lote']] ?? [],
        ), $this->details);
        $savedLines = array_map(function (array $detail): array {
            $lot = $detail['lote_detalle'] ?? $detail['loteDetalle'] ?? $detail['lote'] ?? [];

            return $this->pricedLine($detail, is_array($lot) ? $lot : [], true);
        }, $this->detailLines());
        $matrixLines = array_map(function (array $product): array {
            $assigned = array_sum(array_map(fn (array $detail): int => (int) ($this->selectedLots[$detail['lote']]['producto_id'] ?? 0) === (int) $product['id'] ? (int) $detail['cantidad'] : 0, $this->details));

            return [...$product, 'asignado' => $assigned, 'pendiente' => max(0, (float) $product['punto_reorden'] - $assigned)];
        }, $this->matrixProducts);

        return view('livewire.farma.transferencias', [
            'access' => [
                'search' => $permissions->allows('transfers.search'),
                'receive' => $permissions->allows('transfers.receive'),
                'send' => $permissions->allowsAll(['transfers.send', 'transfers.options', 'transfers.lots']),
                'filterStocks' => $permissions->allows('transfers.filter-stocks'),
            ],
            'draftLines' => $draftLines,
            'draftTotals' => $this->totals($draftLines),
            'detailLines' => $savedLines,
            'savedTotals' => $this->totals($savedLines, $this->selectedTransfer),
            'matrixLines' => $matrixLines,
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('transfers.list');
        $this->validate([
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => array_merge(['nullable', 'date_format:Y-m-d'], $this->dateFrom !== '' ? ['after_or_equal:dateFrom'] : []),
        ], ['dateTo.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.']);
        $this->loadError = null;

        try {
            $this->applyPage($this->api()->list(
                $page,
                $this->search,
                $this->direction,
                $this->typeFilter !== '' ? (int) $this->typeFilter : null,
                $this->selectedStockId(),
                $this->dateFrom !== '' ? $this->dateFrom : null,
                $this->dateTo !== '' ? $this->dateTo : null,
            ));
            $from = $this->dateFrom !== '' ? CarbonImmutable::parse($this->dateFrom)->format('d/m/Y') : null;
            $to = $this->dateTo !== '' ? CarbonImmutable::parse($this->dateTo)->format('d/m/Y') : null;
            $this->dateSummary = $from !== null && $from === $to ? $from
                : ($from === null && $to === null ? 'Todo el historial' : ($from ?? 'Inicio').' — '.($to ?? 'Sin límite'));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->transfers = $page->items;
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
        $types = $page->meta['tipos'] ?? [];
        $this->transferTypes = is_array($types) ? array_values(array_filter($types, is_array(...))) : [];
        $stocks = $page->meta['stocks'] ?? [];
        $this->transferStocks = app(CurrentUserPermissions::class)->allows('transfers.filter-stocks') && is_array($stocks)
            ? array_values(array_filter($stocks, is_array(...)))
            : [];
    }

    private function selectedStockId(): ?int
    {
        if ($this->stockFilter === '') {
            return null;
        }

        $this->authorizeAbility('transfers.filter-stocks');

        return (int) $this->stockFilter;
    }

    private function resetSendForm(): void
    {
        $this->showLotSelectionModal = false;
        $this->resetValidation();
        $this->options = [];
        $this->lots = [];
        $this->details = [];
        $this->selectedLots = [];
        $this->availableLotsForProduct = [];
        $this->lotQuantities = [];
        $this->pendingProduct = null;
        $this->matrixProducts = [];
        $this->conversionOptions = [];
        $this->pendingConversion = [];
        $this->conversionChild = '';
        $this->conversionQuantity = 1;
        $this->matrixStockId = null;
        $this->tipo = '';
        $this->stockPara = '';
        $this->lotSearch = '';

        $this->isPrepared = false;
    }

    /** @return array<int, array<string, mixed>> */
    private function detailLines(): array
    {
        foreach (['detalles', 'detalle', 'items', 'productos'] as $key) {
            $lines = $this->selectedTransfer[$key] ?? null;
            if (is_array($lines) && array_is_list($lines)) {
                return array_values(array_filter($lines, is_array(...)));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  array<string, mixed>  $lot
     * @return array<string, mixed>
     */
    private function pricedLine(array $detail, array $lot, bool $saved = false): array
    {
        $quantity = is_numeric($detail['cantidad'] ?? null) ? max(0, (int) $detail['cantidad']) : 0;
        $cost = is_numeric($lot['costo'] ?? null) ? max(0, (float) $lot['costo']) : null;
        if ($saved && $quantity > 0 && is_numeric($detail['subtotal'] ?? null)) {
            $cost = (float) $detail['subtotal'] / $quantity;
        }
        $taxable = filter_var($lot['isv'] ?? false, FILTER_VALIDATE_BOOL);
        $subtotal = round($quantity * ($cost ?? 0), 2);
        $unitTax = $taxable ? round(($cost ?? 0) * max(0, min(1, (float) config('farma.purchase_isv_rate', 0.15))), 2) : 0.0;
        $tax = round($quantity * $unitTax, 2);
        if ($saved) {
            $subtotal = is_numeric($detail['subtotal'] ?? null) ? (float) $detail['subtotal'] : $subtotal;
            $tax = is_numeric($detail['isv'] ?? null) ? (float) $detail['isv'] : $tax;
            $taxable = $tax > 0;
        }

        return [
            'lot' => $lot,
            'product' => is_array($lot['producto'] ?? null) ? $lot['producto'] : ($lot['productoDetalle'] ?? $lot['producto_detalle'] ?? $detail['producto'] ?? []),
            'quantity' => $quantity,
            'cost' => $cost,
            'taxable' => $taxable,
            'subtotal' => $subtotal,
            'isv' => $tax,
            'total' => $saved && is_numeric($detail['total'] ?? null) ? (float) $detail['total'] : round($subtotal + $tax, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $record
     * @return array{subtotal: float, isv: float, total: float}
     */
    private function totals(array $lines, array $record = []): array
    {
        return [
            'subtotal' => round(is_numeric($record['subtotal'] ?? null) ? (float) $record['subtotal'] : array_sum(array_column($lines, 'subtotal')), 2),
            'isv' => round(is_numeric($record['isvtotal'] ?? null) ? (float) $record['isvtotal'] : array_sum(array_column($lines, 'isv')), 2),
            'total' => round(is_numeric($record['total'] ?? null) ? (float) $record['total'] : array_sum(array_column($lines, 'total')), 2),
        ];
    }

    private function api(): TransferApiService
    {
        return app(TransferApiService::class);
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
