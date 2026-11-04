<?php

namespace App\Livewire\Farma;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\ProviderApiService;
use App\Services\Auth\CurrentUserPermissions;
use App\Services\Images\Base64ImageSanitizer;
use App\Services\Images\ImageSource;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;

use function is_array;

class Proveedores extends Component
{
    use WithFileUploads;

    private const IMAGE_MAX_KILOBYTES = 2048;

    private const UPDATE_ABILITIES = ['providers.search', 'providers.update'];

    /** @var array<int, array<string, mixed>> */
    public array $providers = [];

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

    public string $telefono = '';

    public string $direccion = '';

    public mixed $imagenArchivo = null;

    public ?string $imagenBase64 = null;

    public ?string $imagenMime = null;

    public ?string $imagenExistente = null;

    public function mount(): void
    {
        $this->loadPage();
    }

    public function openCreate(): void
    {
        $this->authorizeAbility('providers.create');
        $this->resetValidation();
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAbilities(self::UPDATE_ABILITIES);
        $this->resetValidation();
        $this->resetForm();

        try {
            $provider = $this->normalizeProvider($this->api()->find($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible cargar el proveedor', $exception->getMessage());

            return;
        }

        $this->editingId = $id;
        $this->nombre = $provider['nombre'];
        $this->telefono = $provider['telefono'];
        $this->direccion = $provider['direccion'];

        if ($provider['tiene_imagen'] && $this->permissions()->allows('providers.image')) {
            $this->loadExistingImage($id, $provider['imagen_url']);
        }

        $this->showFormModal = true;
    }

    public function updatedImagenArchivo(): void
    {
        $this->resetErrorBag('imagenArchivo');

        $validator = Validator::make(
            ['imagenArchivo' => $this->imagenArchivo],
            ['imagenArchivo' => $this->imageRules()],
            $this->imageMessages(),
        );

        if ($validator->fails()) {
            $this->rejectImage($validator->errors()->first('imagenArchivo'));

            return;
        }

        if ($this->imagenArchivo === null) {
            $this->imagenBase64 = null;
            $this->imagenMime = null;

            return;
        }

        try {
            $image = $this->sanitizer()->sanitizeUploadedFile($this->imagenArchivo);
        } catch (InvalidArgumentException $exception) {
            $this->rejectImage($exception->getMessage());

            return;
        }

        $this->imagenBase64 = $image['base64'];
        $this->imagenMime = $image['mime'];
    }

    public function rejectOversizedImage(): void
    {
        $this->rejectImage('La imagen no puede exceder 2 MB.');
    }

    public function closeForm(): void
    {
        $this->showFormModal = false;
        $this->resetValidation();
        $this->resetForm();
    }

    public function viewImage(int $id): void
    {
        $this->authorizeAbility('providers.image');

        try {
            $src = app(ImageSource::class)->forRecord(collect($this->providers)->firstWhere('id', $id), fn () => $this->api()->image($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException|InvalidArgumentException $exception) {
            $this->dispatch('image-viewer:error', key: "providers:{$id}");
            $this->dispatchAlert('No fue posible cargar la imagen', $exception->getMessage());

            return;
        }

        $provider = collect($this->providers)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );

        $name = is_array($provider) ? $provider['nombre'] : "Proveedor #{$id}";
        $this->dispatch(
            'image-viewer:show',
            key: "providers:{$id}",
            name: $name,
            src: $src,
        );
    }

    public function save(): void
    {
        $ability = $this->editingId === null ? 'providers.create' : 'providers.update';
        $this->authorizeAbility($ability);

        $validated = $this->validate([
            'nombre' => ['required', 'string', 'max:180'],
            'telefono' => ['required', 'string', 'max:20', 'regex:/^[0-9+() .-]+$/'],
            'direccion' => ['required', 'string', 'max:255'],
            'imagenArchivo' => $this->imageRules(),
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 180 caracteres.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres.',
            'telefono.regex' => 'El teléfono contiene caracteres no permitidos.',
            'direccion.required' => 'La dirección es obligatoria.',
            'direccion.max' => 'La dirección no puede exceder 255 caracteres.',
            ...$this->imageMessages(),
        ]);

        if ($this->imagenArchivo !== null && $this->imagenBase64 === null) {
            $this->updatedImagenArchivo();

            if ($this->getErrorBag()->has('imagenArchivo')) {
                return;
            }
        }

        $payload = [
            'nombre' => $this->sanitizeText((string) $validated['nombre'], 180),
            'telefono' => $this->sanitizeText((string) $validated['telefono'], 20),
            'direccion' => $this->sanitizeText((string) $validated['direccion'], 255),
        ];

        if ($this->imagenBase64 !== null) {
            $payload['imagen'] = $this->imagenBase64;
        }

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
        $updatedId = $this->editingId;
        $imageChanged = $this->imagenBase64 !== null;
        $this->showFormModal = false;
        $this->dispatchToast($message !== ''
            ? $message
            : ($isCreating ? 'Proveedor creado correctamente.' : 'Proveedor actualizado correctamente.'));

        if (! $isCreating && $imageChanged && $updatedId !== null) {
            $this->dispatch('image-viewer:invalidate', key: "providers:{$updatedId}");
        }

        $this->resetForm();
        $this->loadPage($isCreating ? 1 : $this->page);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('providers.delete');

        $provider = collect($this->providers)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );

        $this->deletingId = $id;
        $this->deletingName = is_array($provider) ? $provider['nombre'] : "#{$id}";
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
        $this->authorizeAbility('providers.delete');

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
            $this->dispatchAlert('No fue posible eliminar el proveedor', $exception->getMessage());

            return;
        }

        $deletedId = $this->deletingId;
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->dispatchToast($message !== '' ? $message : 'Proveedor eliminado correctamente.');
        $this->dispatch('image-viewer:invalidate', key: "providers:{$deletedId}");
        $this->loadPage(count($this->providers) === 1 && $this->page > 1 ? $this->page - 1 : $this->page);
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
        $visibleProviders = $this->providers;

        return view('livewire.farma.proveedores', [
            'visibleProviders' => $visibleProviders,
            'access' => [
                'createProviders' => $permissions->allows('providers.create'),
                'viewProviderImages' => $permissions->allows('providers.image'),
                'editProviders' => $permissions->allowsAll(self::UPDATE_ABILITIES),
                'deleteProviders' => $permissions->allows('providers.delete'),
            ],
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('providers.list');
        $this->loadError = null;

        try {
            $this->applyPage($this->api()->list($page, $this->search));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            if ($this->providers === []) {
                $this->loadError = $exception->getMessage();
            } else {
                $this->dispatchAlert('No fue posible actualizar el listado', $exception->getMessage());
            }
        }
    }

    private function loadExistingImage(int $id, ?string $url = null): void
    {
        try {
            $this->imagenExistente = app(ImageSource::class)->forRecord(['imagen_url' => $url], fn () => $this->api()->image($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException|InvalidArgumentException $exception) {
            $this->imagenExistente = null;
            $this->dispatchAlert('No fue posible cargar la imagen', $exception->getMessage());
        }
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->providers = collect($page->items)
            ->map(fn (array $provider): array => $this->normalizeProvider($provider))
            ->filter(fn (array $provider): bool => $provider['id'] > 0)
            ->values()
            ->all();
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    /** @param array<string, mixed> $provider
     * @return array{id: int, nombre: string, telefono: string, direccion: string, tiene_imagen: bool, imagen_url: ?string, imagen_uuid: ?string}
     */
    private function normalizeProvider(array $provider): array
    {
        return [
            'id' => (int) ($provider['id'] ?? 0),
            'nombre' => (string) ($provider['nombre'] ?? $provider['name'] ?? ''),
            'telefono' => (string) ($provider['telefono'] ?? $provider['phone'] ?? ''),
            'direccion' => (string) ($provider['direccion'] ?? $provider['address'] ?? ''),
            'tiene_imagen' => (bool) ($provider['tiene_imagen'] ?? $provider['has_image'] ?? false),
            'imagen_url' => is_string($provider['imagen_url'] ?? null) ? $provider['imagen_url'] : null,
            'imagen_uuid' => is_string($provider['imagen_uuid'] ?? null) ? $provider['imagen_uuid'] : null,
        ];
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return mb_substr(trim($value), 0, $maxLength);
    }

    /** @return array<string, string> */
    private function imageMessages(): array
    {
        return [
            'imagenArchivo.image' => 'Selecciona una imagen válida.',
            'imagenArchivo.mimes' => 'La imagen debe estar en formato JPEG o PNG.',
            'imagenArchivo.max' => 'La imagen no puede exceder 2 MB.',
        ];
    }

    /** @return array<int, string> */
    private function imageRules(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::IMAGE_MAX_KILOBYTES];
    }

    private function rejectImage(string $message): void
    {
        $this->imagenArchivo = null;
        $this->imagenBase64 = null;
        $this->imagenMime = null;
        $this->resetErrorBag('imagenArchivo');
        $this->addError('imagenArchivo', $message);
        $this->dispatchAlert('Imagen no válida', $message);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->nombre = '';
        $this->telefono = '';
        $this->direccion = '';
        $this->imagenArchivo = null;
        $this->imagenBase64 = null;
        $this->imagenMime = null;
        $this->imagenExistente = null;
    }

    private function applyApiErrors(ApiOperationException $exception): void
    {
        foreach ($exception->errors as $field => $messages) {
            $field = $field === 'imagen' ? 'imagenArchivo' : $field;

            foreach ((array) $messages as $message) {
                $this->addError($field, (string) $message);
            }
        }

        if ($exception->errors === []) {
            $this->dispatchAlert('No fue posible guardar el proveedor', $exception->getMessage());
        }
    }

    private function api(): ProviderApiService
    {
        return app(ProviderApiService::class);
    }

    private function sanitizer(): Base64ImageSanitizer
    {
        return app(Base64ImageSanitizer::class);
    }

    private function permissions(): CurrentUserPermissions
    {
        return app(CurrentUserPermissions::class);
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }

    /** @param array<int, string> $abilities */
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
        $this->dispatch('sweetalert:alert', icon: 'error', title: $title, text: $message);
    }
}
