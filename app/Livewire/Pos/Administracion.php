<?php

namespace App\Livewire\Pos;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\PosManagementApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Administracion extends Component
{
    use RemembersSelectorChoice;

    private const PERMISSIONS_PER_PAGE = 10;

    #[Locked]
    public string $resource = 'usuarios';

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $records = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $roles = [];

    public int $nivel = 0;

    #[Locked]
    public ?int $permissionUserId = null;

    #[Locked]
    public string $permissionUserName = '';

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $userPermissions = [];

    #[Locked]
    public int $permissionPage = 1;

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $permissionModules = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $permissionViews = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $permissionActions = [];

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $permissionTimes = [];

    public string $permissionModule = '';

    public string $permissionView = '';

    public string $permissionAction = '';

    public string $permissionTime = '';

    public bool $showPermissionsModal = false;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $deactivatingId = null;

    #[Locked]
    public ?string $pendingId = null;

    #[Locked]
    public ?string $pendingDigest = null;

    public string $nombre = '';

    public string $vendedor = '';

    public string $usuario = '';

    public string $rolId = '';

    public string $deactivatingName = '';

    public bool $activo = true;

    public bool $showFormModal = false;

    public bool $showDeactivateModal = false;

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public ?string $loadError = null;

    public function mount(string $resource = 'usuarios'): void
    {
        abort_unless(in_array($resource, ['usuarios', 'roles'], true), 404);
        $this->resource = $resource;
        $this->loadPage();
    }

    public function openCreate(): void
    {
        $this->authorizeAbility('create');
        if (! $this->canStart()) {
            return;
        }
        $this->resetForm();
        $this->showFormModal = true;
        $this->loadChoices();
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAbility('search');
        $this->authorizeAbility('update');
        if (! $this->canStart()) {
            return;
        }
        $this->resetForm();
        try {
            $record = $this->api()->find($this->resource, $id);
            $this->editingId = $id;
            $this->nombre = (string) ($record['nombre'] ?? '');
            $this->vendedor = (string) ($record['vendedor'] ?? '');
            $this->activo = (bool) ($record['activo'] ?? false);
            $this->rolId = (string) ($record['rol_id'] ?? '');
            $this->nivel = (int) ($record['nivel'] ?? 0);
            $this->showFormModal = true;
            $this->loadChoices();
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->reportError($exception);
        }
    }

    // Passwords are action arguments only: never public properties or session state.
    public function save(string $password = '', string $confirmation = ''): void
    {
        $this->authorizeAbility($this->editingId === null ? 'create' : 'update');
        if ($this->editingId !== null) {
            $this->authorizeAbility('search');
        }
        $this->resetValidation();
        $this->nombre = trim(strip_tags($this->nombre));
        $rules = ['nombre' => ['required', 'string', 'max:'.($this->resource === 'usuarios' ? 200 : 80)], 'activo' => ['boolean']];
        if ($this->resource === 'roles') {
            $rules += ['nivel' => ['required', 'integer', 'min:0', 'max:1000']];
        } else {
            Gate::authorize(CurrentUserPermissions::GATE, 'rolespos.list');
            $this->vendedor = trim(strip_tags($this->vendedor));
            $this->usuario = strtolower(trim($this->usuario));
            $rules += ['vendedor' => ['required', 'string', 'max:200'], 'rolId' => ['required', 'integer', Rule::in(array_column($this->roles, 'id'))],
                'usuario' => [$this->editingId === null ? 'required' : 'nullable', 'string', 'max:200', 'regex:/\A[a-zA-Z0-9._@-]+\z/']];
        }
        $validated = $this->validate($rules);
        $payload = ['nombre' => $validated['nombre'], 'activo' => $validated['activo']];
        if ($this->resource === 'roles') {
            $payload['nivel'] = $validated['nivel'];
        } else {
            $payload += ['rol_id' => (int) $this->rolId, 'vendedor' => $validated['vendedor']];
            if ($this->usuario !== '') {
                $payload['usuario'] = $this->usuario;
            }
            if ($this->editingId === null || $password !== '' || $confirmation !== '') {
                $check = Validator::make(['password' => $password, 'password_confirmation' => $confirmation],
                    ['password' => ['required', 'string', 'min:6', 'max:1024', 'confirmed']],
                    ['password.min' => 'Usa al menos 6 caracteres.', 'password.confirmed' => 'Las contraseñas no coinciden.', 'password.required' => 'La contraseña es obligatoria.']);
                if ($check->fails()) {
                    foreach ($check->errors()->messages() as $field => $messages) {
                        $this->addError($field, $messages[0]);
                    }

                    return;
                }
                $payload['password'] = $password;
            }
        }
        try {
            $requestId = $this->requestId('save:'.$this->resource.':'.($this->editingId ?? 'new'), $payload);
            $message = $this->api()->save($this->resource, $this->editingId, $payload, $requestId);
            $this->clearPending();
            $this->closeForm();
            $this->dispatch('sweetalert:toast', icon: 'success', title: $message);
            $this->loadPage($this->page);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->reportError($exception, writing: true);
        } finally {
            unset($payload['password'], $password, $confirmation);
        }
    }

    public function closeForm(): void
    {
        if (! $this->canStart()) {
            return;
        }
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function updatedShowFormModal(): void
    {
        if (! $this->showFormModal) {
            $this->showFormModal = ! $this->canStart();
            if (! $this->showFormModal) {
                $this->resetForm();
            }
        }
    }

    public function confirmDeactivate(int $id): void
    {
        $this->authorizeAbility('deactivate');
        if (! $this->canStart()) {
            return;
        }
        $record = collect($this->records)->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === $id);
        abort_unless(is_array($record), 404);
        $this->deactivatingId = $id;
        $this->deactivatingName = (string) ($record['nombre'] ?? '#'.$id);
        $this->resetValidation();
        $this->showDeactivateModal = true;
    }

    public function deactivate(): void
    {
        $this->authorizeAbility('deactivate');
        if ($this->deactivatingId === null) {
            return;
        }
        try {
            $message = $this->api()->deactivate($this->resource, $this->deactivatingId, $this->requestId('deactivate:'.$this->resource.':'.$this->deactivatingId, []));
            $this->clearPending();
            $this->cancelDeactivate();
            $this->dispatch('sweetalert:toast', icon: 'success', title: $message);
            $this->loadPage($this->page);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->reportError($exception, writing: true);
        }
    }

    public function cancelDeactivate(): void
    {
        if ($this->canStart()) {
            $this->showDeactivateModal = false;
            $this->deactivatingId = null;
            $this->deactivatingName = '';
            $this->resetValidation();
        }
    }

    public function updatedShowDeactivateModal(): void
    {
        if (! $this->showDeactivateModal && ! $this->canStart()) {
            $this->showDeactivateModal = true;
        }
    }

    public function openPermissions(int $id): void
    {
        abort_unless($this->resource === 'usuarios', 403);
        $this->authorizeAbility('search');
        if (! $this->canStart()) {
            return;
        }
        $this->resetValidation();
        $this->permissionModule = $this->permissionView = $this->permissionAction = $this->permissionTime = '';
        $this->permissionViews = $this->permissionActions = [];
        $this->permissionPage = 1;
        try {
            $user = $this->api()->find('usuarios', $id);
            $this->permissionUserId = $id;
            $this->permissionUserName = (string) $user['nombre'];
            $this->userPermissions = $this->api()->userPermissions($id);
            $this->showPermissionsModal = true;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->reportError($exception);
        }
    }

    public function updatedPermissionModule(): void
    {
        $this->authorizeAbility('search');
        if (! $this->canStart()) {
            return;
        }
        $this->permissionView = $this->permissionAction = '';
        $this->permissionViews = $this->permissionActions = [];
    }

    public function updatedPermissionView(): void
    {
        $this->authorizeAbility('search');
        if (! $this->canStart()) {
            return;
        }
        $this->permissionAction = '';
        $this->permissionActions = [];
    }

    public function savePermission(): void
    {
        abort_unless($this->resource === 'usuarios' && $this->permissionUserId !== null, 403);
        $this->authorizeAbility('search');
        $this->authorizeAbility('update');
        $this->resetValidation();
        $this->validate([
            'permissionModule' => ['required', 'integer', Rule::in(array_column($this->permissionModules, 'id'))],
            'permissionView' => ['required', 'integer', Rule::in(array_column($this->permissionViews, 'id'))],
            'permissionAction' => ['required', 'integer', Rule::in(array_column($this->permissionActions, 'id'))],
            'permissionTime' => ['required', 'integer', Rule::in(array_column($this->permissionTimes, 'id'))],
        ]);
        $payload = ['usuario' => ['id' => $this->permissionUserId], 'modulo' => ['id' => (int) $this->permissionModule],
            'vista' => ['id' => (int) $this->permissionView], 'actionvista' => ['id' => (int) $this->permissionAction],
            'tipo_tiempo' => ['id' => (int) $this->permissionTime]];
        try {
            $message = $this->api()->createPermission($payload, $this->requestId('permission:create', $payload));
            $this->clearPending();
            $this->userPermissions = $this->api()->userPermissions($this->permissionUserId);
            $this->dispatch('sweetalert:toast', icon: 'success', title: $message);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->reportError($exception, writing: true);
        }
    }

    public function deletePermission(int $id): void
    {
        abort_unless($this->resource === 'usuarios' && $this->permissionUserId !== null, 403);
        $this->authorizeAbility('search');
        $this->authorizeAbility('update');
        abort_unless(in_array($id, array_column($this->userPermissions, 'id'), true), 404);
        try {
            $message = $this->api()->deletePermission($id, $this->requestId('permission:delete:'.$id, []));
            $this->clearPending();
            $this->userPermissions = $this->api()->userPermissions($this->permissionUserId);
            $this->dispatch('sweetalert:toast', icon: 'success', title: $message);
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->reportError($exception, writing: true);
        }
    }

    public function closePermissions(): void
    {
        if (! $this->canStart()) {
            return;
        }
        $this->showPermissionsModal = false;
        $this->permissionUserId = null;
        $this->permissionUserName = '';
        $this->permissionPage = 1;
        $this->userPermissions = $this->permissionModules = $this->permissionViews = $this->permissionActions = $this->permissionTimes = [];
        $this->permissionModule = $this->permissionView = $this->permissionAction = $this->permissionTime = '';
        $this->resetValidation();
    }

    public function updatedShowPermissionsModal(): void
    {
        if (! $this->showPermissionsModal) {
            if (! $this->canStart()) {
                $this->showPermissionsModal = true;
            } else {
                $this->closePermissions();
            }
        }
    }

    public function goToPage(int $page): void
    {
        if ($page >= 1 && $page <= $this->lastPage) {
            $this->loadPage($page);
        }
    }

    public function goToPermissionsPage(int $page): void
    {
        abort_unless($this->resource === 'usuarios' && $this->permissionUserId !== null, 403);
        $this->authorizeAbility('search');

        if ($page >= 1 && $page <= $this->lastPermissionsPage()) {
            $this->permissionPage = $page;
        }
    }

    public function render(): View
    {
        $permissions = app(CurrentUserPermissions::class);
        $prefix = $this->abilityPrefix();
        $permissionLastPage = $this->lastPermissionsPage();
        $this->permissionPage = min($this->permissionPage, $permissionLastPage);

        return view('livewire.pos.administracion', [
            'paginatedPermissions' => array_slice($this->userPermissions, ($this->permissionPage - 1) * self::PERMISSIONS_PER_PAGE, self::PERMISSIONS_PER_PAGE),
            'permissionLastPage' => $permissionLastPage,
            'access' => [
                'create' => $permissions->allows($prefix.'.create'),
                'edit' => $permissions->allowsAll([$prefix.'.search', $prefix.'.update']),
                'deactivate' => $permissions->allows($prefix.'.deactivate'),
                'permissions' => $this->resource === 'usuarios' && $permissions->allows('usuariospos.search'),
            ],
        ]);
    }

    private function lastPermissionsPage(): int
    {
        return max(1, (int) ceil(count($this->userPermissions) / self::PERMISSIONS_PER_PAGE));
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('list');
        $this->records = [];
        $this->total = 0;
        $this->loadError = null;
        try {
            $data = $this->api()->list($this->resource, $page);
            $this->records = $data->items;
            $this->total = $data->total;
            $this->page = $data->currentPage;
            $this->lastPage = $data->lastPage;
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    private function loadChoices(): void
    {
        if ($this->resource !== 'roles' && ! app(CurrentUserPermissions::class)->allows('rolespos.list')) {
            $this->addError('form', 'Para asignar un usuario necesitas el permiso de listar Roles POS.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function requestId(string $operation, array $payload): string
    {
        $digest = hash_hmac('sha256', $operation.json_encode($payload, JSON_THROW_ON_ERROR), (string) config('app.key'));
        if ($this->pendingId !== null && $this->pendingDigest !== $digest) {
            throw new ApiOperationException('Hay una solicitud sin confirmar. Reintenta los datos originales antes de hacer otra operación.', status: 409);
        }
        $this->pendingDigest = $digest;

        return $this->pendingId ??= (string) Str::uuid();
    }

    private function clearPending(): void
    {
        $this->pendingId = $this->pendingDigest = null;
    }

    private function canStart(): bool
    {
        if ($this->pendingId !== null) {
            $this->addError('form', 'La solicitud sigue sin confirmar. Reintenta la misma operación con los datos originales.');

            return false;
        }

        return true;
    }

    private function reportError(ApiOperationException $exception, bool $writing = false): void
    {
        if ($writing && $exception->status >= 400 && $exception->status < 500 && (! in_array($exception->status, [408, 409], true) || ($exception->status === 409 && $exception->errors !== []))) {
            $this->clearPending();
        }
        foreach ($exception->errors as $field => $messages) {
            $field = match ($field) {
                'rol_id' => 'rolId', 'modulo', 'modulo.id' => 'permissionModule', 'vista', 'vista.id' => 'permissionView',
                'actionvista', 'actionvista.id' => 'permissionAction', 'tipo_tiempo', 'tipo_tiempo.id' => 'permissionTime', default => $field
            };
            foreach ((array) $messages as $message) {
                $this->addError($field, $message);
            }
        }
        if ($exception->errors === []) {
            $this->addError('form', $exception->getMessage());
        }
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->nombre = $this->vendedor = $this->usuario = $this->rolId = '';
        $this->activo = true;
        $this->nivel = 0;
        $this->roles = [];
        $this->dispatch('pos-credentials-clear');
    }

    private function abilityPrefix(): string
    {
        return $this->resource === 'usuarios' ? 'usuariospos' : 'rolespos';
    }

    private function authorizeAbility(string $action): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $this->abilityPrefix().'.'.$action);
    }

    private function api(): PosManagementApiService
    {
        return app(PosManagementApiService::class);
    }
}
