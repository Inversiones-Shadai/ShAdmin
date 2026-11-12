<?php

namespace App\Livewire\Farma;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\LostSaleReportApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class VentasPerdidas extends Component
{
    use RemembersSelectorChoice;

    public string $stockId = '';

    public string $productId = '';

    public string $userId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $showFiltersModal = false;

    #[Locked]
    public array $choices = [];

    #[Locked]
    public array $filters = [];

    #[Locked]
    public array $rows = [];

    #[Locked]
    public int $page = 1;

    #[Locked]
    public int $lastPage = 1;

    public function mount(): void
    {
        $this->loadPage(1);
    }

    public function openFilters(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'lost-sales.list');
        $this->stockId = $this->filters['stock_id'] ?? '';
        $this->productId = $this->filters['producto_id'] ?? '';
        $this->userId = $this->filters['usuario_pos_id'] ?? '';
        $this->dateFrom = $this->filters['fecha_desde'] ?? '';
        $this->dateTo = $this->filters['fecha_hasta'] ?? '';
        $this->resetValidation(['stockId', 'productId', 'userId', 'dateFrom', 'dateTo']);
        $this->showFiltersModal = true;
    }

    public function applyFilters(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'lost-sales.list');
        $id = ['nullable', 'regex:/\A[1-9][0-9]{0,19}\z/'];
        $this->validate([
            'stockId' => $id,
            'productId' => $id,
            'userId' => $id,
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d', ...($this->dateFrom !== '' ? ['after_or_equal:dateFrom'] : [])],
        ], [
            'dateFrom.date_format' => 'Selecciona una fecha inicial válida.',
            'dateTo.date_format' => 'Selecciona una fecha final válida.',
            'dateTo.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
        ]);
        $this->filters = array_filter([
            'stock_id' => $this->stockId,
            'producto_id' => $this->productId,
            'usuario_pos_id' => $this->userId,
            'fecha_desde' => $this->dateFrom,
            'fecha_hasta' => $this->dateTo,
        ], fn (string $value): bool => $value !== '');
        $this->showFiltersModal = false;
        $this->loadPage(1);
    }

    public function clearFilters(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'lost-sales.list');
        $this->reset(['stockId', 'productId', 'userId', 'dateFrom', 'dateTo', 'choices']);
        $this->resetValidation(['stockId', 'productId', 'userId', 'dateFrom', 'dateTo']);
    }

    public function loadPage(int $page): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'lost-sales.list');
        $this->resetErrorBag('report');
        try {
            $page = min(max(1, $page), $this->lastPage);
            $api = app(LostSaleReportApiService::class);
            $result = $api->page($page, $this->filters);
            if ($page > $result->lastPage) {
                $result = $api->page($result->lastPage, $this->filters);
            }
            $this->rows = $result->items;
            $this->page = $result->currentPage;
            $this->lastPage = $result->lastPage;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->rows = [];
            $this->addError('report', $error->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.farma.ventas-perdidas');
    }
}
