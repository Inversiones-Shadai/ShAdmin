<?php

namespace App\Livewire\Farma;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\ProductCatalogApiService;
use App\Services\Auth\CurrentUserPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

use function is_array;

class ProductCatalogManager extends Component
{
    use RemembersSelectorChoice;

    private const CONCENTRATION_PAGE_SIZE = 10;

    private const FAMILY_OPTION_PAGE_SIZE = 10;

    public string $catalog;

    /** @var array<int, array<string, mixed>> */
    public array $records = [];

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<string, array<int, array{id: int, label: string}>> */
    public array $options = [];

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public string $search = '';

    public string $concentrationSearch = '';

    public int $concentrationPage = 1;

    /** @var array<string, string> */
    public array $familyOptionSearch = ['presentacion' => '', 'administracion' => ''];

    /** @var array<string, int> */
    public array $familyOptionPage = ['presentacion' => 1, 'administracion' => 1];

    public ?string $loadError = null;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public function mount(string $catalog): void
    {
        $this->catalog = $catalog;
        $this->definition();
        $this->resetForm();
        $this->loadPage();
    }

    public function openCreate(): void
    {
        $this->authorizeAbility('create');
        $this->resetValidation();
        $this->resetForm();

        if (! $this->loadOptions()) {
            return;
        }

        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAbility('search');
        $this->authorizeAbility('update');
        $this->resetValidation();
        $this->resetForm();

        try {
            $record = $this->api()->find($this->catalog, $id);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible cargar el registro', $exception->getMessage());

            return;
        }

        if (! $this->loadOptions()) {
            return;
        }

        $this->editingId = $id;

        foreach ($this->fields() as $field) {
            $name = (string) $field['name'];
            $value = $record[$name] ?? null;
            if (is_array($value)) {
                $value = $value['id'] ?? null;
            }
            $this->form[$name] = $field['type'] === 'select' || $field['type'] === 'number'
                ? ($value === null ? null : (int) $value)
                : (string) ($value ?? '');
        }

        $this->showFormModal = true;
    }

    public function save(): void
    {
        $action = $this->editingId === null ? 'create' : 'update';
        $this->authorizeAbility($action);

        $validated = $this->validate(
            $this->validationRules(),
            $this->validationMessages(),
            $this->validationAttributes(),
        );
        $payload = $this->payload($validated['form'] ?? []);

        try {
            $message = $this->editingId === null
                ? $this->api()->create($this->catalog, $payload)
                : $this->api()->update($this->catalog, $this->editingId, $payload);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->applyApiErrors($exception);

            return;
        }

        $isCreating = $this->editingId === null;
        $this->showFormModal = false;
        $this->dispatchToast(
            $message !== ''
                ? $message
                : ($isCreating
                    ? 'Registro creado correctamente.'
                    : 'Registro actualizado correctamente.'),
        );
        $this->resetForm();
        $this->loadPage($isCreating ? 1 : $this->page);
    }

    public function closeForm(): void
    {
        $this->showFormModal = false;
        $this->resetValidation();
        $this->resetForm();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAbility('delete');
        $record = collect($this->records)->first(
            fn (array $item): bool => (int) ($item['id'] ?? 0) === $id,
        );
        $this->deletingId = $id;
        $this->deletingName = is_array($record) ? $this->recordName($record) : "#{$id}";
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
        $this->authorizeAbility('delete');

        if ($this->deletingId === null) {
            return;
        }

        try {
            $message = $this->api()->delete($this->catalog, $this->deletingId);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->showDeleteModal = false;
            $this->dispatchAlert('No fue posible eliminar el registro', $exception->getMessage());

            return;
        }

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
        $this->dispatchToast(
            $message !== '' ? $message : 'Registro eliminado correctamente.',
        );
        $this->loadPage(count($this->records) === 1 && $this->page > 1 ? $this->page - 1 : $this->page);
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

    public function updatedConcentrationSearch(): void
    {
        $this->concentrationPage = 1;
    }

    public function previousConcentrationPage(): void
    {
        $this->concentrationPage = max(1, $this->concentrationPage - 1);
    }

    public function nextConcentrationPage(): void
    {
        $picker = $this->concentrationPicker();
        $this->concentrationPage = min($picker['lastPage'], $picker['page'] + 1);
    }

    public function selectConcentration(int $id): void
    {
        if ($this->catalog !== 'principios-activos' || ! $this->showFormModal) {
            return;
        }

        if (collect($this->options['concentracion'] ?? [])->contains('id', $id)) {
            $this->form['concentracion'] = $id;
            $this->resetValidation('form.concentracion');
        }
    }

    public function updatedSearch(): void
    {
        $this->loadPage(1);
    }

    public function updatedFamilyOptionSearch(string $value, string $field): void
    {
        if (array_key_exists($field, $this->familyOptionPage)) {
            $this->familyOptionPage[$field] = 1;
        }
    }

    public function goToFamilyOptionPage(string $field, int $page): void
    {
        if ($this->catalog !== 'familias' || ! $this->showFormModal
            || ! in_array($field, ['presentacion', 'administracion'], true)) {
            return;
        }

        $picker = $this->familyOptionPicker($field);
        $this->familyOptionPage[$field] = min(max(1, $page), $picker['lastPage']);
    }

    public function selectFamilyOption(string $field, int $id): void
    {
        if ($this->catalog !== 'familias' || ! $this->showFormModal
            || ! in_array($field, ['presentacion', 'administracion'], true)) {
            return;
        }

        if (collect($this->options[$field] ?? [])->contains('id', $id)) {
            $this->form[$field] = $id;
            $this->resetValidation('form.'.$field);
        }
    }

    public function render(): View
    {
        $permissions = $this->permissions();
        $visibleRecords = $this->records;

        return view('livewire.farma.product-catalog-manager', [
            'definition' => $this->definition(),
            'visibleRecords' => $visibleRecords,
            'concentrationPicker' => $this->concentrationPicker(),
            'selectedConcentration' => collect($this->options['concentracion'] ?? [])
                ->firstWhere('id', $this->form['concentracion'] ?? null),
            'familyOptionPickers' => $this->catalog === 'familias' ? [
                'presentacion' => $this->familyOptionPicker('presentacion'),
                'administracion' => $this->familyOptionPicker('administracion'),
            ] : [],
            'access' => [
                'create' => $permissions->allows($this->ability('create')),
                'edit' => $permissions->allowsAll([
                    $this->ability('search'),
                    $this->ability('update'),
                ]),
                'delete' => $permissions->allows($this->ability('delete')),
            ],
        ]);
    }

    /** @return array{items: array<int, array{id: int, label: string}>, total: int, page: int, lastPage: int} */
    private function concentrationPicker(): array
    {
        $needle = Str::lower(trim($this->concentrationSearch));
        $items = collect($this->options['concentracion'] ?? [])
            ->when($needle !== '', fn ($options) => $options->filter(
                fn (array $option): bool => Str::contains(Str::lower($option['label']), $needle),
            ))
            ->sortByDesc('id')
            ->values();
        $lastPage = max(1, (int) ceil($items->count() / self::CONCENTRATION_PAGE_SIZE));
        $page = min(max(1, $this->concentrationPage), $lastPage);

        return [
            'items' => $items->slice(($page - 1) * self::CONCENTRATION_PAGE_SIZE, self::CONCENTRATION_PAGE_SIZE)->values()->all(),
            'total' => $items->count(),
            'page' => $page,
            'lastPage' => $lastPage,
        ];
    }

    /** @return array{items: array<int, array{id: int, label: string}>, total: int, page: int, lastPage: int, selected: array{id: int, label: string}|null} */
    private function familyOptionPicker(string $field): array
    {
        $options = collect($this->options[$field] ?? []);
        $needle = Str::lower(trim($this->familyOptionSearch[$field] ?? ''));
        $items = $options
            ->when($needle !== '', fn ($options) => $options->filter(
                fn (array $option): bool => Str::contains(Str::lower($option['label']), $needle),
            ))
            ->sortByDesc('id')
            ->values();
        $lastPage = max(1, (int) ceil($items->count() / self::FAMILY_OPTION_PAGE_SIZE));
        $page = min(max(1, $this->familyOptionPage[$field] ?? 1), $lastPage);

        return [
            'items' => $items->slice(($page - 1) * self::FAMILY_OPTION_PAGE_SIZE, self::FAMILY_OPTION_PAGE_SIZE)->values()->all(),
            'total' => $items->count(),
            'page' => $page,
            'lastPage' => $lastPage,
            'selected' => $options->firstWhere('id', $this->form[$field] ?? null),
        ];
    }

    private function loadPage(int $page = 1): void
    {
        $this->authorizeAbility('list');
        $this->loadError = null;

        try {
            $this->applyPage($this->api()->list($this->catalog, $page, $this->search));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->loadError = $exception->getMessage();
        }
    }

    private function loadOptions(): bool
    {
        return true;
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->records = collect($page->items)
            ->map(function (array $record): array {
                $record['id'] = (int) ($record['id'] ?? 0);

                return $record;
            })
            ->filter(fn (array $record): bool => $record['id'] > 0)
            ->values()
            ->all();
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    /** @return array<string, array<int, string>> */
    private function validationRules(): array
    {
        $rules = [];

        foreach ($this->fields() as $field) {
            $fieldRules = [(bool) ($field['required'] ?? false) ? 'required' : 'nullable'];

            if (($field['type'] ?? null) === 'select' || ($field['type'] ?? null) === 'number') {
                $fieldRules[] = 'integer';
                $fieldRules[] = 'min:'.(int) ($field['min'] ?? 1);
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:'.(int) ($field['max'] ?? 255);
            }

            $rules['form.'.(string) $field['name']] = $fieldRules;
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function validationMessages(): array
    {
        return [
            'form.*.required' => 'El campo :attribute es obligatorio.',
            'form.*.integer' => 'El campo :attribute debe ser un número entero.',
            'form.*.min' => 'El campo :attribute debe ser mayor que cero.',
            'form.*.max' => 'El campo :attribute excede la longitud permitida.',
        ];
    }

    /** @return array<string, string> */
    private function validationAttributes(): array
    {
        return collect($this->fields())
            ->mapWithKeys(fn (array $field): array => [
                'form.'.(string) $field['name'] => Str::lower((string) $field['label']),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        $payload = [];

        foreach ($this->fields() as $field) {
            $name = (string) $field['name'];
            $value = $validated[$name] ?? null;

            if (($field['type'] ?? null) === 'select' || ($field['type'] ?? null) === 'number') {
                $payload[$name] = (int) $value;

                continue;
            }

            $value = $this->sanitizeText((string) ($value ?? ''), (int) ($field['max'] ?? 255));
            $payload[$name] = $value === '' && ! ($field['required'] ?? false) ? null : $value;
        }

        return $payload;
    }

    /** @param array<string, mixed> $record */
    private function recordName(array $record): string
    {
        $titleFields = $this->definition()['title_fields'] ?? [];
        $parts = [];

        if (is_array($titleFields)) {
            foreach ($titleFields as $key) {
                if (! is_string($key)) {
                    continue;
                }

                $value = trim((string) data_get($record, $key, ''));

                if ($value !== '') {
                    $parts[] = $value;
                }
            }
        }

        return $parts !== [] ? implode(' / ', $parts) : '#'.(int) ($record['id'] ?? 0);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->options = [];
        $this->form = [];
        $this->concentrationSearch = '';
        $this->concentrationPage = 1;

        $this->familyOptionSearch = ['presentacion' => '', 'administracion' => ''];
        $this->familyOptionPage = ['presentacion' => 1, 'administracion' => 1];

        foreach ($this->fields() as $field) {
            $this->form[(string) $field['name']] = in_array(
                $field['type'] ?? 'text',
                ['select', 'number'],
                true,
            ) ? null : '';
        }
    }

    private function applyApiErrors(ApiOperationException $exception): void
    {
        foreach ($exception->errors as $field => $messages) {
            foreach ((array) $messages as $message) {
                $this->addError('form.'.$field, (string) $message);
            }
        }

        if ($exception->errors === []) {
            $this->dispatchAlert('No fue posible guardar el registro', $exception->getMessage());
        }
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return mb_substr(trim($value), 0, $maxLength);
    }

    /** @return array<string, mixed> */
    private function definition(): array
    {
        $definition = config("farma_product_catalogs.{$this->catalog}");

        abort_unless(is_array($definition), 404);

        return $definition;
    }

    /** @return array<int, array<string, mixed>> */
    private function fields(): array
    {
        $fields = $this->definition()['fields'] ?? [];

        return is_array($fields) ? array_values(array_filter($fields, is_array(...))) : [];
    }

    private function ability(string $action): string
    {
        return "product-catalogs.{$this->catalog}.{$action}";
    }

    private function api(): ProductCatalogApiService
    {
        return app(ProductCatalogApiService::class);
    }

    private function permissions(): CurrentUserPermissions
    {
        return app(CurrentUserPermissions::class);
    }

    private function authorizeAbility(string $action): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $this->ability($action));
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
