<?php

namespace App\Livewire\Config;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\StockApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

use function is_array;
use function is_scalar;

class Stocks extends Component
{
    use RemembersSelectorChoice;

    private const CREATE_ABILITIES = ['stocks.create', 'stocks.states'];

    private const UPDATE_ABILITIES = ['stocks.search', 'stocks.update', 'stocks.states'];

    /** @var array<int, array<string, mixed>> */
    public array $stocks = [];

    /** @var array<int, array{id: string, label: string}> */
    public array $states = [];

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public string $search = '';

    public ?string $loadError = null;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public string $descripcion = '';

    public string $telefono = '';

    public string $ubicacion = '';

    public string $estado = '';

    public function mount(): void
    {
        $this->loadPage();
    }

    public function openCreate(): void
    {
        $this->authorizeAbilities(self::CREATE_ABILITIES);
        $this->resetValidation();
        $this->resetForm();

        if (! $this->loadStates()) {
            return;
        }

        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAbilities(self::UPDATE_ABILITIES);
        $this->resetValidation();

        if (! $this->loadStates()) {
            return;
        }

        try {
            $stock = $this->normalizeStock($this->api()->find($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible cargar el stock', $exception->getMessage());

            return;
        }

        $this->editingId = $id;
        $this->descripcion = (string) $stock['descripcion'];
        $this->telefono = (string) $stock['telefono'];
        $this->ubicacion = (string) $stock['ubicacion'];
        $this->estado = (string) $stock['estado'];
        $this->showFormModal = true;
    }

    public function closeForm(): void
    {
        $this->showFormModal = false;
        $this->resetValidation();
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorizeAbility(
            $this->editingId === null ? 'stocks.create' : 'stocks.update',
        );

        $validated = $this->validate([
            'descripcion' => ['required', 'string', 'max:180'],
            'telefono' => ['required', 'string', 'max:20'],
            'ubicacion' => ['required', 'string', 'max:180'],
            'estado' => ['required'],
        ], [
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.max' => 'La descripción no puede exceder 180 caracteres.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres.',
            'ubicacion.required' => 'La ubicación es obligatoria.',
            'ubicacion.max' => 'La ubicación no puede exceder 180 caracteres.',
            'estado.required' => 'Selecciona un estado.',
        ]);

        $payload = [
            'descripcion' => trim($validated['descripcion']),
            'telefono' => trim($validated['telefono']),
            'ubicacion' => trim($validated['ubicacion']),
            'estado' => $this->castIdentifier($validated['estado']),
        ];

        try {
            $message = $this->editingId === null
                ? $this->api()->create($payload)
                : $this->api()->update($this->editingId, $payload);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->applyApiErrors($exception);

            return;
        }

        $isCreating = $this->editingId === null;
        $this->showFormModal = false;
        $this->dispatchToast($message !== ''
            ? $message
            : ($isCreating ? 'Stock creado correctamente.' : 'Stock actualizado correctamente.'));
        $this->resetForm();
        $this->loadPage($isCreating ? 1 : $this->page);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('stocks.delete');

        $stock = collect($this->stocks)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );

        $this->deletingId = $id;
        $this->deletingName = is_array($stock)
            ? (string) $stock['descripcion']
            : "#{$id}";
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
    }

    public function delete(): void
    {
        $this->authorizeAbility('stocks.delete');

        if ($this->deletingId === null) {
            return;
        }

        try {
            $message = $this->api()->delete($this->deletingId);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->showDeleteModal = false;
            $this->dispatchAlert('No fue posible eliminar el stock', $exception->getMessage());

            return;
        }

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->dispatchToast($message !== '' ? $message : 'Stock eliminado correctamente.');
        $targetPage = count($this->stocks) === 1 && $this->page > 1
            ? $this->page - 1
            : $this->page;
        $this->loadPage($targetPage);
    }

    public function goToPage(int $page): void
    {
        if ($page < 1 || $page > $this->lastPage || $page === $this->page) {
            return;
        }

        $this->loadPage($page);
    }

    public function previousPage(): void
    {
        $this->goToPage($this->page - 1);
    }

    public function nextPage(): void
    {
        $this->goToPage($this->page + 1);
    }

    public function updatedSearch(): void
    {
        $this->loadPage(1);
    }

    public function render(): View
    {
        $permissions = $this->permissions();
        $visibleStocks = $this->stocks;

        return view('livewire.config.stocks', [
            'visibleStocks' => $visibleStocks,
            'access' => [
                'createStocks' => $permissions->allowsAll(self::CREATE_ABILITIES),
                'editStocks' => $permissions->allowsAll(self::UPDATE_ABILITIES),
                'deleteStocks' => $permissions->allows('stocks.delete'),
            ],
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('stocks.list');
        $this->loadError = null;

        try {
            $this->applyPage($this->api()->list($page, $this->search));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            if ($this->stocks === []) {
                $this->loadError = $exception->getMessage();
            } else {
                $this->dispatchAlert('No fue posible actualizar el listado', $exception->getMessage());
            }
        }
    }

    private function loadStates(): bool
    {
        return true;
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->stocks = collect($page->items)
            ->map(fn (array $stock): array => $this->normalizeStock($stock))
            ->filter(fn (array $stock): bool => $stock['id'] > 0)
            ->values()
            ->all();
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    /**
     * @param  array<string, mixed>  $stock
     * @return array<string, mixed>
     */
    private function normalizeStock(array $stock): array
    {
        $state = $stock['estado'] ?? $stock['state'] ?? null;

        return [
            'id' => (int) ($stock['id'] ?? 0),
            'descripcion' => (string) ($stock['descripcion'] ?? $stock['description'] ?? ''),
            'telefono' => (string) ($stock['telefono'] ?? $stock['phone'] ?? ''),
            'ubicacion' => (string) ($stock['ubicacion'] ?? $stock['location'] ?? ''),
            'estado' => (string) $this->identifier($state),
            'state_label' => $this->description($state),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{id: string, label: string}>
     */
    private function normalizeCatalog(array $items): array
    {
        return collect($items)
            ->map(function (array $item): array {
                $id = $item['id'] ?? $item['value'] ?? null;
                $label = $item['descripcion']
                    ?? $item['description']
                    ?? $item['nombre']
                    ?? $item['name']
                    ?? $item['label']
                    ?? $id;

                return [
                    'id' => is_scalar($id) ? (string) $id : '',
                    'label' => is_scalar($label) ? (string) $label : '',
                ];
            })
            ->filter(fn (array $item): bool => $item['id'] !== '')
            ->values()
            ->all();
    }

    private function identifier(mixed $value): int|string
    {
        if (is_array($value)) {
            $value = $value['id'] ?? $value['value'] ?? '';
        }

        return is_int($value) ? $value : (is_scalar($value) ? (string) $value : '');
    }

    private function description(mixed $value): string
    {
        if (is_array($value)) {
            foreach (['descripcion', 'description', 'nombre', 'name', 'label'] as $key) {
                if (is_scalar($value[$key] ?? null)) {
                    return (string) $value[$key];
                }
            }
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function castIdentifier(mixed $value): int|string
    {
        return is_numeric($value) ? (int) $value : (string) $value;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->descripcion = '';
        $this->telefono = '';
        $this->ubicacion = '';
        $this->estado = '';
    }

    private function applyApiErrors(ApiOperationException $exception): void
    {
        foreach ($exception->errors as $field => $messages) {
            $field = $field === 'estado.id' ? 'estado' : $field;

            foreach ((array) $messages as $message) {
                $this->addError($field, (string) $message);
            }
        }

        if ($exception->errors === []) {
            $this->dispatchAlert('No fue posible guardar el stock', $exception->getMessage());
        }
    }

    private function api(): StockApiService
    {
        return app(StockApiService::class);
    }

    private function permissions(): CurrentUserPermissions
    {
        return app(CurrentUserPermissions::class);
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }

    /** @param  array<int, string>  $abilities */
    private function authorizeAbilities(array $abilities): void
    {
        foreach ($abilities as $ability) {
            $this->authorizeAbility($ability);
        }
    }

    private function redirectToLogin(): void
    {
        $this->loadError = null;
        $this->redirectRoute('login');
    }

    private function dispatchToast(string $message): void
    {
        $this->dispatch('sweetalert:toast', icon: 'success', title: $message);
    }

    private function dispatchAlert(string $title, string $message): void
    {
        $this->dispatch(
            'sweetalert:alert',
            icon: 'error',
            title: $title,
            text: $message,
        );
    }
}
