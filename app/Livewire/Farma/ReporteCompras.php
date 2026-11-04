<?php

namespace App\Livewire\Farma;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\PurchaseReportApiService;
use App\Services\Auth\CurrentUserPermissions;
use App\Services\Reports\PurchaseReportExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReporteCompras extends Component
{
    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $purchases = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $totals = [];

    public string $search = '';

    public string $provider = '';

    #[Locked]
    public string $providerName = '';

    public string $type = '';

    #[Locked]
    public int $page = 1;

    #[Locked]
    public int $lastPage = 1;

    #[Locked]
    public int $total = 0;

    public ?string $loadError = null;

    public bool $showProviderPicker = false;

    public string $providerSearch = '';

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $providerOptions = [];

    #[Locked]
    public int $providerPage = 1;

    #[Locked]
    public int $providerLastPage = 1;

    public ?string $providerLoadError = null;

    #[Locked]
    public bool $hasLoaded = false;

    public bool $showPaymentsModal = false;

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $paymentTransactions = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $paymentPurchase = [];

    #[Locked]
    public int $paymentPage = 1;

    #[Locked]
    public int $paymentLastPage = 1;

    public ?string $paymentsError = null;

    public bool $showExportModal = false;

    public string $exportFormat = 'pdf';

    public ?string $exportError = null;

    /** @var array<string, string> */
    #[Locked]
    public array $activeFilters = [];

    public function mount(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchase-reports.list');
    }

    public function loadReport(): void
    {
        $this->validate(['type' => ['nullable', 'in:1,2']]);
        $this->loadPage();
    }

    public function updatedSearch(): void
    {
        $this->loadPage();
    }

    public function updatedType(): void
    {
        $this->validate(['type' => ['nullable', 'in:1,2']]);
        $this->loadPage();
    }

    public function updatedProvider(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchase-reports.providers');
        $this->loadPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'provider', 'providerName', 'type');
        $this->resetValidation();
        $this->reset('purchases', 'totals', 'total', 'page', 'lastPage', 'hasLoaded', 'loadError', 'activeFilters', 'showExportModal', 'exportError');
        $this->closePayments();
    }

    public function previousPage(): void
    {
        if ($this->hasLoaded) {
            $this->loadPage(max(1, $this->page - 1));
        }
    }

    public function nextPage(): void
    {
        if ($this->hasLoaded) {
            $this->loadPage(min($this->lastPage, $this->page + 1));
        }
    }

    public function openProviderPicker(): void
    {
        $this->loadProviders();
        $this->showProviderPicker = true;
    }

    public function updatedProviderSearch(): void
    {
        $this->loadProviders();
    }

    public function previousProviderPage(): void
    {
        $this->loadProviders(max(1, $this->providerPage - 1));
    }

    public function nextProviderPage(): void
    {
        $this->loadProviders(min($this->providerLastPage, $this->providerPage + 1));
    }

    public function selectProvider(int $id): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchase-reports.providers');
        $provider = collect($this->providerOptions)->firstWhere('id', $id);
        if (! is_array($provider)) {
            return;
        }
        $this->provider = (string) $id;
        $this->providerName = (string) $provider['nombre'];
        $this->showProviderPicker = false;
        $this->loadPage();
    }

    public function render(): View
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchase-reports.list');

        return view('livewire.farma.reporte-compras', [
            'canSelectProvider' => app(CurrentUserPermissions::class)->allows('purchase-reports.providers'),
            'canViewPayments' => app(CurrentUserPermissions::class)->allows('purchase-reports.payments'),
            'canExport' => app(CurrentUserPermissions::class)->allows('purchase-reports.export'),
        ]);
    }

    public function openExport(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchase-reports.export');
        if (! $this->hasLoaded || $this->total < 1 || $this->loadError !== null) {
            return;
        }
        $this->reset('exportError');
        $this->resetValidation('exportFormat');
        $this->showExportModal = true;
    }

    public function export(): ?StreamedResponse
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchase-reports.list');
        Gate::authorize(CurrentUserPermissions::GATE, 'purchase-reports.export');
        $this->validate(['exportFormat' => ['required', 'in:pdf,xlsx']]);
        $this->exportError = null;
        if (! $this->hasLoaded || $this->total < 1 || $this->loadError !== null || $this->activeFilters === []) {
            $this->exportError = 'Consulta el reporte antes de exportar.';

            return null;
        }
        try {
            $filters = $this->activeFilters;
            $rows = app(PurchaseReportApiService::class)->exportRows($filters['search'], $filters['provider'], $filters['type']);
            if ($rows === []) {
                $this->exportError = 'No hay compras con los filtros seleccionados.';

                return null;
            }
            $criteria = [
                'Proveedor: '.($filters['provider_name'] ?: 'Todos'),
                'Tipo: '.match ($filters['type']) {
                    '1' => 'Contado', '2' => 'Crédito', default => 'Todos'
                },
            ];
            if ($filters['search'] !== '') {
                $criteria[] = 'Búsqueda: '.$filters['search'];
            }
            $exporter = app(PurchaseReportExporter::class);
            $content = $this->exportFormat === 'pdf' ? $exporter->pdf($rows, $criteria) : $exporter->excel($rows, $criteria);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');

            return null;
        } catch (ApiOperationException $exception) {
            $this->exportError = $exception->getMessage();

            return null;
        } catch (Throwable $exception) {
            report($exception);
            $this->exportError = 'No fue posible generar el archivo. Inténtalo nuevamente.';

            return null;
        }
        $this->showExportModal = false;

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, 'reporte-compras-'.now()->format('Ymd-His').'.'.$this->exportFormat, [
            'Content-Type' => $this->exportFormat === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function viewPayments(int $id): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchase-reports.payments');
        if (! collect($this->purchases)->contains(fn (array $purchase): bool => (int) $purchase['id'] === $id)) {
            return;
        }
        $this->closePayments();
        $this->paymentPurchase = ['id' => $id];
        $this->showPaymentsModal = true;
        $this->loadPayments();
    }

    public function previousPaymentPage(): void
    {
        $this->loadPayments(max(1, $this->paymentPage - 1));
    }

    public function nextPaymentPage(): void
    {
        $this->loadPayments(min($this->paymentLastPage, $this->paymentPage + 1));
    }

    public function closePayments(): void
    {
        $this->reset('showPaymentsModal', 'paymentTransactions', 'paymentPurchase', 'paymentPage', 'paymentLastPage', 'paymentsError');
    }

    private function loadPayments(int $page = 1): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchase-reports.payments');
        if (! isset($this->paymentPurchase['id'])) {
            return;
        }
        $this->paymentsError = null;
        $this->paymentTransactions = [];
        try {
            $result = app(PurchaseReportApiService::class)->payments((int) $this->paymentPurchase['id'], $page);
            $this->paymentTransactions = $result->items;
            $this->paymentPurchase = $result->meta['purchase'];
            $this->paymentPage = $result->currentPage;
            $this->paymentLastPage = $result->lastPage;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->paymentPurchase = ['id' => $this->paymentPurchase['id']];
            $this->paymentsError = $exception->getMessage();
        }
    }

    private function loadPage(int $page = 1): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchase-reports.list');
        $this->closePayments();
        $this->showExportModal = false;
        $this->activeFilters = [];
        $this->hasLoaded = true;
        $this->loadError = null;
        $this->purchases = $this->totals = [];
        $this->total = 0;
        $this->page = $this->lastPage = 1;
        try {
            $result = app(PurchaseReportApiService::class)->list($page, $this->search, $this->provider, $this->type);
            $this->purchases = $result->items;
            $this->totals = $result->meta['totals'];
            $this->total = $result->total;
            $this->page = $result->currentPage;
            $this->lastPage = $result->lastPage;
            $this->activeFilters = ['search' => $this->search, 'provider' => $this->provider, 'type' => $this->type, 'provider_name' => $this->providerName];
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    private function loadProviders(int $page = 1): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchase-reports.providers');
        $this->providerLoadError = null;
        $this->providerOptions = [];
        try {
            $result = app(PurchaseReportApiService::class)->providers($page, $this->providerSearch);
            $this->providerOptions = $result->items;
            $this->providerPage = $result->currentPage;
            $this->providerLastPage = $result->lastPage;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->providerLoadError = $exception->getMessage();
        }
    }
}
