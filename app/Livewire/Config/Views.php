<?php

namespace App\Livewire\Config;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\ViewApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

use function is_array;
use function is_scalar;

class Views extends Component
{
    use RemembersSelectorChoice;

    private const CREATE_ABILITIES = [
        'views.create',
        'views.modules',
    ];

    private const UPDATE_ABILITIES = [
        'views.search',
        'views.update',
    ];

    private const FILTER_ABILITIES = [
        'views.modules',
        'views.by-module',
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $views = [];

    /**
     * @var array<int, array{id: string, label: string}>
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

    public bool $showModuleFilter = false;

    public string $filterModule = '';

    public ?string $loadError = null;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public string $modulo = '';

    public string $nombre = '';

    public string $codigo = '';

    public string $estado = '';

    public bool $showActionsModal = false;

    public bool $showActionDeleteModal = false;

    public ?int $actionsViewId = null;

    public string $actionsViewName = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $actions = [];

    public string $actionName = '';

    public string $actionCode = '';

    public ?int $deletingActionId = null;

    public string $deletingActionName = '';

    public function mount(): void
    {
        $this->loadPage();
    }

    public function openModuleFilter(): void
    {
        $this->authorizeAbilities(self::FILTER_ABILITIES);

        if (! $this->loadModules()) {
            return;
        }

        $this->showModuleFilter = true;
    }

    public function updatedFilterModule(string $moduleId): void
    {
        $this->authorizeAbilities(self::FILTER_ABILITIES);

        if ($moduleId === '') {
            $this->loadPage();

            return;
        }

        $this->loadPage();
    }

    public function clearModuleFilter(): void
    {
        $this->authorizeAbility('views.list');
        $this->filterModule = '';
        $this->showModuleFilter = false;
        $this->loadPage();
    }

    public function openCreate(): void
    {
        $this->authorizeAbilities(self::CREATE_ABILITIES);
        $this->resetValidation();
        $this->resetForm();

        if (! $this->loadFormCatalogs()) {
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
            $view = $this->normalizeView($this->api()->find($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible cargar la vista', $exception->getMessage());

            return;
        }

        $this->editingId = $id;
        $this->modulo = (string) $view['modulo'];
        $this->nombre = (string) $view['nombre'];
        $this->codigo = (string) $view['codigo'];
        $this->estado = (string) $view['estado'];
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
            $this->editingId === null ? 'views.create' : 'views.update',
        );

        $rules = $this->editingId === null
            ? [
                'modulo' => ['required'],
                'nombre' => ['required', 'string', 'max:50'],
                'codigo' => ['required', 'string', 'max:10'],
                'estado' => ['required'],
            ]
            : [
                'estado' => ['required'],
            ];

        $validated = $this->validate($rules, [
            'modulo.required' => 'Selecciona un módulo.',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 50 caracteres.',
            'codigo.required' => 'El código es obligatorio.',
            'codigo.max' => 'El código no puede exceder 10 caracteres.',
            'estado.required' => 'Selecciona un estado.',
        ]);

        try {
            if ($this->editingId === null) {
                $message = $this->api()->create([
                    'modulo' => $this->castIdentifier($validated['modulo']),
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
            $this->applyApiErrors($exception, 'No fue posible guardar la vista');

            return;
        }

        $isCreating = $this->editingId === null;
        $this->showFormModal = false;
        $this->dispatchToast($message !== ''
            ? $message
            : ($isCreating ? 'Vista creada correctamente.' : 'Vista actualizada correctamente.'));
        $this->resetForm();
        $this->loadPage($isCreating ? 1 : $this->page);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('views.delete');

        $view = collect($this->views)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );

        $this->deletingId = $id;
        $this->deletingName = is_array($view)
            ? (string) $view['nombre']
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
        $this->authorizeAbility('views.delete');

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
            $this->dispatchAlert('No fue posible eliminar la vista', $exception->getMessage());

            return;
        }

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->dispatchToast($message !== '' ? $message : 'Vista eliminada correctamente.');
        $targetPage = count($this->views) === 1 && $this->page > 1
            ? $this->page - 1
            : $this->page;
        $this->loadPage($targetPage);
    }

    public function openActions(int $viewId): void
    {
        $this->authorizeAbility('views.actions');
        $this->resetActionState();

        $view = collect($this->views)->first(
            fn (array $item): bool => (int) $item['id'] === $viewId,
        );

        $this->actionsViewId = $viewId;
        $this->actionsViewName = is_array($view)
            ? (string) $view['nombre']
            : "#{$viewId}";

        try {
            $this->actions = $this->normalizeActions($this->api()->actions($viewId));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible cargar las acciones', $exception->getMessage());

            return;
        }

        $this->showActionsModal = true;
    }

    public function closeActions(): void
    {
        $this->showActionsModal = false;
        $this->showActionDeleteModal = false;
        $this->resetValidation();
        $this->resetActionState();
    }

    public function saveAction(): void
    {
        $this->authorizeAbility('views.create-action');

        if ($this->actionsViewId === null) {
            return;
        }

        $validated = $this->validate([
            'actionName' => ['required', 'string', 'max:80'],
            'actionCode' => ['required', 'string', 'max:20'],
        ], [
            'actionName.required' => 'El nombre de la acción es obligatorio.',
            'actionName.max' => 'El nombre no puede exceder 80 caracteres.',
            'actionCode.required' => 'El código del botón es obligatorio.',
            'actionCode.max' => 'El código no puede exceder 20 caracteres.',
        ]);

        try {
            $message = $this->api()->createAction([
                'vista' => $this->actionsViewId,
                'nombre' => trim($validated['actionName']),
                'codigo' => Str::lower(trim($validated['actionCode'])),
            ]);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->applyApiErrors($exception, 'No fue posible crear la acción');

            return;
        }

        $this->dispatchToast($message !== '' ? $message : 'Acción creada correctamente.');
        $this->actionName = '';
        $this->actionCode = '';
        $this->resetValidation(['actionName', 'actionCode']);
        $this->reloadActions();
    }

    public function confirmActionDelete(int $id): void
    {
        $this->authorizeAbility('views.delete-action');

        $action = collect($this->actions)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );

        $this->deletingActionId = $id;
        $this->deletingActionName = is_array($action)
            ? implode(' · ', [$action['nombre'], $action['codigo']])
            : "#{$id}";
        $this->showActionDeleteModal = true;
    }

    public function cancelActionDelete(): void
    {
        $this->showActionDeleteModal = false;
        $this->deletingActionId = null;
        $this->deletingActionName = '';
    }

    public function deleteAction(): void
    {
        $this->authorizeAbility('views.delete-action');

        if ($this->deletingActionId === null) {
            return;
        }

        try {
            $message = $this->api()->deleteAction($this->deletingActionId);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->showActionDeleteModal = false;
            $this->dispatchAlert('No fue posible eliminar la acción', $exception->getMessage());

            return;
        }

        $this->showActionDeleteModal = false;
        $this->deletingActionId = null;
        $this->deletingActionName = '';
        $this->dispatchToast($message !== '' ? $message : 'Acción eliminada correctamente.');
        $this->reloadActions();
    }

    public function goToPage(int $page): void
    {
        if ($this->filterModule !== ''
            || $page < 1
            || $page > $this->lastPage
            || $page === $this->page) {
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
        $visibleViews = $this->views;

        return view('livewire.config.views', [
            'visibleViews' => $visibleViews,
            'access' => [
                'createViews' => $permissions->allowsAll(self::CREATE_ABILITIES),
                'editViews' => $permissions->allowsAll(self::UPDATE_ABILITIES),
                'deleteViews' => $permissions->allows('views.delete'),
                'filterViews' => $permissions->allowsAll(self::FILTER_ABILITIES),
                'manageActions' => $permissions->allows('views.actions'),
                'createActions' => $permissions->allows('views.create-action'),
                'deleteActions' => $permissions->allows('views.delete-action'),
            ],
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('views.list');
        $this->loadError = null;

        try {
            $result = $this->filterModule === ''
                ? $this->api()->list($page, $this->search)
                : $this->api()->listByModule((int) $this->filterModule, $this->search);
            $this->applyPage($result);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            if ($this->views === []) {
                $this->loadError = $exception->getMessage();
            } else {
                $this->dispatchAlert('No fue posible actualizar el listado', $exception->getMessage());
            }
        }
    }

    private function loadFormCatalogs(): bool
    {
        return $this->loadModules() && $this->loadStates();
    }

    private function loadModules(): bool
    {
        return true;
    }

    private function loadStates(): bool
    {
        return true;
    }

    private function reloadActions(): void
    {
        if ($this->actionsViewId === null) {
            return;
        }

        try {
            $this->actions = $this->normalizeActions(
                $this->api()->actions($this->actionsViewId),
            );
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible actualizar las acciones', $exception->getMessage());
        }
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->views = collect($page->items)
            ->map(fn (array $view): array => $this->normalizeView($view))
            ->filter(fn (array $view): bool => $view['id'] > 0)
            ->values()
            ->all();
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    /**
     * @param  array<string, mixed>  $view
     * @return array<string, mixed>
     */
    private function normalizeView(array $view): array
    {
        $module = $view['modulo'] ?? $view['module'] ?? null;
        $state = $view['estado'] ?? $view['state'] ?? null;

        return [
            'id' => (int) ($view['id'] ?? 0),
            'modulo' => (string) $this->identifier($module),
            'module_label' => $this->description($module),
            'nombre' => (string) ($view['nombre'] ?? $view['name'] ?? ''),
            'codigo' => (string) ($view['codigo'] ?? $view['code'] ?? ''),
            'estado' => (string) $this->identifier($state),
            'state_label' => $this->description($state),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeActions(array $actions): array
    {
        return collect($actions)
            ->map(fn (array $action): array => [
                'id' => (int) ($action['id'] ?? 0),
                'nombre' => (string) ($action['nombre'] ?? $action['name'] ?? ''),
                'codigo' => (string) ($action['codigo'] ?? $action['code'] ?? ''),
            ])
            ->filter(fn (array $action): bool => $action['id'] > 0)
            ->values()
            ->all();
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
        $this->modulo = '';
        $this->nombre = '';
        $this->codigo = '';
        $this->estado = '';
    }

    private function resetActionState(): void
    {
        $this->actionsViewId = null;
        $this->actionsViewName = '';
        $this->actions = [];
        $this->actionName = '';
        $this->actionCode = '';
        $this->deletingActionId = null;
        $this->deletingActionName = '';
    }

    private function applyApiErrors(ApiOperationException $exception, string $title): void
    {
        foreach ($exception->errors as $field => $messages) {
            $field = match ($field) {
                'modulo.id' => 'modulo',
                'estado.id' => 'estado',
                'vista.id' => 'actionsViewId',
                default => $field,
            };

            foreach ((array) $messages as $message) {
                $this->addError($field, (string) $message);
            }
        }

        if ($exception->errors === []) {
            $this->dispatchAlert($title, $exception->getMessage());
        }
    }

    private function api(): ViewApiService
    {
        return app(ViewApiService::class);
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
