<?php

namespace App\Livewire\Farma;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\ProductCostReportApiService;
use App\Services\Api\SelectorApiService;
use App\Services\Auth\CurrentUserPermissions;
use App\Services\Reports\ProductCostChart;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReporteCosteo extends Component
{
    public string $productId = '';

    public string $providerId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    #[Locked]
    public string $productName = '';

    #[Locked]
    public string $providerName = '';

    public bool $showFiltersModal = false;

    #[Locked]
    public array $filterSnapshot = [];

    public bool $showPicker = false;

    #[Locked]
    public string $pickerKind = 'products';

    public string $pickerSearch = '';

    #[Locked]
    public array $pickerOptions = [];

    #[Locked]
    public int $pickerPage = 1;

    #[Locked]
    public int $pickerLastPage = 1;

    #[Locked]
    public int $pickerTotal = 0;

    public ?string $pickerError = null;

    public ?string $loadError = null;

    #[Locked]
    public array $report = [];

    #[Locked]
    public int $page = 1;

    public function mount(): void
    {
        $this->authorizeReport();
    }

    public function openFilters(): void
    {
        $this->authorizeReport();
        $this->filterSnapshot = ['productId' => $this->productId, 'productName' => $this->productName,
            'providerId' => $this->providerId, 'providerName' => $this->providerName, 'dateFrom' => $this->dateFrom, 'dateTo' => $this->dateTo];
        $this->resetValidation();
        $this->showFiltersModal = true;
    }

    public function cancelFilters(): void
    {
        $this->authorizeReport();
        foreach ($this->filterSnapshot as $field => $value) {
            $this->{$field} = $value;
        }
        $this->filterSnapshot = [];
        $this->showFiltersModal = false;
        $this->closePicker();
        $this->resetValidation();
    }

    public function openPicker(string $kind): void
    {
        $this->authorizeReport();
        abort_unless(in_array($kind, ['products', 'providers'], true), 422);
        if ($kind === 'products') {
            Gate::authorize(CurrentUserPermissions::GATE, 'cost-reports.products');
        }
        $this->pickerKind = $kind;
        $this->pickerSearch = '';
        $this->showPicker = true;
        $this->loadPickerPage();
    }

    public function updatedPickerSearch(): void
    {
        if ($this->showPicker) {
            $this->loadPickerPage();
        }
    }

    public function pickerGoToPage(int $page): void
    {
        if ($this->showPicker) {
            $this->loadPickerPage(max(1, min($page, $this->pickerLastPage)));
        }
    }

    public function selectPickerOption(int $id): void
    {
        $this->authorizeReport();
        if ($this->pickerKind === 'products') {
            Gate::authorize(CurrentUserPermissions::GATE, 'cost-reports.products');
        }
        $record = collect($this->pickerOptions)->firstWhere('id', $id);
        if (! $this->showPicker || ! is_array($record)) {
            return;
        }
        if ($this->pickerKind === 'products') {
            $this->productId = (string) $id;
            $this->productName = implode(' · ', array_filter([(string) ($record['codigo'] ?? ''), (string) ($record['descripcion'] ?? '')], fn (string $value): bool => $value !== ''));
        } else {
            $this->providerId = (string) $id;
            $this->providerName = (string) ($record['nombre'] ?? 'Proveedor '.$id);
        }
        $this->resetValidation($this->pickerKind === 'products' ? 'productId' : 'providerId');
        $this->closePicker();
    }

    public function clearProvider(): void
    {
        $this->authorizeReport();
        $this->providerId = $this->providerName = '';
    }

    public function closePicker(): void
    {
        $this->showPicker = false;
        $this->pickerOptions = [];
        $this->pickerError = null;
    }

    private function loadPickerPage(int $page = 1): void
    {
        $this->authorizeReport();
        if ($this->pickerKind === 'products') {
            Gate::authorize(CurrentUserPermissions::GATE, 'cost-reports.products');
        }
        $this->pickerOptions = [];
        $this->pickerError = null;
        try {
            $response = app(SelectorApiService::class)->page('cost-report-'.$this->pickerKind,
                ['page' => max(1, $page), 'search' => mb_substr(trim($this->pickerSearch), 0, 100)]);
            foreach ($response['data'] as $option) {
                if (is_array($option['record'] ?? null) && (int) ($option['record']['id'] ?? 0) > 0
                    && (string) $option['record']['id'] === (string) ($option['id'] ?? '')) {
                    $this->pickerOptions[] = $option['record'];
                }
            }
            $this->pickerPage = max(1, (int) data_get($response, 'meta.current_page', $page));
            $this->pickerLastPage = max(1, (int) data_get($response, 'meta.last_page', 1));
            $this->pickerTotal = max(0, (int) data_get($response, 'meta.total', count($this->pickerOptions)));
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->pickerError = $error->getMessage();
        }
    }

    public function loadReport(): void
    {
        $this->authorizeReport();
        $this->validate(['productId' => ['required', 'integer', 'min:1'], 'providerId' => ['nullable', 'integer', 'min:1'],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => array_merge(['nullable', 'date_format:Y-m-d'], $this->dateFrom !== '' ? ['after_or_equal:dateFrom'] : [])],
            ['productId.required' => 'Selecciona un producto.', 'dateTo.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.']);
        $this->report = [];
        $this->page = 1;
        $this->loadError = null;
        try {
            $this->report = app(ProductCostReportApiService::class)->report((int) $this->productId, $this->dateFrom, $this->dateTo, $this->providerId);
            $this->filterSnapshot = [];
            $this->showFiltersModal = false;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->loadError = $error->getMessage();
        }
    }

    public function clearFilters(): void
    {
        $this->authorizeReport();
        $this->productId = $this->providerId = $this->dateFrom = $this->dateTo = '';
        $this->productName = $this->providerName = '';
        if (! $this->showFiltersModal) {
            $this->report = [];
            $this->page = 1;
        }
        $this->loadError = null;
        $this->resetValidation();
    }

    public function goToPage(int $page): void
    {
        $this->authorizeReport();
        $this->page = max(1, min($page, max(1, (int) ceil(count($this->report['compras'] ?? []) / 25))));
    }

    private function authorizeReport(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'cost-reports.list');
    }

    public function render(): View
    {
        $this->authorizeReport();
        $rows = $this->report['compras'] ?? [];

        return view('livewire.farma.reporte-costeo', ['chart' => app(ProductCostChart::class)->build($rows),
            'rows' => array_slice(array_reverse($rows), ($this->page - 1) * 25, 25),
            'lastPage' => max(1, (int) ceil(count($rows) / 25)),
            'canSelectProduct' => app(CurrentUserPermissions::class)->allows('cost-reports.products')]);
    }
}
