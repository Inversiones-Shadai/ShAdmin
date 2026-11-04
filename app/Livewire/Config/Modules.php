<?php

namespace App\Livewire\Config;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\ModuleApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

use function is_array;
use function is_scalar;

class Modules extends Component
{
    use RemembersSelectorChoice;

    private const CREATE_ABILITIES = [
        'modules.create',
        'modules.states',
    ];

    private const UPDATE_ABILITIES = [
        'modules.search',
        'modules.update',
        'modules.states',
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $modules = [];

    /**
     * @var array<int, array{id: string, label: string}>
     */
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

    public string $nombre = '';

    public string $codigo = '';

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
            $module = $this->normalizeModule($this->api()->find($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible cargar el módulo', $exception->getMessage());

            return;
        }

        $this->editingId = $id;
        $this->nombre = (string) $module['nombre'];
        $this->codigo = (string) $module['codigo'];
        $this->estado = (string) $module['estado'];
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
            $this->editingId === null ? 'modules.create' : 'modules.update',
        );

        $rules = $this->editingId === null
            ? [
                'nombre' => ['required', 'string', 'max:150'],
                'codigo' => ['required', 'string', 'max:20'],
                'estado' => ['required'],
            ]
            : [
                'estado' => ['required'],
            ];

        $validated = $this->validate($rules, [
            'nombre.required' => 'El nombre es obligatorio.',
            'codigo.required' => 'El código es obligatorio.',
            'codigo.max' => 'El código no puede exceder 20 caracteres.',
            'estado.required' => 'Selecciona un estado.',
        ]);

        try {
            if ($this->editingId === null) {
                $message = $this->api()->create([
                    'nombre' => trim($validated['nombre']),
                    'codigo' => Str::lower(trim($validated['codigo'])),
                    'estado' => $this->castIdentifier($validated['estado']),
                ]);
            } else {
                $message = $this->api()->update([
                    'id' => $this->editingId,
                    'estado' => $this->castIdentifier($validated['estado']),
                ]);
            }
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
            : ($isCreating ? 'Módulo creado correctamente.' : 'Módulo actualizado correctamente.'));
        $this->resetForm();
        $this->loadPage($isCreating ? 1 : $this->page);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('modules.delete');

        $module = collect($this->modules)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );

        $this->deletingId = $id;
        $this->deletingName = is_array($module)
            ? (string) $module['nombre']
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
        $this->authorizeAbility('modules.delete');

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
            $this->dispatchAlert('No fue posible eliminar el módulo', $exception->getMessage());

            return;
        }

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->dispatchToast($message !== '' ? $message : 'Módulo eliminado correctamente.');
        $targetPage = count($this->modules) === 1 && $this->page > 1
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
        $needle = Str::lower(trim($this->search));
        $visibleModules = $needle === ''
            ? $this->modules
            : array_values(array_filter(
                $this->modules,
                fn (array $module): bool => Str::contains(
                    Str::lower(implode(' ', [
                        $module['nombre'],
                        $module['codigo'],
                        $module['state_label'],
                    ])),
                    $needle,
                ),
            ));

        return view('livewire.config.modules', [
            'visibleModules' => $visibleModules,
            'access' => [
                'createModules' => $permissions->allowsAll(self::CREATE_ABILITIES),
                'editModules' => $permissions->allowsAll(self::UPDATE_ABILITIES),
                'deleteModules' => $permissions->allows('modules.delete'),
            ],
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('modules.list');
        $this->loadError = null;

        try {
            $this->applyPage($this->api()->list($page));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            if ($this->modules === []) {
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
        $this->modules = collect($page->items)
            ->map(fn (array $module): array => $this->normalizeModule($module))
            ->filter(fn (array $module): bool => $module['id'] > 0)
            ->values()
            ->all();
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    /**
     * @param  array<string, mixed>  $module
     * @return array<string, mixed>
     */
    private function normalizeModule(array $module): array
    {
        $state = $module['estado'] ?? $module['state'] ?? null;

        return [
            'id' => (int) ($module['id'] ?? 0),
            'nombre' => (string) ($module['nombre'] ?? $module['name'] ?? ''),
            'codigo' => (string) ($module['codigo'] ?? $module['code'] ?? ''),
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
        $this->nombre = '';
        $this->codigo = '';
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
            $this->dispatchAlert('No fue posible guardar el módulo', $exception->getMessage());
        }
    }

    private function api(): ModuleApiService
    {
        return app(ModuleApiService::class);
    }

    private function permissions(): CurrentUserPermissions
    {
        return app(CurrentUserPermissions::class);
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }

    /**
     * @param  array<int, string>  $abilities
     */
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
        $this->dispatch(
            'sweetalert:toast',
            icon: 'success',
            title: $message,
        );
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
