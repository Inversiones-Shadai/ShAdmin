<?php

namespace App\Livewire\Config;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\DeviceApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

use function is_array;
use function is_scalar;

class Devices extends Component
{
    use RemembersSelectorChoice;

    private const CREATE_ABILITIES = [
        'devices.create',
        'devices.states',
        'devices.stocks',
    ];

    private const UPDATE_ABILITIES = [
        'devices.search',
        'devices.update',
        'devices.states',
    ];

    /** @var array<int, array<string, mixed>> */
    public array $devices = [];

    /** @var array<int, array{id: string, label: string}> */
    public array $states = [];

    /** @var array<int, array{id: string, label: string}> */
    public array $stocks = [];

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

    public string $ip = '';

    public string $ip2 = '';

    public string $displayname = '';

    public string $deviceName = '';

    public string $stock = '';

    public string $estado = '';

    public string $hqPairingUuid = '';

    public function mount(): void
    {
        $this->loadPage();
    }

    public function openCreate(): void
    {
        $this->authorizeAbilities(self::CREATE_ABILITIES);
        $this->resetValidation();
        $this->resetForm();

        if (! $this->loadStates() || ! $this->loadStocks()) {
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
            $device = $this->normalizeDevice($this->api()->find($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible cargar el dispositivo', $exception->getMessage());

            return;
        }

        $this->editingId = $id;
        $this->displayname = (string) $device['displayname'];
        $this->stock = (string) $device['stock'];
        $this->estado = (string) $device['estado'];
        $this->hqPairingUuid = (string) $device['hq_pairing_uuid'];
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
        $this->authorizeAbility($isCreating ? 'devices.create' : 'devices.update');

        $rules = $isCreating
            ? [
                'ip' => ['required', 'string', 'max:100'],
                'ip2' => ['required', 'string', 'max:100'],
                'displayname' => ['required', 'string', 'max:60'],
                'deviceName' => ['required', 'string', 'max:100'],
                'stock' => ['required'],
                'estado' => ['required'],
            ]
            : [
                'estado' => ['required'],
            ];

        $this->hqPairingUuid = Str::lower(trim($this->hqPairingUuid));
        $rules['hqPairingUuid'] = ['nullable', 'uuid', 'not_in:00000000-0000-0000-0000-000000000000'];

        $validated = $this->validate($rules, [
            'hqPairingUuid.uuid' => 'Pega un UUID válido generado en HQ.',
            'hqPairingUuid.not_in' => 'Genera un UUID en HQ antes de emparejarlo.',
            'ip.required' => 'La IP es obligatoria.',
            'ip.max' => 'La IP no puede exceder 100 caracteres.',
            'ip2.required' => 'La IP secundaria es obligatoria.',
            'ip2.max' => 'La IP secundaria no puede exceder 100 caracteres.',
            'displayname.required' => 'El nombre visible es obligatorio.',
            'displayname.max' => 'El nombre visible no puede exceder 60 caracteres.',
            'deviceName.required' => 'El nombre del equipo es obligatorio.',
            'deviceName.max' => 'El nombre del equipo no puede exceder 100 caracteres.',
            'stock.required' => 'Selecciona un stock.',
            'estado.required' => 'Selecciona un estado.',
        ]);

        try {
            if ($isCreating) {
                $message = $this->api()->create([
                    'ip' => trim($validated['ip']),
                    'ip2' => trim($validated['ip2']),
                    'displayname' => trim($validated['displayname']),
                    'name' => trim($validated['deviceName']),
                    'stock' => $this->castIdentifier($validated['stock']),
                    'estado' => $this->castIdentifier($validated['estado']),
                    'hq_pairing_uuid' => $validated['hqPairingUuid'] ?: null,
                ]);
            } else {
                $message = $this->api()->update([
                    'id' => $this->editingId,
                    'estado' => $this->castIdentifier($validated['estado']),
                    'hq_pairing_uuid' => $validated['hqPairingUuid'] ?: null,
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
        $this->dispatchToast($message !== ''
            ? $message
            : ($isCreating ? 'Dispositivo creado correctamente.' : 'Dispositivo actualizado correctamente.'));
        $this->resetForm();
        $this->loadPage($isCreating ? 1 : $this->page);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('devices.delete');

        $device = collect($this->devices)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );

        $this->deletingId = $id;
        $this->deletingName = is_array($device)
            ? (string) $device['displayname']
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
        $this->authorizeAbility('devices.delete');

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
            $this->dispatchAlert('No fue posible eliminar el dispositivo', $exception->getMessage());

            return;
        }

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->dispatchToast($message !== '' ? $message : 'Dispositivo eliminado correctamente.');
        $targetPage = count($this->devices) === 1 && $this->page > 1
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
        $visibleDevices = $this->devices;

        return view('livewire.config.devices', [
            'visibleDevices' => $visibleDevices,
            'access' => [
                'createDevices' => $permissions->allowsAll(self::CREATE_ABILITIES),
                'editDevices' => $permissions->allowsAll(self::UPDATE_ABILITIES),
                'deleteDevices' => $permissions->allows('devices.delete'),
            ],
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('devices.list');
        $this->loadError = null;

        try {
            $this->applyPage($this->api()->list($page, $this->search));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            if ($this->devices === []) {
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

    private function loadStocks(): bool
    {
        return true;
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->devices = collect($page->items)
            ->map(fn (array $device): array => $this->normalizeDevice($device))
            ->filter(fn (array $device): bool => $device['id'] > 0)
            ->values()
            ->all();
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    /**
     * @param  array<string, mixed>  $device
     * @return array<string, mixed>
     */
    private function normalizeDevice(array $device): array
    {
        $stock = $device['stock'] ?? null;
        $state = $device['estado'] ?? $device['state'] ?? null;

        return [
            'id' => (int) ($device['id'] ?? 0),
            'displayname' => (string) ($device['displayname'] ?? $device['display_name'] ?? ''),
            'hq_pairing_uuid' => (string) ($device['hq_pairing_uuid'] ?? ''),
            'stock' => (string) $this->identifier($stock),
            'stock_label' => $this->description($stock),
            'estado' => (string) $this->identifier($state),
            'state_label' => $this->description($state),
            'created_at' => (string) ($device['created_at'] ?? ''),
            'updated_at' => (string) ($device['updated_at'] ?? ''),
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
        $this->ip = '';
        $this->ip2 = '';
        $this->displayname = '';
        $this->deviceName = '';
        $this->stock = '';
        $this->estado = '';
        $this->hqPairingUuid = '';
    }

    private function applyApiErrors(ApiOperationException $exception): void
    {
        foreach ($exception->errors as $field => $messages) {
            $field = match ($field) {
                'estado.id' => 'estado',
                'stock.id' => 'stock',
                'name' => 'deviceName',
                'hq_pairing_uuid' => 'hqPairingUuid',
                default => $field,
            };

            foreach ((array) $messages as $message) {
                $this->addError($field, (string) $message);
            }
        }

        if ($exception->errors === []) {
            $this->dispatchAlert('No fue posible guardar el dispositivo', $exception->getMessage());
        }
    }

    private function api(): DeviceApiService
    {
        return app(DeviceApiService::class);
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
