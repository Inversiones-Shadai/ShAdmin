<?php

namespace App\Livewire\Farma;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\LotApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Lotes extends Component
{
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

    public bool $showLotsModal = false;

    public function mount(): void
    {
        $this->loadPage();
    }

    public function updatedSearch(): void
    {
        $this->loadPage(1);
    }

    public function openLots(int $productId): void
    {
        $this->authorizeAbility('lots.detail');
        $product = collect($this->products)->first(
            static fn (array $item): bool => (int) ($item['producto_id'] ?? 0) === $productId,
        );

        if (! is_array($product)) {
            $this->alert('No fue posible cargar los lotes', 'El producto ya no se encuentra en la lista actual.');

            return;
        }

        try {
            $this->lots = $this->api()->productLots($productId);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->alert('No fue posible cargar los lotes', $exception->getMessage());

            return;
        }

        $this->selectedProduct = $product;
        $this->showLotsModal = true;
    }

    public function closeLots(): void
    {
        $this->showLotsModal = false;
        $this->lots = [];
        $this->selectedProduct = [];
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
        return view('livewire.farma.lotes', [
            'access' => [
                'detail' => app(CurrentUserPermissions::class)->allows('lots.detail'),
                'image' => app(CurrentUserPermissions::class)->allows('products.image'),
            ],
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('lots.list');
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

    private function api(): LotApiService
    {
        return app(LotApiService::class);
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
