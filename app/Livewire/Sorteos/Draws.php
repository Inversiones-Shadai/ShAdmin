<?php

namespace App\Livewire\Sorteos;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\DrawApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

use function is_array;
use function is_scalar;

class Draws extends Component
{
    use RemembersSelectorChoice;

    private const CREATE_ABILITIES = ['draws.create', 'draws.states'];

    private const UPDATE_ABILITIES = ['draws.search', 'draws.update', 'draws.states'];

    /** @var array<int, array<string, mixed>> */
    public array $draws = [];

    /** @var array<int, array{id: string, label: string}> */
    public array $states = [];

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public string $filterTerm = '';

    public bool $filterActive = false;

    public ?string $loadError = null;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public string $nombre = '';

    public string $descripcion = '';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public string $impresiones = '0';

    public string $valor = '0';

    public string $estado = '';

    public function mount(): void
    {
        $this->loadPage();
    }

    public function applyFilter(): void
    {
        $this->authorizeAbility('draws.search');

        $validated = $this->validate([
            'filterTerm' => ['required', 'string', 'max:100'],
        ], [
            'filterTerm.required' => 'Escribe un término para buscar.',
            'filterTerm.max' => 'La búsqueda no puede exceder 100 caracteres.',
        ]);

        $this->filterTerm = trim($validated['filterTerm']);
        $this->filterActive = true;
        $this->loadPage();
    }

    public function updatedFilterTerm(): void
    {
        if (trim($this->filterTerm) === '') {
            $this->clearFilter();
        } else {
            $this->applyFilter();
        }
    }

    public function clearFilter(): void
    {
        $this->filterTerm = '';
        $this->filterActive = false;
        $this->resetValidation('filterTerm');
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
            $draw = $this->normalizeDraw($this->api()->find($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible cargar el sorteo', $exception->getMessage());

            return;
        }

        $this->editingId = $id;
        $this->nombre = (string) $draw['nombre'];
        $this->descripcion = (string) $draw['descripcion'];
        $this->fechaInicio = (string) $draw['fecha_inicio'];
        $this->fechaFin = (string) $draw['fecha_fin'];
        $this->impresiones = (string) $draw['impresiones'];
        $this->valor = (string) $draw['valor'];
        $this->estado = (string) $draw['estado'];
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
        $isCreating = $this->editingId === null;
        $this->authorizeAbility($isCreating ? 'draws.create' : 'draws.update');

        $rules = [
            'nombre' => ['required', 'string', 'max:200'],
            'descripcion' => ['required', 'string', 'max:200'],
            'impresiones' => ['nullable', 'integer', 'min:0'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required'],
        ];

        if ($isCreating) {
            $rules['fechaInicio'] = ['required', 'date'];
            $rules['fechaFin'] = ['required', 'date', 'after_or_equal:fechaInicio'];
        }

        $validated = $this->validate($rules, [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 200 caracteres.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.max' => 'La descripción no puede exceder 200 caracteres.',
            'fechaInicio.required' => 'La fecha de inicio es obligatoria.',
            'fechaInicio.date' => 'La fecha de inicio no es válida.',
            'fechaFin.required' => 'La fecha de finalización es obligatoria.',
            'fechaFin.date' => 'La fecha de finalización no es válida.',
            'fechaFin.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'impresiones.integer' => 'Las impresiones deben ser un número entero.',
            'impresiones.min' => 'Las impresiones no pueden ser negativas.',
            'valor.numeric' => 'El valor debe ser numérico.',
            'valor.min' => 'El valor no puede ser negativo.',
            'estado.required' => 'Selecciona un estado.',
        ]);

        $payload = [
            'nombre' => trim($validated['nombre']),
            'descripcion' => trim($validated['descripcion']),
            'impresiones' => (int) ($validated['impresiones'] ?: 0),
            'valor' => (float) ($validated['valor'] ?: 0),
            'estado' => $this->castIdentifier($validated['estado']),
        ];

        if ($isCreating) {
            $payload['fecha_inicio'] = $validated['fechaInicio'];
            $payload['fecha_fin'] = $validated['fechaFin'];
        } else {
            $payload['id'] = $this->editingId;
        }

        try {
            $message = $isCreating
                ? $this->api()->create($payload)
                : $this->api()->update($payload);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->applyApiErrors($exception);

            return;
        }

        $this->showFormModal = false;
        $this->dispatchToast($message !== ''
            ? $message
            : ($isCreating ? 'Sorteo creado correctamente.' : 'Sorteo actualizado correctamente.'));
        $this->resetForm();
        $this->loadPage($isCreating ? 1 : $this->page);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('draws.delete');

        $draw = collect($this->draws)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );

        $this->deletingId = $id;
        $this->deletingName = is_array($draw)
            ? (string) $draw['nombre']
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
        $this->authorizeAbility('draws.delete');

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
            $this->dispatchAlert('No fue posible eliminar el sorteo', $exception->getMessage());

            return;
        }

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->dispatchToast($message !== '' ? $message : 'Sorteo eliminado correctamente.');
        $targetPage = count($this->draws) === 1 && $this->page > 1
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

    public function render(): View
    {
        $permissions = $this->permissions();

        return view('livewire.sorteos.draws', [
            'access' => [
                'createDraws' => $permissions->allowsAll(self::CREATE_ABILITIES),
                'searchDraws' => $permissions->allows('draws.search'),
                'editDraws' => $permissions->allowsAll(self::UPDATE_ABILITIES),
                'deleteDraws' => $permissions->allows('draws.delete'),
            ],
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility($this->filterActive ? 'draws.search' : 'draws.list');
        $this->loadError = null;

        try {
            $result = $this->filterActive
                ? $this->api()->filter($this->filterTerm, $page)
                : $this->api()->list($page);
            $this->applyPage($result);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            if ($exception->status === 404) {
                $this->applyEmptyPage();

                return;
            }

            if ($this->draws === []) {
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
        $this->draws = collect($page->items)
            ->map(fn (array $draw): array => $this->normalizeDraw($draw))
            ->filter(fn (array $draw): bool => $draw['id'] > 0)
            ->values()
            ->all();
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    private function applyEmptyPage(): void
    {
        $this->draws = [];
        $this->total = 0;
        $this->page = 1;
        $this->lastPage = 1;
    }

    /**
     * @param  array<string, mixed>  $draw
     * @return array<string, mixed>
     */
    private function normalizeDraw(array $draw): array
    {
        $state = $draw['estado'] ?? $draw['state'] ?? null;

        return [
            'id' => (int) ($draw['id'] ?? 0),
            'nombre' => (string) ($draw['nombre'] ?? $draw['name'] ?? ''),
            'descripcion' => (string) ($draw['descripcion'] ?? $draw['description'] ?? ''),
            'fecha_inicio' => $this->dateValue($draw['fecha_inicio'] ?? $draw['start_date'] ?? ''),
            'fecha_fin' => $this->dateValue($draw['fecha_fin'] ?? $draw['end_date'] ?? ''),
            'impresiones' => (int) ($draw['impresiones'] ?? 0),
            'valor' => (float) ($draw['valor'] ?? 0),
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

    private function dateValue(mixed $value): string
    {
        return is_scalar($value) ? substr((string) $value, 0, 10) : '';
    }

    private function castIdentifier(mixed $value): int|string
    {
        return is_numeric($value) ? (int) $value : (string) $value;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->nombre = '';
        $this->descripcion = '';
        $this->fechaInicio = '';
        $this->fechaFin = '';
        $this->impresiones = '0';
        $this->valor = '0';
        $this->estado = '';
    }

    private function applyApiErrors(ApiOperationException $exception): void
    {
        foreach ($exception->errors as $field => $messages) {
            $field = match ($field) {
                'estado.id' => 'estado',
                'fecha_inicio' => 'fechaInicio',
                'fecha_fin' => 'fechaFin',
                default => $field,
            };

            foreach ((array) $messages as $message) {
                $this->addError($field, (string) $message);
            }
        }

        if ($exception->errors === []) {
            $this->dispatchAlert('No fue posible guardar el sorteo', $exception->getMessage());
        }
    }

    private function api(): DrawApiService
    {
        return app(DrawApiService::class);
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
