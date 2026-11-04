<?php

namespace App\Livewire\Pos;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\PosPurchasesApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Compras extends Component
{
    use RemembersSelectorChoice;

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $purchases = [];

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $document = null;

    /** @var array<string, string> */
    #[Locked]
    public array $filters = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    #[Locked]
    public array $choices = ['stocks' => [], 'suppliers' => [], 'products' => []];

    #[Locked]
    public int $page = 1;

    #[Locked]
    public int $lastPage = 1;

    #[Locked]
    public int $total = 0;

    public bool $showFilters = false;

    public bool $showDocument = false;

    public ?string $loadError = null;

    public string $stockId = '';

    public string $supplierId = '';

    public string $productId = '';

    public string $supplierSearch = '';

    public string $productSearch = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $this->filters === [] ? 'purchasespos.list' : 'purchasespos.filter');
        $this->purchases = [];
        $this->loadError = null;
        try {
            $page = app(PosPurchasesApiService::class)->list($this->page, $this->filters);
            $this->purchases = $page->items;
            $this->total = $page->total;
            $this->page = $page->currentPage;
            $this->lastPage = $page->lastPage;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    public function goToPage(int $page): void
    {
        if ($page < 1 || $page > $this->lastPage) {
            return;
        }
        $this->page = $page;
        $this->refresh();
    }

    public function openDocument(string $id): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchasespos.show');
        abort_unless(Str::isUuid($id), 404);
        $this->document = null;
        $this->showDocument = false;
        try {
            $this->document = app(PosPurchasesApiService::class)->find($id);
            $this->showDocument = true;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    public function closeDocument(): void
    {
        $this->showDocument = false;
        $this->document = null;
    }

    public function openFilters(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchasespos.filter');
        foreach (['stockId' => 'stock_id', 'supplierId' => 'supplier_id', 'productId' => 'product_id', 'dateFrom' => 'date_from', 'dateTo' => 'date_to', 'statusFilter' => 'status'] as $property => $key) {
            $this->{$property} = $this->filters[$key] ?? '';
        }
        $this->resetValidation();
        $this->showFilters = true;
        $this->loadChoices('stocks');
        $this->loadChoices('suppliers');
        $this->loadChoices('products');
    }

    public function updatedSupplierSearch(): void
    {
        $this->loadChoices('suppliers');
    }

    public function updatedProductSearch(): void
    {
        $this->loadChoices('products');
    }

    private function loadChoices(string $kind): void
    {
        $this->choices[$kind] = [];
    }

    public function applyFilters(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchasespos.filter');
        $this->validate([
            'stockId' => ['nullable', 'regex:/\A[1-9][0-9]{0,19}\z/'],
            'supplierId' => ['nullable', 'regex:/\A[1-9][0-9]{0,19}\z/'],
            'productId' => ['nullable', 'regex:/\A[1-9][0-9]{0,19}\z/'],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d', ...($this->dateFrom !== '' ? ['after_or_equal:dateFrom'] : [])],
            'statusFilter' => ['nullable', 'in:accepted,rejected'],
        ]);
        $this->filters = array_filter(['stock_id' => $this->stockId, 'supplier_id' => $this->supplierId,
            'product_id' => $this->productId, 'date_from' => $this->dateFrom, 'date_to' => $this->dateTo, 'status' => $this->statusFilter], fn ($value) => $value !== '');
        $this->showFilters = false;
        $this->page = 1;
        $this->refresh();
    }

    public function clearFilters(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'purchasespos.filter');
        $this->filters = [];
        $this->page = 1;
        $this->stockId = $this->supplierId = $this->productId = $this->dateFrom = $this->dateTo = $this->statusFilter = '';
        $this->refresh();
    }

    public function render(): View
    {
        $permissions = app(CurrentUserPermissions::class);
        if (! $permissions->allows('purchasespos.show')) {
            $this->closeDocument();
        }

        return view('livewire.pos.compras', ['access' => ['filter' => $permissions->allows('purchasespos.filter'), 'show' => $permissions->allows('purchasespos.show')]]);
    }
}
