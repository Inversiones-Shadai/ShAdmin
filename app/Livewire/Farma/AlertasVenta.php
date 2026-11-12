<?php

namespace App\Livewire\Farma;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\SaleAlertApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AlertasVenta extends Component
{
    use RemembersSelectorChoice;

    #[Locked]
    public array $alerts = [];

    #[Locked]
    public int $page = 1;

    #[Locked]
    public int $lastPage = 1;

    #[Locked]
    public bool $editing = false;

    #[Locked]
    public ?string $alertId = null;

    #[Locked]
    public ?string $revision = null;

    #[Locked]
    public array $productChoice = [];

    #[Locked]
    public array $categoryChoice = [];

    #[Locked]
    public array $stockChoice = [];

    public string $target = 'producto';

    public string $scope = 'todos';

    public string $productId = '';

    public string $categoryId = '';

    public string $stockId = '';

    public string $title = '';

    public string $requirements = '';

    public bool $active = true;

    public function mount(): void
    {
        $this->goToPage(1);
    }

    public function goToPage(int $page): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'sale-alerts.list');
        try {
            $page = min(max(1, $page), $this->lastPage);
            $data = app(SaleAlertApiService::class)->page($page);
            if ($page > $data->lastPage) {
                $data = app(SaleAlertApiService::class)->page($data->lastPage);
            }
            $this->alerts = $data->items;
            $this->page = $data->currentPage;
            $this->lastPage = $data->lastPage;
            $this->resetValidation('list');
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->addError('list', $error->getMessage());
        }
    }

    public function start(?string $id = null): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'sale-alerts.manage');
        $this->cancel();
        $this->editing = true;
        if ($id === null) {
            return;
        }

        $alert = collect($this->alerts)->firstWhere('id', $id);
        abort_unless($alert, 404);
        $this->alertId = $id;
        $this->revision = $alert['revision'];
        $this->title = $alert['titulo'];
        $this->requirements = implode("\n", $alert['requisitos']);
        $this->active = $alert['activo'];
        $this->target = $alert['producto_id'] !== null ? 'producto' : 'categoria';
        $this->scope = $alert['stock_id'] === null ? 'todos' : 'stock';
        $this->productId = $alert['producto_id'] ?? '';
        $this->categoryId = $alert['categoria_id'] ?? '';
        $this->stockId = $alert['stock_id'] ?? '';
        $this->productChoice = [['id' => $this->productId, 'label' => trim(($alert['producto_codigo'] ?? '').' '.($alert['producto'] ?? ''))]];
        $this->categoryChoice = [['id' => $this->categoryId, 'label' => $alert['categoria'] ?? '']];
        $this->stockChoice = [['id' => $this->stockId, 'label' => $alert['stock'] ?? '']];
    }

    public function cancel(): void
    {
        $this->reset(['editing', 'alertId', 'revision', 'target', 'scope', 'title', 'requirements', 'active',
            'productId', 'categoryId', 'stockId', 'productChoice', 'categoryChoice', 'stockChoice']);
        $this->resetValidation();
    }

    public function save(): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'sale-alerts.manage');
        abort_unless($this->editing, 422);
        $this->validate([
            'target' => 'required|in:producto,categoria', 'scope' => 'required|in:todos,stock',
            'title' => 'required|string|max:150', 'requirements' => 'required|string|max:4800',
            'productId' => 'required_if:target,producto|nullable|integer|min:1',
            'categoryId' => 'required_if:target,categoria|nullable|integer|min:1',
            'stockId' => 'required_if:scope,stock|nullable|integer|min:1',
        ], [], ['title' => 'título', 'requirements' => 'requisitos', 'productId' => 'producto', 'categoryId' => 'categoría', 'stockId' => 'sucursal']);
        $requirements = array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->requirements)), fn (string $line): bool => $line !== ''));
        if (count($requirements) < 1 || count($requirements) > 15 || collect($requirements)->contains(fn (string $line): bool => mb_strlen($line) > 300)) {
            $this->addError('requirements', 'Agrega entre 1 y 15 requisitos de hasta 300 caracteres por línea.');

            return;
        }
        $data = [
            'objetivo' => $this->target, 'alcance' => $this->scope, 'titulo' => trim($this->title),
            'requisitos' => $requirements, 'activo' => $this->active,
            'producto_id' => $this->target === 'producto' ? $this->productId : null,
            'categoria_id' => $this->target === 'categoria' ? $this->categoryId : null,
            'stock_id' => $this->scope === 'stock' ? $this->stockId : null,
        ];
        if ($this->revision !== null) {
            $data['revision'] = $this->revision;
        }

        try {
            app(SaleAlertApiService::class)->save($this->alertId, $data);
            $page = $this->alertId === null ? 1 : $this->page;
            $this->cancel();
            $this->goToPage($page);
            session()->flash('sale-alert-status', 'Alerta guardada.');
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->addError('save', $error->getMessage());
        }
    }

    public function remove(string $id): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, 'sale-alerts.manage');
        $alert = collect($this->alerts)->firstWhere('id', $id);
        abort_unless($alert, 404);
        try {
            app(SaleAlertApiService::class)->remove($id, $alert['revision']);
            if ($this->alertId === $id) {
                $this->cancel();
            }
            $this->goToPage($this->page);
            session()->flash('sale-alert-status', 'Alerta retirada.');
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $error) {
            $this->addError('list', $error->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.farma.alertas-venta');
    }
}
