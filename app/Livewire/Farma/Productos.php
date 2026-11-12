<?php

namespace App\Livewire\Farma;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\ProductApiService;
use App\Services\Auth\CurrentUserPermissions;
use App\Services\Images\Base64ImageSanitizer;
use App\Services\Images\ImageSource;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

use function is_array;
use function is_scalar;

class Productos extends Component
{
    use RemembersSelectorChoice;
    use WithFileUploads;

    private const PICKER_PAGE_SIZE = 10;

    private const CREATE_ABILITIES = ['products.create', 'products.options'];

    private const UPDATE_ABILITIES = ['products.search', 'products.update', 'products.options'];

    /** @var array<int, array<string, mixed>> */
    public array $products = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $options = [];

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public string $search = '';

    public ?string $loadError = null;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public bool $showChildModal = false;

    #[Locked]
    public ?int $childParentId = null;

    #[Locked]
    public string $childParentName = '';

    #[Locked]
    public string $childCode = '';

    public string $childDescription = '';

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public string $categoria = '';

    public string $laboratorio = '';

    public string $laboratoryName = '';

    public bool $showLaboratoryPicker = false;

    public string $laboratorySearch = '';

    /** @var array<int, array{id: int, nombre: string}> */
    #[Locked]
    public array $laboratoryOptions = [];

    public int $laboratoryPage = 1;

    public int $laboratoryLastPage = 1;

    public int $laboratoryTotal = 0;

    public ?string $laboratoryLoadError = null;

    public string $unidad = '';

    public string $familia = '';

    #[Locked]
    public string $productoPadreId = '';

    #[Locked]
    public string $parentName = '';

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $parentOptions = [];

    public string $parentSearch = '';

    public bool $showParentPicker = false;

    #[Locked]
    public int $parentPage = 1;

    #[Locked]
    public int $parentLastPage = 1;

    public ?string $parentLoadError = null;

    public string $codigo = '';

    public string $codigobar = '';

    public string $descripcion = '';

    public string $estado = '';

    /** @var array<int, int|string> */
    public array $principiosActivos = [];

    public mixed $imagenArchivo = null;

    public ?string $imagenBase64 = null;

    public ?string $imagenMime = null;

    public ?string $imagenExistente = null;

    public bool $showFamilyPicker = false;

    public string $familySearch = '';

    public int $familyPage = 1;

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $familyOptions = [];

    public int $familyTotal = 0;

    public int $familyLastPage = 1;

    public ?string $familyLoadError = null;

    public bool $familyLoaded = false;

    public bool $showUnitPicker = false;

    public string $unitSearch = '';

    public int $unitPage = 1;

    public bool $showActivePicker = false;

    public string $activeSearch = '';

    public int $activePage = 1;

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $activeOptions = [];

    public int $activeTotal = 0;

    public int $activeLastPage = 1;

    public ?string $activeLoadError = null;

    public bool $activeLoaded = false;

    public int $selectedActivePage = 1;

    public function mount(): void
    {
        $this->loadPage();
    }

    public function openCreate(): void
    {
        $this->authorizeAbilities(self::CREATE_ABILITIES);
        $this->resetValidation();
        $this->resetForm();

        if (! $this->loadOptions()) {
            return;
        }

        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAbilities(self::UPDATE_ABILITIES);
        $this->resetValidation();
        $this->resetForm();

        if (! $this->loadOptions()) {
            return;
        }

        try {
            $record = $this->api()->find($id);
            $product = $this->normalizeProduct($record);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible cargar el producto', $exception->getMessage());

            return;
        }

        $this->editingId = $id;
        $this->categoria = (string) $product['categoria_id'];
        $this->options['categorias'] = is_array($record['categoria'] ?? null) ? [$record['categoria']] : [];
        $this->laboratorio = (string) $product['laboratorio_id'];
        $this->laboratoryName = $product['laboratorio_label'];
        $this->unidad = (string) $product['unidad_id'];
        if ($product['familia_id'] > 0) {
            $this->familia = (string) $product['familia_id'];
            $this->options['familias'] = [[
                'id' => $product['familia_id'],
                'nombre' => $product['familia_label'],
                'label' => $product['familia_label'],
                'presentacion' => $product['familia_presentacion_label'],
                'administracion' => $product['familia_administracion_label'],
            ]];
        }
        $this->codigo = $product['codigo'];
        $this->codigobar = $product['codigobar'];
        $this->productoPadreId = $product['producto_padre_id'];
        $this->parentName = $product['parent_label'];
        $this->descripcion = $product['descripcion'];
        $this->estado = (string) $product['estado_id'];
        $this->principiosActivos = $product['principios_activos_ids'];
        $this->options['principios_activos'] = array_values(array_filter(
            $record['principios_activos'] ?? [], is_array(...),
        ));

        if ($product['tiene_imagen'] && $this->permissions()->allows('products.image')) {
            $this->loadExistingImage($id, $product['imagen_url']);
        }

        $this->showFormModal = true;
    }

    public function updatedImagenArchivo(): void
    {
        $this->resetErrorBag('imagenArchivo');
        $this->validateOnly('imagenArchivo', [
            'imagenArchivo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ], $this->imageMessages());

        if ($this->imagenArchivo === null) {
            $this->imagenBase64 = null;
            $this->imagenMime = null;

            return;
        }

        try {
            $image = $this->sanitizer()->sanitizeUploadedFile($this->imagenArchivo);
            if (strlen($image['base64']) > 1_398_104) {
                throw new InvalidArgumentException('La imagen saneada no puede exceder 1 MB.');
            }
        } catch (InvalidArgumentException $exception) {
            $this->imagenBase64 = null;
            $this->imagenMime = null;
            $this->addError('imagenArchivo', $exception->getMessage());

            return;
        }

        $this->imagenBase64 = $image['base64'];
        $this->imagenMime = $image['mime'];
    }

    public function openChild(int $id): void
    {
        $this->authorizeAbility('products.create-child');
        $this->closeChild();
        $product = collect($this->products)->firstWhere('id', $id);
        if ($product === null) {
            return;
        }
        if ($product['producto_padre_id'] !== '' || Str::endsWith(mb_strtolower(rtrim($product['codigo'])), 'h')) {
            $this->dispatchAlert('El producto ya es hijo', 'Selecciona un producto padre cuyo código no termine en H o h.');

            return;
        }
        if (mb_strlen($product['codigo']) >= 20) {
            $this->dispatchAlert('Código demasiado largo', 'El código del padre no permite añadir H: el máximo es de 20 caracteres.');

            return;
        }
        $this->childParentId = $id;
        $this->childParentName = $product['codigo'].' · '.$product['descripcion'];
        $this->childCode = $product['codigo'].'H';
        $this->childDescription = $product['descripcion'];
        $this->showChildModal = true;
    }

    public function closeChild(): void
    {
        $this->showChildModal = false;
        $this->childParentId = null;
        $this->childParentName = '';
        $this->childCode = '';
        $this->childDescription = '';
        $this->resetValidation('childDescription');
    }

    public function createChild(): void
    {
        $this->authorizeAbility('products.create-child');
        if (! $this->showChildModal || $this->childParentId === null) {
            return;
        }
        $this->childDescription = trim(strip_tags($this->childDescription));
        $this->validate(['childDescription' => ['required', 'string', 'max:150']], [
            'childDescription.required' => 'La descripción del hijo es obligatoria.',
            'childDescription.max' => 'La descripción no debe exceder 150 caracteres.',
        ]);

        try {
            $message = $this->api()->createChild($this->childParentId, $this->childDescription);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $details = collect($exception->errors)->flatten()->implode(' ');
            $this->addError('childDescription', $details !== '' ? $details : $exception->getMessage());

            return;
        }

        $this->closeChild();
        $this->dispatchToast($message !== '' ? $message : 'Producto hijo creado correctamente.');
        $this->loadPage(1);
    }

    public function save(): void
    {
        $this->authorizeAbility($this->editingId === null ? 'products.create' : 'products.update');

        $validated = $this->validate([
            'productoPadreId' => ['nullable', 'regex:/^[1-9][0-9]{0,19}$/'],
            'categoria' => ['required', 'integer', 'min:1'],
            'laboratorio' => ['required', 'integer', 'min:1'],
            'unidad' => ['required', 'integer', 'min:1'],
            'familia' => [Rule::requiredIf($this->familyRequired()), 'nullable', 'integer', 'min:1'],
            'codigo' => ['required', 'string', 'max:20'],
            'codigobar' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['required', 'string', 'max:150'],
            'estado' => ['required', 'integer', 'min:1'],
            'principiosActivos' => ['array'],
            'principiosActivos.*' => ['integer', 'distinct', 'min:1'],
            'imagenArchivo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ], [
            '*.required' => 'El campo :attribute es obligatorio.',
            '*.integer' => 'Selecciona una opción válida para :attribute.',
            '*.min' => 'Selecciona una opción válida para :attribute.',
            '*.max' => 'El campo :attribute excede la longitud permitida.',
            'principiosActivos.*.distinct' => 'No puedes repetir un principio activo.',
            ...$this->imageMessages(),
        ], [
            'categoria' => 'categoría',
            'laboratorio' => 'laboratorio',
            'unidad' => 'unidad',
            'familia' => 'familia',
            'codigo' => 'código',
            'codigobar' => 'código de barras',
            'descripcion' => 'descripción',
            'estado' => 'estado',
            'principiosActivos' => 'principios activos',
        ]);

        if ($this->imagenArchivo !== null && $this->imagenBase64 === null) {
            $this->updatedImagenArchivo();

            if ($this->getErrorBag()->has('imagenArchivo')) {
                return;
            }
        }

        $payload = [
            'producto_padre_id' => $this->productoPadreId === '' ? null : $this->productoPadreId,
            'categoria' => (int) $validated['categoria'],
            'laboratorio' => (int) $validated['laboratorio'],
            'unidad' => (int) $validated['unidad'],
            'familia' => filled($validated['familia'] ?? null) ? (int) $validated['familia'] : null,
            'codigo' => $this->sanitizeText((string) $validated['codigo'], 20),
            'codigobar' => $this->nullableText((string) ($validated['codigobar'] ?? ''), 50),
            'descripcion' => $this->sanitizeText((string) $validated['descripcion'], 150),
            'estado' => (int) $validated['estado'],
            'principios_activos' => array_values(array_unique(array_map(
                static fn (mixed $id): int => (int) $id,
                $validated['principiosActivos'] ?? [],
            ))),
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
            : ($isCreating ? 'Producto creado correctamente.' : 'Producto actualizado correctamente.'));

        if (! $isCreating && $imageChanged && $updatedId !== null) {
            $this->dispatch('image-viewer:invalidate', key: "products:{$updatedId}");
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

    public function openParentPicker(): void
    {
        $this->authorizeAbility('products.options');
        if (! $this->showFormModal) {
            return;
        }
        $this->parentSearch = '';
        $this->showParentPicker = true;
        $this->loadParentPage();
    }

    public function closeParentPicker(): void
    {
        $this->showParentPicker = false;
        $this->parentOptions = [];
    }

    public function updatedParentSearch(): void
    {
        $this->loadParentPage();
    }

    public function parentPage(int $page): void
    {
        $this->loadParentPage(min(max(1, $page), $this->parentLastPage));
    }

    private function loadParentPage(int $page = 1): void
    {
        $this->authorizeAbility('products.options');
        if (! $this->showFormModal || ! $this->showParentPicker) {
            return;
        }
        $this->parentOptions = [];
        $this->parentLoadError = null;
        if (mb_strlen($this->parentSearch) > 150) {
            $this->parentLoadError = 'La búsqueda admite hasta 150 caracteres.';

            return;
        }
        try {
            $result = $this->api()->parents($this->parentSearch, $page, $this->editingId);
            $this->parentOptions = $result->items;
            $this->parentPage = $result->currentPage;
            $this->parentLastPage = $result->lastPage;
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->parentLoadError = $exception->getMessage();
        }
    }

    public function selectParent(string $id): void
    {
        $this->authorizeAbility('products.options');
        if (! $this->showFormModal || ! $this->showParentPicker || $id === (string) $this->editingId) {
            return;
        }
        foreach ($this->parentOptions as $option) {
            if ((string) ($option['id'] ?? '') === $id && preg_match('/^[1-9][0-9]{0,19}$/', $id)) {
                $this->productoPadreId = $id;
                $this->parentName = ($option['codigo'] ?? '').' · '.($option['descripcion'] ?? '');
                $this->closeParentPicker();

                return;
            }
        }
    }

    public function clearParent(): void
    {
        $this->authorizeAbility('products.options');
        if (! $this->showFormModal) {
            return;
        }
        $this->productoPadreId = '';
        $this->parentName = '';
    }

    public function openLaboratoryPicker(): void
    {
        $this->authorizeAbility('products.options');

        if (! $this->showFormModal) {
            return;
        }

        $this->laboratorySearch = '';
        $this->showLaboratoryPicker = true;
        $this->loadLaboratoryPage();
    }

    public function closeLaboratoryPicker(): void
    {
        $this->showLaboratoryPicker = false;
    }

    public function updatedLaboratorySearch(): void
    {
        $this->loadLaboratoryPage();
    }

    public function previousLaboratoryPage(): void
    {
        if ($this->laboratoryPage > 1) {
            $this->loadLaboratoryPage($this->laboratoryPage - 1);
        }
    }

    public function nextLaboratoryPage(): void
    {
        if ($this->laboratoryPage < $this->laboratoryLastPage) {
            $this->loadLaboratoryPage($this->laboratoryPage + 1);
        }
    }

    public function retryLaboratoryPage(): void
    {
        $this->loadLaboratoryPage($this->laboratoryPage);
    }

    public function selectLaboratory(int $id): void
    {
        $this->authorizeAbility('products.options');

        if (! $this->showFormModal || ! $this->showLaboratoryPicker || $id < 1) {
            return;
        }

        foreach ($this->laboratoryOptions as $option) {
            if ($option['id'] === $id) {
                $this->laboratorio = (string) $id;
                $this->laboratoryName = $option['nombre'];
                $this->resetErrorBag('laboratorio');
                $this->showLaboratoryPicker = false;

                return;
            }
        }
    }

    private function loadLaboratoryPage(int $page = 1): void
    {
        $this->authorizeAbility('products.options');

        if (! $this->showFormModal || ! $this->showLaboratoryPicker) {
            return;
        }

        $this->laboratoryOptions = [];
        $this->laboratoryTotal = 0;
        $this->laboratoryPage = max(1, $page);
        $this->laboratoryLastPage = $this->laboratoryPage;
        $this->laboratoryLoadError = null;

        try {
            $result = $this->api()->filterLaboratories($this->laboratorySearch, $this->laboratoryPage);
            foreach ($result->items as $record) {
                $id = $this->identifier($record);
                if ($id > 0) {
                    $this->laboratoryOptions[] = [
                        'id' => $id,
                        'nombre' => $this->description($record, ['nombre', 'label']) ?: '#'.$id,
                    ];
                }
            }
            $this->laboratoryTotal = $result->total;
            $this->laboratoryPage = $result->currentPage;
            $this->laboratoryLastPage = $result->lastPage;
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->laboratoryLoadError = $exception->getMessage();
        }
    }

    public function openFamilyPicker(): void
    {
        $this->authorizeAbility('products.options');
        if (! $this->showFormModal) {
            return;
        }

        $this->familySearch = '';
        $this->familyPage = 1;
        $this->familyOptions = [];
        $this->familyTotal = 0;
        $this->familyLastPage = 1;
        $this->familyLoadError = null;
        $this->familyLoaded = false;
        $this->showFamilyPicker = true;
    }

    public function closeFamilyPicker(): void
    {
        $this->showFamilyPicker = false;
        $this->familyOptions = [];
    }

    public function clearFamily(): void
    {
        if ($this->showFormModal && ! $this->familyRequired()) {
            $this->familia = '';
            $this->options['familias'] = [];
            $this->resetErrorBag('familia');
        }
    }

    public function selectFamily(int $id): void
    {
        if (! $this->showFormModal || ! $this->showFamilyPicker || $this->familyLoadError !== null) {
            return;
        }

        foreach ($this->familyOptions as $option) {
            if ((int) $option['id'] === $id) {
                $this->familia = (string) $id;
                $this->options['familias'] = [$option];
                $this->resetErrorBag('familia');
                $this->closeFamilyPicker();

                return;
            }
        }
    }

    public function updatedFamilySearch(): void
    {
        $this->loadFamilyPage(1);
    }

    public function previousFamilyPage(): void
    {
        if ($this->familyPage > 1) {
            $this->loadFamilyPage($this->familyPage - 1);
        }
    }

    public function nextFamilyPage(): void
    {
        if ($this->familyPage < $this->familyLastPage) {
            $this->loadFamilyPage($this->familyPage + 1);
        }
    }

    public function retryFamilyPage(): void
    {
        $this->loadFamilyPage($this->familyPage);
    }

    private function loadFamilyPage(int $page): void
    {
        $this->authorizeAbility('products.options');
        if (! $this->showFormModal || ! $this->showFamilyPicker) {
            return;
        }

        $this->familyOptions = [];
        $this->familyLoadError = null;
        $this->familyLoaded = false;

        try {
            $result = $this->api()->familyPickerPage($this->familySearch, $page);
            // A removal in another tab can leave the last page empty.
            if ($result->items === [] && $page > $result->lastPage) {
                $result = $this->api()->familyPickerPage($this->familySearch, $result->lastPage);
            }
            $this->familyOptions = $result->items;
            $this->familyTotal = $result->total;
            $this->familyPage = $result->currentPage;
            $this->familyLastPage = $result->lastPage;
            $this->familyLoaded = true;
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->familyLoadError = $exception->getMessage();
        }
    }

    public function openUnitPicker(): void
    {
        $this->authorizeAbility('products.options');
        if (! $this->showFormModal || ! $this->loadOptions(['unidades'])) {
            return;
        }

        $this->unitSearch = '';
        $this->unitPage = 1;
        $this->showUnitPicker = true;
    }

    public function closeUnitPicker(): void
    {
        $this->showUnitPicker = false;
    }

    public function selectUnit(int $id): void
    {
        if ($this->optionById('unidades', $id) === null) {
            return;
        }

        $this->unidad = (string) $id;
        $this->resetErrorBag('unidad');
        $this->showUnitPicker = false;
    }

    public function updatedUnitSearch(): void
    {
        $this->unitPage = 1;
    }

    public function previousUnitPage(): void
    {
        $this->unitPage = max(1, $this->unitPage - 1);
    }

    public function nextUnitPage(): void
    {
        $lastPage = $this->pickerLastPage('unidades', $this->unitSearch, ['abreviatura_c', 'abreviatura_v', 'label']);
        $this->unitPage = min($lastPage, $this->unitPage + 1);
    }

    public function openActivePicker(): void
    {
        $this->authorizeAbility('products.options');
        if (! $this->showFormModal) {
            return;
        }

        $this->activeSearch = '';
        $this->activePage = 1;
        $this->activeOptions = [];
        $this->activeTotal = 0;
        $this->activeLastPage = 1;
        $this->activeLoadError = null;
        $this->activeLoaded = false;
        $this->showActivePicker = true;
    }

    public function closeActivePicker(): void
    {
        $this->showActivePicker = false;
        $this->activeOptions = [];
        $this->retainSelectedActives();
    }

    public function addActive(int $id): void
    {
        if (! $this->showFormModal || ! $this->showActivePicker || $this->activeLoadError !== null
            || ! collect($this->activeOptions)->contains('id', $id)) {
            return;
        }

        $selected = array_map(static fn (mixed $value): int => (int) $value, $this->principiosActivos);

        if (! in_array($id, $selected, true)) {
            $selected[] = $id;
        }

        $this->principiosActivos = $selected;
        $this->selectedActivePage = max(1, (int) ceil(count($selected) / self::PICKER_PAGE_SIZE));
        $this->resetErrorBag('principiosActivos');
    }

    public function removeActive(int $id): void
    {
        $this->principiosActivos = array_values(array_filter(
            $this->principiosActivos,
            static fn (mixed $value): bool => (int) $value !== $id,
        ));
        $this->selectedActivePage = min(
            $this->selectedActivePage,
            max(1, (int) ceil(count($this->principiosActivos) / self::PICKER_PAGE_SIZE)),
        );
    }

    public function updatedActiveSearch(): void
    {
        $this->loadActivePage(1);
    }

    public function previousActivePage(): void
    {
        if ($this->activePage > 1) {
            $this->loadActivePage($this->activePage - 1);
        }
    }

    public function nextActivePage(): void
    {
        if ($this->activePage < $this->activeLastPage) {
            $this->loadActivePage($this->activePage + 1);
        }
    }

    public function retryActivePage(): void
    {
        $this->loadActivePage($this->activePage);
    }

    private function loadActivePage(int $page): void
    {
        $this->authorizeAbility('products.options');
        if (! $this->showFormModal || ! $this->showActivePicker) {
            return;
        }
        $this->activeOptions = [];
        $this->activeLoadError = null;
        $this->retainSelectedActives();
        try {
            $result = $this->api()->activePickerPage(mb_substr($this->activeSearch, 0, 255), $page);
            $this->activeOptions = $result->items;
            $this->activeTotal = $result->total;
            $this->activePage = $result->currentPage;
            $this->activeLastPage = $result->lastPage;
            $this->activeLoaded = true;
            $this->options['principios_activos'] = collect($this->options['principios_activos'] ?? [])
                ->merge($this->activeOptions)->unique('id')->values()->all();
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->activeLoadError = $exception->getMessage();
        }
    }

    private function retainSelectedActives(): void
    {
        $this->options['principios_activos'] = array_values(array_filter(
            $this->options['principios_activos'] ?? [],
            fn (array $option): bool => in_array((int) $option['id'], array_map('intval', $this->principiosActivos), true),
        ));
    }

    public function previousSelectedActivePage(): void
    {
        $this->selectedActivePage = max(1, $this->selectedActivePage - 1);
    }

    public function nextSelectedActivePage(): void
    {
        $lastPage = max(1, (int) ceil(count($this->principiosActivos) / self::PICKER_PAGE_SIZE));
        $this->selectedActivePage = min($lastPage, $this->selectedActivePage + 1);
    }

    public function viewImage(int $id): void
    {
        $this->authorizeAbility('products.image');

        try {
            $src = app(ImageSource::class)->forRecord(collect($this->products)->firstWhere('id', $id), fn () => $this->api()->image($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException|InvalidArgumentException $exception) {
            $this->dispatch('image-viewer:error', key: "products:{$id}");
            $this->dispatchAlert('No fue posible cargar la imagen', $exception->getMessage());

            return;
        }

        $product = collect($this->products)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );
        $name = is_array($product) ? $product['descripcion'] : "Producto #{$id}";
        $this->dispatch(
            'image-viewer:show',
            key: "products:{$id}",
            name: $name,
            src: $src,
        );
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('products.delete');
        $product = collect($this->products)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );
        $this->deletingId = $id;
        $this->deletingName = is_array($product)
            ? trim($product['codigo'].' · '.$product['descripcion'])
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
        $this->authorizeAbility('products.delete');

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
            $this->dispatchAlert('No fue posible eliminar el producto', $exception->getMessage());

            return;
        }

        $deletedId = $this->deletingId;
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->dispatchToast($message !== '' ? $message : 'Producto eliminado correctamente.');
        $this->dispatch('image-viewer:invalidate', key: "products:{$deletedId}");
        $this->loadPage(count($this->products) === 1 && $this->page > 1 ? $this->page - 1 : $this->page);
    }

    public function previousPage(): void
    {
        $this->goToPage($this->page - 1);
    }

    public function nextPage(): void
    {
        $this->goToPage($this->page + 1);
    }

    public function goToPage(int $page): void
    {
        if ($page < 1 || $page > $this->lastPage || $page === $this->page) {
            return;
        }

        $this->loadPage($page);
    }

    public function updatedSearch(): void
    {
        $this->loadPage(1);
    }

    public function updatedCategoria(): void
    {
        $this->resetErrorBag('familia');
    }

    public function render(): View
    {
        $permissions = $this->permissions();
        $visibleProducts = $this->products;

        $selectedActives = collect($this->principiosActivos)
            ->map(fn (mixed $id): ?array => $this->optionById('principios_activos', (int) $id))
            ->filter()
            ->values()
            ->all();

        return view('livewire.farma.productos', [
            'visibleProducts' => $visibleProducts,
            'familyRequired' => $this->familyRequired(),
            'selectedFamily' => $this->optionById('familias', (int) $this->familia),
            'selectedUnit' => $this->optionById('unidades', (int) $this->unidad),
            'selectedActives' => $this->paginateItems($selectedActives, $this->selectedActivePage),
            'familyPicker' => [
                'items' => $this->familyOptions,
                'total' => $this->familyTotal,
                'page' => $this->familyPage,
                'lastPage' => $this->familyLastPage,
            ],
            'unitPicker' => $this->pickerPage(
                'unidades',
                $this->unitSearch,
                ['abreviatura_c', 'abreviatura_v', 'label'],
                $this->unitPage,
            ),
            'activePicker' => ['items' => $this->activeOptions, 'total' => $this->activeTotal,
                'page' => $this->activePage, 'lastPage' => $this->activeLastPage],
            'access' => [
                'create' => $permissions->allowsAll(self::CREATE_ABILITIES),
                'createChild' => $permissions->allows('products.create-child'),
                'image' => $permissions->allows('products.image'),
                'edit' => $permissions->allowsAll(self::UPDATE_ABILITIES),
                'delete' => $permissions->allows('products.delete'),
            ],
        ]);
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('products.list');
        $this->loadError = null;

        try {
            $this->applyPage($this->api()->list($page, $this->search));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            if ($this->products === []) {
                $this->loadError = $exception->getMessage();
            } else {
                $this->dispatchAlert('No fue posible actualizar el listado', $exception->getMessage());
            }
        }
    }

    public function refreshOptions(string $catalog): void
    {
        $this->authorizeAbility('products.options');
        if (! $this->showFormModal) {
            return;
        }

        if ($catalog === 'familias') {
            $this->retryFamilyPage();

            return;
        }

        if ($catalog === 'principios_activos') {
            $this->retryActivePage();

            return;
        }

        $pickerIsOpen = match ($catalog) {
            'unidades' => $this->showUnitPicker,
            default => false,
        };

        if ($pickerIsOpen) {
            $this->loadOptions([$catalog]);
        }
    }

    /** @param list<string>|null $catalogs */
    private function loadOptions(?array $catalogs = null): bool
    {
        return true;
    }

    private function loadExistingImage(int $id, ?string $url = null): void
    {
        try {
            $this->imagenExistente = app(ImageSource::class)->forRecord(['imagen_url' => $url], fn () => $this->api()->image($id));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException|InvalidArgumentException $exception) {
            $this->imagenExistente = null;
            $this->dispatchAlert('No fue posible cargar la imagen actual', $exception->getMessage());
        }
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->products = collect($page->items)
            ->map(fn (array $product): array => $this->normalizeProduct($product))
            ->filter(fn (array $product): bool => $product['id'] > 0)
            ->values()
            ->all();
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function normalizeProduct(array $product): array
    {
        $category = is_array($product['categoria'] ?? null) ? $product['categoria'] : [];
        $laboratory = is_array($product['laboratorio'] ?? null) ? $product['laboratorio'] : [];
        $unit = is_array($product['unidad'] ?? null) ? $product['unidad'] : [];
        $family = is_array($product['familia'] ?? null) ? $product['familia'] : [];
        $familyPresentation = is_array($family['presentacion'] ?? null)
            ? $family['presentacion']
            : [];
        $state = is_array($product['estado'] ?? null) ? $product['estado'] : [];
        $actives = collect(is_array($product['principios_activos'] ?? null) ? $product['principios_activos'] : [])
            ->filter(fn (mixed $active): bool => is_array($active))
            ->values();

        return [
            'id' => (int) ($product['id'] ?? 0),
            'codigo' => (string) ($product['codigo'] ?? ''),
            'codigobar' => (string) ($product['codigobar'] ?? ''),
            'descripcion' => (string) ($product['descripcion'] ?? ''),
            'tiene_imagen' => (bool) ($product['tiene_imagen'] ?? false),
            'imagen_url' => $product['imagen_url'] ?? null,
            'imagen_uuid' => $product['imagen_uuid'] ?? null,
            'producto_padre_id' => (string) ($product['producto_padre_id'] ?? ''),
            'parent_label' => isset($product['producto_padre']) && is_array($product['producto_padre'])
                ? ($product['producto_padre']['codigo'] ?? '').' · '.($product['producto_padre']['descripcion'] ?? '') : '',
            'categoria_id' => $this->identifier($product['categoria_id'] ?? $category),
            'laboratorio_id' => $this->identifier($product['laboratorio_id'] ?? $laboratory),
            'unidad_id' => $this->identifier($product['unidad_id'] ?? $unit),
            'familia_id' => $this->identifier($product['familia_id'] ?? $family),
            'estado_id' => $this->identifier($product['estado_id'] ?? $state),
            'categoria_label' => $this->description($category, ['nombre', 'descripcion']),
            'laboratorio_label' => $this->description($laboratory, ['nombre']),
            'unidad_label' => $this->unitLabel($unit),
            'familia_label' => $this->description($family, ['descripcion', 'nombre']),
            'familia_presentacion_label' => $this->description(
                $familyPresentation,
                ['descripcion', 'nombre', 'label'],
            ),
            'familia_administracion_label' => $this->description(
                is_array($family['administracion'] ?? null) ? $family['administracion'] : [],
                ['descripcion', 'nombre', 'label'],
            ),
            'estado_label' => $this->description($state, ['descripcion', 'nombre']),
            'principios_activos_ids' => $actives
                ->map(fn (array $active): int => $this->identifier($active))
                ->filter(fn (int $id): bool => $id > 0)
                ->all(),
            'principios_activos_labels' => $actives
                ->map(fn (array $active): string => $this->activeLabel($active))
                ->filter()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $unit */
    private function unitLabel(array $unit): string
    {
        $purchase = trim((string) ($unit['abreviatura_c'] ?? ''));
        $sale = trim((string) ($unit['abreviatura_v'] ?? ''));

        if ($purchase !== '' || $sale !== '') {
            return trim(sprintf(
                '%s x %d → %s x %d',
                $purchase,
                (int) ($unit['cantidad_c'] ?? 0),
                $sale,
                (int) ($unit['cantidad_v'] ?? 0),
            ));
        }

        return $this->description($unit, ['descripcion', 'nombre', 'label']);
    }

    /** @param array<string, mixed> $active */
    private function activeLabel(array $active): string
    {
        $name = $this->description($active, ['nombre', 'descripcion']);
        $concentration = $this->description(
            is_array($active['concentracion_detalle'] ?? null) ? $active['concentracion_detalle'] : [],
            ['valor', 'descripcion'],
        );

        return trim($name.' '.$concentration);
    }

    private function identifier(mixed $value): int
    {
        if (is_array($value)) {
            $value = $value['id'] ?? 0;
        }

        return is_scalar($value) ? (int) $value : 0;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<int, string>  $keys
     */
    private function description(array $value, array $keys): string
    {
        foreach ($keys as $key) {
            if (is_scalar($value[$key] ?? null)) {
                return (string) $value[$key];
            }
        }

        return '';
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->categoria = '';
        $this->laboratorio = '';
        $this->laboratoryName = '';
        $this->showLaboratoryPicker = false;
        $this->laboratorySearch = '';
        $this->laboratoryOptions = [];
        $this->laboratoryPage = 1;
        $this->laboratoryLastPage = 1;
        $this->laboratoryTotal = 0;
        $this->laboratoryLoadError = null;
        $this->unidad = '';
        $this->familia = '';
        $this->productoPadreId = '';
        $this->parentName = '';
        $this->showParentPicker = false;
        $this->parentOptions = [];
        $this->parentSearch = '';
        $this->parentPage = $this->parentLastPage = 1;
        $this->parentLoadError = null;
        $this->codigo = '';
        $this->codigobar = '';
        $this->descripcion = '';
        $this->estado = '';
        $this->principiosActivos = [];
        $this->imagenArchivo = null;
        $this->imagenBase64 = null;
        $this->imagenMime = null;
        $this->imagenExistente = null;
        $this->showFamilyPicker = false;
        $this->familySearch = '';
        $this->familyPage = 1;
        $this->familyOptions = [];
        $this->familyTotal = 0;
        $this->familyLastPage = 1;
        $this->familyLoadError = null;
        $this->familyLoaded = false;
        $this->options['familias'] = [];
        $this->showUnitPicker = false;
        $this->unitSearch = '';
        $this->unitPage = 1;
        $this->showActivePicker = false;
        $this->activeSearch = '';
        $this->activePage = 1;
        $this->activeOptions = [];
        $this->activeTotal = 0;
        $this->activeLastPage = 1;
        $this->activeLoadError = null;
        $this->activeLoaded = false;
        $this->options['principios_activos'] = [];
        $this->selectedActivePage = 1;
    }

    /** @return array<string, mixed>|null */
    private function optionById(string $key, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        foreach ($this->options[$key] ?? [] as $option) {
            if ((int) ($option['id'] ?? 0) === $id) {
                return $option;
            }
        }

        return null;
    }

    private function familyRequired(): bool
    {
        $category = $this->optionById('categorias', (int) $this->categoria);

        return ($category['requires_family'] ?? false) === true;
    }

    /**
     * @param  array<int, string>  $fields
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, lastPage: int}
     */
    private function pickerPage(string $key, string $search, array $fields, int $page): array
    {
        $items = $this->filteredOptions($key, $search, $fields);
        $lastPage = max(1, (int) ceil(count($items) / self::PICKER_PAGE_SIZE));
        $currentPage = min(max(1, $page), $lastPage);

        return [
            'items' => array_slice(
                $items,
                ($currentPage - 1) * self::PICKER_PAGE_SIZE,
                self::PICKER_PAGE_SIZE,
            ),
            'total' => count($items),
            'page' => $currentPage,
            'lastPage' => $lastPage,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, lastPage: int}
     */
    private function paginateItems(array $items, int $page): array
    {
        $lastPage = max(1, (int) ceil(count($items) / self::PICKER_PAGE_SIZE));
        $currentPage = min(max(1, $page), $lastPage);

        return [
            'items' => array_slice(
                $items,
                ($currentPage - 1) * self::PICKER_PAGE_SIZE,
                self::PICKER_PAGE_SIZE,
            ),
            'total' => count($items),
            'page' => $currentPage,
            'lastPage' => $lastPage,
        ];
    }

    /** @param array<int, string> $fields */
    private function pickerLastPage(string $key, string $search, array $fields): int
    {
        return max(1, (int) ceil(count($this->filteredOptions($key, $search, $fields)) / self::PICKER_PAGE_SIZE));
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function filteredOptions(string $key, string $search, array $fields): array
    {
        $needle = Str::lower(trim($search));
        $items = $this->options[$key] ?? [];

        if ($needle === '') {
            return array_values($items);
        }

        if ($key === 'unidades' && ctype_digit($needle)) {
            return array_values(array_filter($items, static fn (array $option): bool => is_numeric($option['cantidad_c'] ?? null)
                && (int) $option['cantidad_c'] === (int) $needle));
        }

        return array_values(array_filter($items, static function (array $option) use ($fields, $needle): bool {
            $haystack = collect($fields)
                ->map(static fn (string $field): string => is_scalar($option[$field] ?? null)
                    ? (string) $option[$field]
                    : '')
                ->implode(' ');

            return Str::contains(Str::lower($haystack), $needle);
        }));
    }

    private function applyApiErrors(ApiOperationException $exception): void
    {
        foreach ($exception->errors as $field => $messages) {
            $field = match (true) {
                $field === 'imagen' => 'imagenArchivo',
                str_starts_with($field, 'principios_activos') => 'principiosActivos',
                str_ends_with($field, '.id') => substr($field, 0, -3),
                $field === 'producto_padre_id' => 'productoPadreId',
                default => $field,
            };

            foreach ((array) $messages as $message) {
                $this->addError($field, (string) $message);
            }
        }

        if ($exception->errors === []) {
            $this->dispatchAlert('No fue posible guardar el producto', $exception->getMessage());
        }
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return mb_substr(trim($value), 0, $maxLength);
    }

    private function nullableText(string $value, int $maxLength): ?string
    {
        $value = $this->sanitizeText($value, $maxLength);

        return $value === '' ? null : $value;
    }

    /** @return array<string, string> */
    private function imageMessages(): array
    {
        return [
            'imagenArchivo.image' => 'Selecciona una imagen válida.',
            'imagenArchivo.mimes' => 'La imagen debe estar en formato JPEG o PNG.',
            'imagenArchivo.max' => 'La imagen no puede exceder 1 MB.',
        ];
    }

    private function api(): ProductApiService
    {
        return app(ProductApiService::class);
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
