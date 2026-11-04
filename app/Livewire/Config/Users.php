<?php

namespace App\Livewire\Config;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\PermissionApiService;
use App\Services\Api\UserApiService;
use App\Services\Api\WorkHoursApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

use function is_array;
use function is_scalar;

class Users extends Component
{
    use RemembersSelectorChoice;

    private const USER_CREATE_ABILITIES = [
        'users.create',
        'users.roles',
        'users.states',
    ];

    private const USER_UPDATE_ABILITIES = [
        'users.search',
        'users.update',
        'users.roles',
        'users.states',
    ];

    private const WORK_HOURS_MANAGE_ABILITIES = [
        'users.work-hours',
        'users.create-work-hours',
        'users.update-work-hours',
    ];

    private const PERMISSION_CREATE_ABILITIES = [
        'permissions.create',
        'permissions.modules',
        'permissions.views',
        'permissions.actions',
        'permissions.time-types',
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $users = [];

    /**
     * @var array<int, array{id: string, label: string}>
     */
    public array $roles = [];

    /**
     * @var array<int, array{id: string, label: string}>
     */
    public array $states = [];

    public string $search = '';

    public ?string $loadError = null;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public string $nombre = '';

    public string $name = '';

    public string $email = '';

    public string $rol = '';

    public string $estado = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public bool $showWorkHoursModal = false;

    public ?int $workHoursUserId = null;

    public string $workHoursUserName = '';

    public ?int $workHoursId = null;

    public string $workHours = '';

    public string $lunchHours = '';

    public bool $showPermissionsModal = false;

    public bool $showPermissionDeleteModal = false;

    public ?int $permissionsUserId = null;

    public string $permissionsUserName = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $permissions = [];

    public int $permissionTotal = 0;

    public int $permissionPage = 1;

    public int $permissionLastPage = 1;

    /**
     * @var array<int, array{id: string, label: string}>
     */
    public array $permissionModules = [];

    /**
     * @var array<int, array{id: string, label: string}>
     */
    public array $permissionViews = [];

    /**
     * @var array<int, array{id: string, label: string}>
     */
    public array $permissionActions = [];

    /**
     * @var array<int, array{id: string, label: string}>
     */
    public array $permissionTimeTypes = [];

    public string $permissionModule = '';

    public string $permissionView = '';

    public string $permissionAction = '';

    public string $permissionTimeType = '';

    public ?int $deletingPermissionId = null;

    public string $deletingPermissionName = '';

    public function mount(): void
    {
        $this->reload();
    }

    public function reload(): void
    {
        $this->authorizeAbility('users.list');
        $this->loadError = null;

        try {
            $this->users = $this->normalizeUsers($this->api()->list());
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    public function openCreate(): void
    {
        $this->authorizeAbilities(self::USER_CREATE_ABILITIES);
        $this->resetValidation();
        $this->resetForm();

        if (! $this->loadUserCatalogs()) {
            return;
        }

        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAbilities(self::USER_UPDATE_ABILITIES);
        $this->resetValidation();
        $this->loadError = null;

        if (! $this->loadUserCatalogs()) {
            return;
        }

        try {
            $user = $this->normalizeUser($this->api()->find($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert(
                'No fue posible cargar el usuario',
                $exception->getMessage(),
            );

            return;
        }

        $this->editingId = $id;
        $this->nombre = (string) $user['nombre'];
        $this->name = (string) $user['name'];
        $this->email = (string) $user['email'];
        $this->rol = (string) $user['rol'];
        $this->estado = (string) $user['estado'];
        $this->password = '';
        $this->passwordConfirmation = '';
        $this->showFormModal = true;
    }

    public function closeForm(): void
    {
        $this->showFormModal = false;
        $this->resetValidation();
        $this->resetForm();
    }

    public function openWorkHours(int $userId): void
    {
        $this->authorizeAbilities(self::WORK_HOURS_MANAGE_ABILITIES);
        $this->resetValidation();
        $this->resetWorkHours();

        $user = collect($this->users)->first(
            fn (array $item): bool => (int) $item['id'] === $userId,
        );

        $this->workHoursUserId = $userId;
        $this->workHoursUserName = is_array($user)
            ? (string) ($user['nombre'] ?: $user['name'])
            : "#{$userId}";

        try {
            $schedule = $this->workHoursApi()->findByUser($userId);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert(
                'No fue posible cargar el horario',
                $exception->getMessage(),
            );

            $this->resetWorkHours();

            return;
        }

        if ($schedule !== null) {
            $id = $schedule['id'] ?? null;

            if (! is_scalar($id) || ! is_numeric($id)) {
                $this->dispatchAlert(
                    'No fue posible cargar el horario',
                    'La API no devolvió el identificador de la configuración.',
                );
                $this->resetWorkHours();

                return;
            }

            $this->workHoursId = (int) $id;
            $this->workHours = $this->numericValue($schedule['horas_lab'] ?? '');
            $this->lunchHours = $this->numericValue($schedule['horas_lunch'] ?? '');
        }

        $this->showWorkHoursModal = true;
    }

    public function closeWorkHours(): void
    {
        $this->showWorkHoursModal = false;
        $this->resetValidation();
        $this->resetWorkHours();
    }

    public function saveWorkHours(): void
    {
        $this->authorizeAbility(
            $this->workHoursId === null
                ? 'users.create-work-hours'
                : 'users.update-work-hours',
        );

        if ($this->workHoursUserId === null) {
            return;
        }

        $validated = $this->validate([
            'workHours' => ['required', 'integer', 'min:1', 'max:24'],
            'lunchHours' => ['required', 'integer', 'min:1', 'max:160'],
        ], [
            'workHours.required' => 'Indica las horas de la jornada laboral.',
            'workHours.integer' => 'Las horas de la jornada deben ser un número entero.',
            'workHours.min' => 'La jornada laboral debe ser de al menos 1 hora.',
            'workHours.max' => 'La jornada laboral no puede exceder 24 horas.',
            'lunchHours.required' => 'Indica los minutos de almuerzo.',
            'lunchHours.integer' => 'Los minutos de almuerzo deben ser un número entero.',
            'lunchHours.min' => 'El almuerzo debe durar al menos 1 minuto.',
            'lunchHours.max' => 'El almuerzo no puede exceder 160 minutos.',
        ]);

        try {
            if ($this->workHoursId === null) {
                $message = $this->workHoursApi()->create([
                    'usuario' => $this->workHoursUserId,
                    'horas_lab' => (int) $validated['workHours'],
                    'horas_lunch' => (int) $validated['lunchHours'],
                ]);
            } else {
                $message = $this->workHoursApi()->update([
                    'usuario' => $this->workHoursUserId,
                    'horas_lab' => (int) $validated['workHours'],
                    'horas_lunch' => (int) $validated['lunchHours'],
                ]);
            }
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->applyWorkHoursApiErrors($exception);

            return;
        }

        $this->showWorkHoursModal = false;
        $this->dispatchToast(
            $message !== '' ? $message : 'Horario laboral guardado correctamente.',
        );
        $this->resetWorkHours();
    }

    public function openPermissions(int $userId): void
    {
        $this->authorizeAbility('permissions.list');
        $this->resetValidation();
        $this->resetPermissionState();

        $user = collect($this->users)->first(
            fn (array $item): bool => (int) $item['id'] === $userId,
        );

        $this->permissionsUserId = $userId;
        $this->permissionsUserName = is_array($user)
            ? (string) ($user['nombre'] ?: $user['name'])
            : "#{$userId}";

        try {
            $this->applyPermissionPage(
                $this->permissionApi()->listForUser($userId),
            );
            if ($this->permissions()->allowsAll(self::PERMISSION_CREATE_ABILITIES)) {
            }
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert(
                'No fue posible cargar los permisos',
                $exception->getMessage(),
            );

            return;
        }

        $this->showPermissionsModal = true;
    }

    public function goToPermissionPage(int $page): void
    {
        $this->authorizeAbility('permissions.list');

        if ($page < 1 || $page > $this->permissionLastPage || $page === $this->permissionPage) {
            return;
        }

        $this->loadPermissionsPage($page);
    }

    public function previousPermissionPage(): void
    {
        $this->goToPermissionPage($this->permissionPage - 1);
    }

    public function nextPermissionPage(): void
    {
        $this->goToPermissionPage($this->permissionPage + 1);
    }

    public function closePermissions(): void
    {
        $this->showPermissionsModal = false;
        $this->showPermissionDeleteModal = false;
        $this->resetValidation();
        $this->resetPermissionState();
    }

    public function updatedPermissionModule(string $moduleId): void
    {
        $this->authorizeAbility('permissions.views');
        $this->permissionView = $this->permissionAction = '';
        $this->permissionViews = $this->permissionActions = [];
    }

    public function updatedPermissionView(string $viewId): void
    {
        $this->authorizeAbility('permissions.actions');
        $this->permissionAction = '';
        $this->permissionActions = [];
    }

    public function savePermission(): void
    {
        $this->authorizeAbilities(self::PERMISSION_CREATE_ABILITIES);

        if ($this->permissionsUserId === null) {
            return;
        }

        $validated = $this->validate([
            'permissionModule' => ['required'],
            'permissionView' => ['required'],
            'permissionAction' => ['required'],
            'permissionTimeType' => ['required'],
        ], [
            'permissionModule.required' => 'Selecciona un módulo.',
            'permissionView.required' => 'Selecciona una vista.',
            'permissionAction.required' => 'Selecciona una acción.',
            'permissionTimeType.required' => 'Selecciona un tipo de tiempo.',
        ]);

        try {
            $message = $this->permissionApi()->create([
                'usuario' => $this->permissionsUserId,
                'modulo' => $this->castIdentifier($validated['permissionModule']),
                'vista' => $this->castIdentifier($validated['permissionView']),
                'actionvista' => $this->castIdentifier($validated['permissionAction']),
                'tipo_tiempo' => $this->castIdentifier($validated['permissionTimeType']),
            ]);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert(
                'No fue posible crear el permiso',
                $exception->getMessage(),
            );

            return;
        }

        $this->dispatchToast($message !== '' ? $message : 'Permiso creado correctamente.');
        $this->loadPermissionsPage(1);
    }

    public function confirmPermissionDelete(int $permissionId): void
    {
        $this->authorizeAbility('permissions.delete');

        $permission = collect($this->permissions)->first(
            fn (array $item): bool => (int) $item['id'] === $permissionId,
        );

        $this->deletingPermissionId = $permissionId;
        $this->deletingPermissionName = is_array($permission)
            ? implode(' / ', [
                $permission['module_label'],
                $permission['view_label'],
                $permission['action_label'],
            ])
            : "#{$permissionId}";
        $this->showPermissionDeleteModal = true;
    }

    public function cancelPermissionDelete(): void
    {
        $this->showPermissionDeleteModal = false;
        $this->deletingPermissionId = null;
        $this->deletingPermissionName = '';
    }

    public function deletePermission(): void
    {
        $this->authorizeAbility('permissions.delete');

        if ($this->deletingPermissionId === null) {
            return;
        }

        try {
            $message = $this->permissionApi()->delete($this->deletingPermissionId);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->showPermissionDeleteModal = false;
            $this->dispatchAlert(
                'No fue posible eliminar el permiso',
                $exception->getMessage(),
            );

            return;
        }

        $this->showPermissionDeleteModal = false;
        $this->deletingPermissionId = null;
        $this->deletingPermissionName = '';
        $this->dispatchToast($message !== '' ? $message : 'Permiso eliminado correctamente.');
        $targetPage = count($this->permissions) === 1 && $this->permissionPage > 1
            ? $this->permissionPage - 1
            : $this->permissionPage;
        $this->loadPermissionsPage($targetPage);
    }

    public function save(): void
    {
        $this->authorizeAbility(
            $this->editingId === null ? 'users.create' : 'users.update',
        );
        $this->resetErrorBag('form');

        $validated = $this->validate($this->rules(), $this->messages());
        $payload = [
            'nombre' => $validated['nombre'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'rol' => $this->castIdentifier($validated['rol']),
            'estado' => $this->castIdentifier($validated['estado']),
        ];

        if (filled($validated['password'] ?? null)) {
            $payload['password'] = $validated['password'];
            $payload['password_confirmation'] = $validated['passwordConfirmation'] ?? '';
        }

        try {
            if ($this->editingId === null) {
                $message = $this->api()->create($payload);
            } else {
                $message = $this->api()->update([
                    'id' => $this->editingId,
                    ...$payload,
                ]);
            }
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->applyApiErrors($exception);

            return;
        }

        $this->showFormModal = false;
        $message = $message !== ''
            ? $message
            : ($this->editingId === null ? 'Usuario creado correctamente.' : 'Usuario actualizado correctamente.');
        $this->dispatchToast($message);
        $this->resetForm();
        $this->reloadUsers();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('users.delete');

        $user = collect($this->users)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );

        $this->deletingId = $id;
        $this->deletingName = is_array($user)
            ? (string) ($user['nombre'] ?: $user['name'])
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
        $this->authorizeAbility('users.delete');

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
            $this->dispatchAlert(
                'No fue posible eliminar el usuario',
                $exception->getMessage(),
            );

            return;
        }

        $this->showDeleteModal = false;
        $this->dispatchToast($message !== '' ? $message : 'Usuario eliminado correctamente.');
        $this->deletingId = null;
        $this->deletingName = '';
        $this->reloadUsers();
    }

    public function render(): View
    {
        $permissions = $this->permissions();
        $needle = Str::lower(trim($this->search));
        $visibleUsers = $needle === ''
            ? $this->users
            : array_values(array_filter(
                $this->users,
                fn (array $user): bool => Str::contains(
                    Str::lower(implode(' ', [
                        $user['nombre'],
                        $user['name'],
                        $user['email'],
                        $user['role_label'],
                        $user['state_label'],
                    ])),
                    $needle,
                ),
            ));

        return view('livewire.config.users', [
            'visibleUsers' => $visibleUsers,
            'access' => [
                'createUsers' => $permissions->allowsAll(self::USER_CREATE_ABILITIES),
                'editUsers' => $permissions->allowsAll(self::USER_UPDATE_ABILITIES),
                'deleteUsers' => $permissions->allows('users.delete'),
                'manageWorkHours' => $permissions->allowsAll(self::WORK_HOURS_MANAGE_ABILITIES),
                'managePermissions' => $permissions->allows('permissions.list'),
                'createPermissions' => $permissions->allowsAll(self::PERMISSION_CREATE_ABILITIES),
                'deletePermissions' => $permissions->allows('permissions.delete'),
            ],
        ]);
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    private function rules(): array
    {
        $profileRequirement = $this->editingId === null ? 'required' : 'nullable';

        return [
            'nombre' => [$profileRequirement, 'string', 'max:150'],
            'name' => [$profileRequirement, 'string', 'max:25'],
            'email' => [$profileRequirement, 'email:rfc', 'max:255'],
            'rol' => ['required'],
            'estado' => ['required'],
            'password' => [
                $this->editingId === null ? 'required' : 'nullable',
                'string',
                'min:8',
                'max:50',
                'same:passwordConfirmation',
            ],
            'passwordConfirmation' => [
                $this->editingId === null ? 'required' : 'nullable',
                'string',
                'max:50',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'nombre.required' => 'El nombre completo es obligatorio.',
            'name.required' => 'El usuario es obligatorio.',
            'name.max' => 'El usuario no puede exceder 25 caracteres.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingresa un correo válido.',
            'rol.required' => 'Selecciona un rol.',
            'estado.required' => 'Selecciona un estado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede exceder 50 caracteres.',
            'password.same' => 'Las contraseñas no coinciden.',
            'passwordConfirmation.required' => 'Confirma la contraseña.',
        ];
    }

    private function reloadUsers(): void
    {
        $this->authorizeAbility('users.list');

        try {
            $this->users = $this->normalizeUsers($this->api()->list());
            $this->loadError = null;
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert(
                'No fue posible actualizar el listado',
                $exception->getMessage(),
            );
        }
    }

    private function loadUserCatalogs(): bool
    {
        return true;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->nombre = '';
        $this->name = '';
        $this->email = '';
        $this->rol = '';
        $this->estado = '';
        $this->password = '';
        $this->passwordConfirmation = '';
    }

    private function resetWorkHours(): void
    {
        $this->workHoursUserId = null;
        $this->workHoursUserName = '';
        $this->workHoursId = null;
        $this->workHours = '';
        $this->lunchHours = '';
    }

    private function loadPermissionsPage(int $page): void
    {
        $this->authorizeAbility('permissions.list');

        if ($this->permissionsUserId === null) {
            return;
        }

        try {
            $this->applyPermissionPage(
                $this->permissionApi()->listForUser($this->permissionsUserId, $page),
            );
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert(
                'No fue posible actualizar los permisos',
                $exception->getMessage(),
            );
        }
    }

    private function resetPermissionSelection(): void
    {
        $this->permissionModule = '';
        $this->permissionView = '';
        $this->permissionAction = '';
        $this->permissionTimeType = '';
        $this->permissionViews = [];
        $this->permissionActions = [];
        $this->resetValidation([
            'permissionModule',
            'permissionView',
            'permissionAction',
            'permissionTimeType',
        ]);
    }

    private function resetPermissionState(): void
    {
        $this->permissionsUserId = null;
        $this->permissionsUserName = '';
        $this->permissions = [];
        $this->permissionTotal = 0;
        $this->permissionPage = 1;
        $this->permissionLastPage = 1;
        $this->permissionModules = [];
        $this->permissionTimeTypes = [];
        $this->deletingPermissionId = null;
        $this->deletingPermissionName = '';
        $this->resetPermissionSelection();
    }

    private function applyPermissionPage(PaginatedCollectionData $page): void
    {
        $this->permissions = $this->normalizePermissions($page->items);
        $this->permissionTotal = $page->total;
        $this->permissionPage = $page->currentPage;
        $this->permissionLastPage = $page->lastPage;
    }

    private function applyApiErrors(ApiOperationException $exception): void
    {
        foreach ($exception->errors as $field => $messages) {
            foreach ((array) $messages as $message) {
                $this->addError($field, (string) $message);
            }
        }

        if ($exception->errors === []) {
            $this->dispatchAlert(
                'No fue posible guardar el usuario',
                $exception->getMessage(),
            );
        }
    }

    private function applyWorkHoursApiErrors(ApiOperationException $exception): void
    {
        $fieldMap = [
            'horas_lab' => 'workHours',
            'horas_lunch' => 'lunchHours',
        ];

        foreach ($exception->errors as $field => $messages) {
            $target = $fieldMap[$field] ?? $field;

            foreach ((array) $messages as $message) {
                $this->addError($target, (string) $message);
            }
        }

        if ($exception->errors === []) {
            $this->dispatchAlert(
                'No fue posible guardar el horario',
                $exception->getMessage(),
            );
        }
    }

    private function api(): UserApiService
    {
        return app(UserApiService::class);
    }

    private function permissionApi(): PermissionApiService
    {
        return app(PermissionApiService::class);
    }

    private function workHoursApi(): WorkHoursApiService
    {
        return app(WorkHoursApiService::class);
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

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @return array<int, array<string, mixed>>
     */
    private function normalizeUsers(array $users): array
    {
        return collect($users)
            ->map(fn (array $user): array => $this->normalizeUser($user))
            ->filter(fn (array $user): bool => $user['id'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $permissions
     * @return array<int, array<string, mixed>>
     */
    private function normalizePermissions(array $permissions): array
    {
        return collect($permissions)
            ->map(fn (array $permission): array => [
                'id' => (int) ($permission['id'] ?? 0),
                'module_label' => $this->description($permission['modulo'] ?? null),
                'view_label' => $this->description($permission['vista'] ?? null),
                'action_label' => $this->description($permission['actionvista'] ?? null),
                'time_label' => $this->description($permission['tipo_tiempo'] ?? null),
            ])
            ->filter(fn (array $permission): bool => $permission['id'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    private function normalizeUser(array $user): array
    {
        $role = $user['rol'] ?? $user['role'] ?? $user['role_id'] ?? null;
        $state = $user['estado'] ?? $user['state'] ?? $user['status_id'] ?? null;
        $roleId = $this->identifier($role);
        $stateId = $this->identifier($state);

        return [
            'id' => (int) ($user['id'] ?? 0),
            'nombre' => (string) ($user['nombre'] ?? $user['full_name'] ?? ''),
            'name' => (string) ($user['name'] ?? $user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'rol' => $roleId,
            'estado' => $stateId,
            'role_label' => $this->description($role)
                ?: (string) ($user['rol_descripcion'] ?? $user['role_name'] ?? $this->catalogLabel($this->roles, $roleId)),
            'state_label' => $this->description($state)
                ?: (string) ($user['estado_descripcion'] ?? $user['state_name'] ?? $this->catalogLabel($this->states, $stateId)),
        ];
    }

    private function identifier(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['id'] ?? $value['value'] ?? '';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function description(mixed $value): string
    {
        if (! is_array($value)) {
            return '';
        }

        $description = $value['descripcion']
            ?? $value['description']
            ?? $value['nombre']
            ?? $value['name']
            ?? $value['label']
            ?? '';

        return is_scalar($description) ? (string) $description : '';
    }

    /**
     * @param  array<int, array{id: string, label: string}>  $catalog
     */
    private function catalogLabel(array $catalog, string $id): string
    {
        foreach ($catalog as $item) {
            if ($item['id'] === $id) {
                return $item['label'];
            }
        }

        return $id;
    }

    private function castIdentifier(string $value): int|string
    {
        return ctype_digit($value) ? (int) $value : $value;
    }

    private function numericValue(mixed $value): string
    {
        return is_scalar($value) && is_numeric($value)
            ? (string) $value
            : '';
    }
}
