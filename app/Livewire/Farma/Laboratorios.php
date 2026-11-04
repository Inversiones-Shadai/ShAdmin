<?php

namespace App\Livewire\Farma;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\LaboratoryApiService;
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

class Laboratorios extends Component
{
    use WithFileUploads;

    private const IMAGE_MAX_KILOBYTES = 2048;

    private const array UPDATE_ABILITIES = ['laboratories.search', 'laboratories.update'];

    /** @var array<int, array<string, mixed>> */
    public array $laboratories = [];

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
        $this->authorizeAbility('laboratories.create');
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
            $laboratory = $this->normalizeLaboratory($this->api()->find($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible cargar el laboratorio', $exception->getMessage());

            return;
        }

        $this->editingId = $id;
        $this->nombre = $laboratory['nombre'];
        $this->telefono = $laboratory['telefono'];
        $this->direccion = $laboratory['direccion'];

        if ($laboratory['tiene_imagen'] && $this->permissions()->allows('laboratories.image')) {
            $this->loadExistingImage($id, $laboratory['imagen_url']);
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

    public function save(): void
    {
        $this->authorizeAbility(
            $this->editingId === null ? 'laboratories.create' : 'laboratories.update',
        );

        $validated = $this->validate([
            'nombre' => ['required', 'string', 'max:180'],
            'telefono' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+() .-]*$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'imagenArchivo' => $this->imageRules(),
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 180 caracteres.',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres.',
            'telefono.regex' => 'El teléfono contiene caracteres no permitidos.',
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
            'telefono' => $this->sanitizeText((string) ($validated['telefono'] ?? ''), 20),
            'direccion' => $this->sanitizeText((string) ($validated['direccion'] ?? ''), 255),
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
            : ($isCreating ? 'Laboratorio creado correctamente.' : 'Laboratorio actualizado correctamente.'));

        if (! $isCreating && $imageChanged && $updatedId !== null) {
            $this->dispatch('image-viewer:invalidate', key: "laboratories:{$updatedId}");
        }

        $this->resetForm();
        $this->loadPage($isCreating ? 1 : $this->page);
    }

    public function closeForm(): void
    {
        $this->showFormModal = false;
        $this->resetValidation();
        $this->resetForm();
    }

    public function viewImage(int $id): void
    {
        $this->authorizeAbility('laboratories.image');

        try {
            $src = app(ImageSource::class)->forRecord(collect($this->laboratories)->firstWhere('id', $id), fn () => $this->api()->image($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException|InvalidArgumentException $exception) {
            $this->dispatch('image-viewer:error', key: "laboratories:{$id}");
            $this->dispatchAlert('No fue posible cargar la imagen', $exception->getMessage());

            return;
        }

        $laboratory = collect($this->laboratories)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );
        $name = is_array($laboratory) ? $laboratory['nombre'] : "Laboratorio #{$id}";
        $this->dispatch(
            'image-viewer:show',
            key: "laboratories:{$id}",
            name: $name,
            src: $src,
        );
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('laboratories.delete');
        $laboratory = collect($this->laboratories)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );
        $this->deletingId = $id;
        $this->deletingName = is_array($laboratory) ? $laboratory['nombre'] : "#{$id}";
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
        $this->authorizeAbility('laboratories.delete');

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
            $this->dispatchAlert('No fue posible eliminar el laboratorio', $exception->getMessage());

            return;
        }

        $deletedId = $this->deletingId;
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->dispatchToast($message !== '' ? $message : 'Laboratorio eliminado correctamente.');
        $this->dispatch('image-viewer:invalidate', key: "laboratories:{$deletedId}");
        $this->loadPage(count($this->laboratories) === 1 && $this->page > 1 ? $this->page - 1 : $this->page);
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
        $visibleLaboratories = $this->laboratories;

        return view('livewire.farma.laboratorios', [
            'visibleLaboratories' => $visibleLaboratories,
            'access' => [
                'createLaboratories' => $permissions->allows('laboratories.create'),
                'viewLaboratoryImages' => $permissions->allows('laboratories.image'),
                'editLaboratories' => $permissions->allowsAll(self::UPDATE_ABILITIES),
                'deleteLaboratories' => $permissions->allows('laboratories.delete'),
            ],
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('laboratories.list');
        $this->loadError = null;

        try {
            $this->applyPage($this->api()->list($page, $this->search));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
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
        $this->laboratories = collect($page->items)
            ->map($this->normalizeLaboratory(...))
            ->filter(fn (array $laboratory): bool => $laboratory['id'] > 0)
            ->values()
            ->all();
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    /** @param array<string, mixed> $laboratory
     * @return array{id: int, nombre: string, telefono: string, direccion: string, tiene_imagen: bool, imagen_url: ?string, imagen_uuid: ?string}
     */
    private function normalizeLaboratory(array $laboratory): array
    {
        return [
            'id' => (int) ($laboratory['id'] ?? 0),
            'nombre' => (string) ($laboratory['nombre'] ?? ''),
            'telefono' => (string) ($laboratory['telefono'] ?? ''),
            'direccion' => (string) ($laboratory['direccion'] ?? ''),
            'tiene_imagen' => (bool) ($laboratory['tiene_imagen'] ?? false),
            'imagen_url' => is_string($laboratory['imagen_url'] ?? null) ? $laboratory['imagen_url'] : null,
            'imagen_uuid' => is_string($laboratory['imagen_uuid'] ?? null) ? $laboratory['imagen_uuid'] : null,
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
            $this->dispatchAlert('No fue posible guardar el laboratorio', $exception->getMessage());
        }
    }

    private function api(): LaboratoryApiService
    {
        return app(LaboratoryApiService::class);
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
