<?php

namespace App\Livewire\Reportes;

use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\DrawReportApiService;
use App\Services\Auth\CurrentUserPermissions;
use App\Services\Reports\DrawReportExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function is_array;
use function is_scalar;

class Sorteos extends Component
{
    private const CATALOGS = [
        'draws' => [
            'title' => 'Sorteos',
            'selected' => 'selectedDraw',
            'list' => 'reports.draws.list-draws',
            'filter' => 'reports.draws.filter-draws',
        ],
        'clients' => [
            'title' => 'Clientes',
            'selected' => 'selectedClient',
            'list' => 'reports.draws.list-clients',
            'filter' => 'reports.draws.filter-clients',
        ],
        'users' => [
            'title' => 'Usuarios',
            'selected' => 'selectedUser',
            'list' => 'reports.draws.list-users',
            'filter' => 'reports.draws.filter-users',
        ],
        'stocks' => [
            'title' => 'Stocks',
            'selected' => 'selectedStock',
            'list' => 'reports.draws.list-stocks',
            'filter' => 'reports.draws.filter-stocks',
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    public array $tickets = [];

    /** @var array<int, array{id: int, label: string}> */
    public array $draws = [];

    /** @var array<int, array{id: int, label: string}> */
    public array $clients = [];

    /** @var array<int, array{id: int, label: string}> */
    public array $users = [];

    /** @var array<int, array{id: int, label: string}> */
    public array $stocks = [];

    public string $ticketNumber = '';

    public string $selectedDraw = '';

    public string $selectedClient = '';

    public string $selectedUser = '';

    public string $selectedStock = '';

    /** @var array<string, string> */
    public array $selectedLabels = [];

    public bool $showCatalogModal = false;

    public ?string $activeCatalog = null;

    public string $catalogSearch = '';

    public int $catalogPage = 1;

    public int $catalogLastPage = 1;

    public int $catalogTotal = 0;

    public ?string $catalogError = null;

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public bool $filterActive = false;

    public bool $showExportModal = false;

    public string $exportFormat = 'pdf';

    public ?string $loadError = null;

    public function mount(): void
    {
        $permissions = $this->permissions();

        if ($permissions->allows('reports.draws.list-draws')) {
            $this->loadDraws();
        }

        if ($permissions->allows('reports.draws.list-clients')) {
            $this->loadClients();
        }

        if ($permissions->allows('reports.draws.list-users')) {
            $this->loadUsers();
        }

        if ($permissions->allows('reports.draws.list-stocks')) {
            $this->loadStocks();
        }
    }

    public function applyFilters(): void
    {
        $this->authorizeAbility('reports.draws.filter');
        $this->resetValidation();

        $validated = $this->validate([
            'ticketNumber' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-]+$/'],
            'selectedDraw' => ['nullable', 'integer', 'min:1'],
            'selectedClient' => ['nullable', 'integer', 'min:1'],
            'selectedUser' => ['nullable', 'integer', 'min:1'],
            'selectedStock' => ['nullable', 'integer', 'min:1'],
        ], [
            'ticketNumber.regex' => 'El número de ticket solo admite letras, números y guiones.',
        ]);

        $filters = [];

        if (trim($validated['ticketNumber'] ?? '') !== '') {
            $filters[] = [
                'key' => 'ntiket',
                'value' => trim($validated['ticketNumber']),
                'operator' => 'LIKE',
            ];
        }

        foreach ([
            'selectedDraw' => 'promocion',
            'selectedClient' => 'cliente',
            'selectedUser' => 'usuario',
            'selectedStock' => 'stock',
        ] as $property => $key) {
            if ((int) ($validated[$property] ?? 0) > 0) {
                $filters[] = ['key' => $key, 'value' => (int) $validated[$property]];
            }
        }

        if ($filters === []) {
            $this->addError('filters', 'Selecciona al menos un criterio para generar el reporte.');

            return;
        }

        $this->filterActive = true;
        $this->loadResults($filters, 1);
    }

    public function clearFilters(): void
    {
        $this->reset([
            'ticketNumber',
            'selectedDraw',
            'selectedClient',
            'selectedUser',
            'selectedStock',
            'selectedLabels',
            'tickets',
            'total',
            'filterActive',
            'showExportModal',
            'loadError',
        ]);
        $this->page = 1;
        $this->lastPage = 1;
        $this->resetValidation();
    }

    public function openExport(): void
    {
        $this->authorizeAbility('reports.draws.export');

        if (! $this->filterActive || $this->total === 0 || $this->activeFilters() === []) {
            $this->dispatchAlert(
                'No hay resultados para exportar',
                'Genera primero un reporte con al menos un resultado.',
            );

            return;
        }

        $this->exportFormat = 'pdf';
        $this->showExportModal = true;
        $this->resetValidation('exportFormat');
    }

    public function closeExport(): void
    {
        $this->showExportModal = false;
        $this->resetValidation('exportFormat');
    }

    public function exportReport(): ?StreamedResponse
    {
        $this->authorizeAbility('reports.draws.export');

        $validated = $this->validate([
            'exportFormat' => ['required', 'in:pdf,xlsx'],
        ]);

        if (! $this->filterActive || $this->activeFilters() === []) {
            $this->dispatchAlert(
                'No hay un reporte activo',
                'Aplica los filtros nuevamente antes de exportar.',
            );

            return null;
        }

        try {
            $rows = collect($this->api()->allFilteredTickets($this->activeFilters()))
                ->map(fn (array $ticket): array => $this->normalizeTicket($ticket))
                ->filter(fn (array $ticket): bool => $ticket['number'] !== '')
                ->values()
                ->all();
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return null;
        } catch (ApiOperationException $exception) {
            $this->dispatchAlert('No fue posible preparar la exportación', $exception->getMessage());

            return null;
        }

        if ($rows === []) {
            $this->dispatchAlert('No hay resultados para exportar', 'El reporte filtrado no contiene registros.');

            return null;
        }

        try {
            $content = $validated['exportFormat'] === 'pdf'
                ? $this->exporter()->pdf($rows, $this->criteriaSummary())
                : $this->exporter()->excel($rows, $this->criteriaSummary());
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatchAlert(
                'No fue posible generar el archivo',
                'Ocurrió un error al construir la exportación. Inténtalo nuevamente.',
            );

            return null;
        }

        $extension = $validated['exportFormat'];
        $contentType = $extension === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $filename = 'reporte-sorteos-'.now()->format('Ymd-His').".{$extension}";
        $this->showExportModal = false;

        return response()->streamDownload(
            static function () use ($content): void {
                echo $content;
            },
            $filename,
            ['Content-Type' => $contentType],
        );
    }

    public function openCatalog(string $catalog): void
    {
        $definition = self::CATALOGS[$catalog] ?? null;
        abort_unless($definition !== null, 404);
        abort_unless(
            $this->permissions()->allowsAny([$definition['list'], $definition['filter']]),
            403,
        );

        $this->activeCatalog = $catalog;
        $this->catalogSearch = '';
        $this->catalogPage = 1;
        $this->catalogLastPage = 1;
        $this->catalogTotal = 0;
        $this->catalogError = null;
        $this->resetValidation('catalogSearch');
        $this->showCatalogModal = true;

        if ($this->permissions()->allows($definition['list'])) {
            $this->loadCatalogPage(1);
        } else {
            $this->{$catalog} = [];
        }
    }

    public function closeCatalog(): void
    {
        $this->showCatalogModal = false;
    }

    public function updatedCatalogSearch(): void
    {
        if (! $this->showCatalogModal) {
            return;
        }

        $this->resetValidation('catalogSearch');
        if (trim($this->catalogSearch) === '') {
            $this->resetCatalogSearch();
        } elseif (mb_strlen(trim($this->catalogSearch)) >= 2) {
            $this->searchCatalog();
        } else {
            $this->{$this->activeCatalog} = [];
            $this->catalogTotal = 0;
            $this->catalogPage = $this->catalogLastPage = 1;
        }
    }

    public function searchCatalog(): void
    {
        $definition = $this->activeCatalogDefinition();
        $this->authorizeAbility($definition['filter']);

        if (trim($this->catalogSearch) === '') {
            $this->resetCatalogSearch();

            return;
        }

        $this->validate([
            'catalogSearch' => ['required', 'string', 'min:2', 'max:100'],
        ], [
            'catalogSearch.min' => 'Escribe al menos 2 caracteres.',
            'catalogSearch.max' => 'La búsqueda no puede exceder 100 caracteres.',
        ]);

        $this->loadCatalogPage(1);
    }

    public function resetCatalogSearch(): void
    {
        $definition = $this->activeCatalogDefinition();
        $this->catalogSearch = '';
        $this->resetValidation('catalogSearch');

        if ($this->permissions()->allows($definition['list'])) {
            $this->loadCatalogPage(1);
        } else {
            $this->{$this->activeCatalog} = [];
            $this->catalogTotal = 0;
            $this->catalogPage = 1;
            $this->catalogLastPage = 1;
        }
    }

    public function goToCatalogPage(int $page): void
    {
        $definition = $this->activeCatalogDefinition();
        $ability = trim($this->catalogSearch) === '' ? $definition['list'] : $definition['filter'];
        $this->authorizeAbility($ability);

        if ($page < 1 || $page > $this->catalogLastPage || $page === $this->catalogPage) {
            return;
        }

        $this->loadCatalogPage($page);
    }

    public function selectCatalogItem(int $id): void
    {
        $definition = $this->activeCatalogDefinition();
        $catalog = $this->currentCatalog();
        abort_unless(
            $this->permissions()->allowsAny([$definition['list'], $definition['filter']]),
            403,
        );

        $items = match ($catalog) {
            'draws' => $this->draws,
            'clients' => $this->clients,
            'users' => $this->users,
            'stocks' => $this->stocks,
            default => throw new \InvalidArgumentException('Catálogo no válido.'),
        };
        $item = collect($items)->firstWhere('id', $id);

        if (! is_array($item)) {
            return;
        }

        $this->{$definition['selected']} = (string) $id;
        $this->selectedLabels[$catalog] = $item['label'];
        $this->showCatalogModal = false;
    }

    public function clearCatalogSelection(string $catalog): void
    {
        $definition = self::CATALOGS[$catalog] ?? null;
        abort_unless($definition !== null, 404);
        abort_unless(
            $this->permissions()->allowsAny([$definition['list'], $definition['filter']]),
            403,
        );

        $this->{$definition['selected']} = '';
        unset($this->selectedLabels[$catalog]);
    }

    public function goToPage(int $page): void
    {
        if (! $this->filterActive || $page < 1 || $page > $this->lastPage || $page === $this->page) {
            return;
        }

        $this->loadResults($this->activeFilters(), $page);
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

        return view('livewire.reportes.sorteos', [
            'access' => [
                'export' => $permissions->allows('reports.draws.export'),
                'drawCatalog' => $permissions->allowsAny([
                    'reports.draws.list-draws',
                    'reports.draws.filter-draws',
                ]),
                'clientCatalog' => $permissions->allowsAny([
                    'reports.draws.list-clients',
                    'reports.draws.filter-clients',
                ]),
                'userCatalog' => $permissions->allowsAny([
                    'reports.draws.list-users',
                    'reports.draws.filter-users',
                ]),
                'stockCatalog' => $permissions->allowsAny([
                    'reports.draws.list-stocks',
                    'reports.draws.filter-stocks',
                ]),
            ],
            'catalogTitle' => self::CATALOGS[$this->activeCatalog]['title'] ?? '',
            'catalogRows' => $this->activeCatalog !== null ? $this->{$this->activeCatalog} : [],
            'catalogCanSearch' => $this->activeCatalog !== null
                && $permissions->allows(self::CATALOGS[$this->activeCatalog]['filter']),
        ]);
    }

    private function loadCatalogPage(int $page): void
    {
        $catalog = $this->currentCatalog();
        $definition = self::CATALOGS[$catalog];
        $term = trim($this->catalogSearch);
        $this->authorizeAbility($term === '' ? $definition['list'] : $definition['filter']);
        $this->catalogError = null;

        $this->replaceCatalog($catalog, function () use ($catalog, $term, $page): PaginatedCollectionData {
            if ($term !== '') {
                return match ($catalog) {
                    'draws' => $this->api()->filterDraws($term, $page),
                    'clients' => $this->api()->filterClients($term, $page),
                    'users' => $this->api()->filterUsers($term, $page),
                    'stocks' => $this->api()->filterStocks($term, $page),
                    default => throw new \InvalidArgumentException('Catálogo no válido.'),
                };
            }

            return match ($catalog) {
                'draws' => $this->api()->listDraws($page),
                'clients' => $this->api()->listClients($page),
                'users' => $this->api()->listUsers($page),
                'stocks' => $this->api()->listStocks($page),
                default => throw new \InvalidArgumentException('Catálogo no válido.'),
            };
        });
    }

    private function currentCatalog(): string
    {
        if ($this->activeCatalog === null || ! isset(self::CATALOGS[$this->activeCatalog])) {
            abort(404);
        }

        return $this->activeCatalog;
    }

    /** @return array{title: string, selected: string, list: string, filter: string} */
    private function activeCatalogDefinition(): array
    {
        return self::CATALOGS[$this->currentCatalog()];
    }

    private function loadDraws(): void
    {
        $this->authorizeAbility('reports.draws.list-draws');
        $this->replaceCatalog('draws', fn (): PaginatedCollectionData => $this->api()->listDraws());
    }

    private function loadClients(): void
    {
        $this->authorizeAbility('reports.draws.list-clients');
        $this->replaceCatalog('clients', fn (): PaginatedCollectionData => $this->api()->listClients());
    }

    private function loadUsers(): void
    {
        $this->authorizeAbility('reports.draws.list-users');
        $this->replaceCatalog('users', fn (): PaginatedCollectionData => $this->api()->listUsers());
    }

    private function loadStocks(): void
    {
        $this->authorizeAbility('reports.draws.list-stocks');
        $this->replaceCatalog('stocks', fn (): PaginatedCollectionData => $this->api()->listStocks());
    }

    /**
     * @param  array<int, array{key: string, value: string|int, operator?: string}>  $filters
     */
    private function loadResults(array $filters, int $page): void
    {
        $this->loadError = null;

        try {
            $result = $this->api()->filterTickets($filters, $page);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            if ($exception->status === 404) {
                $this->applyEmptyResults();

                return;
            }

            $this->loadError = $exception->getMessage();

            return;
        }

        $this->tickets = collect($result->items)
            ->map(fn (array $ticket): array => $this->normalizeTicket($ticket))
            ->filter(fn (array $ticket): bool => $ticket['number'] !== '')
            ->values()
            ->all();
        $this->total = $result->total;
        $this->page = $result->currentPage;
        $this->lastPage = $result->lastPage;
    }

    /**
     * @param  callable(): PaginatedCollectionData  $loader
     */
    private function replaceCatalog(string $catalog, callable $loader): void
    {
        try {
            $page = $loader();
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            if ($exception->status === 404) {
                $this->{$catalog} = [];
                if ($this->activeCatalog === $catalog) {
                    $this->catalogTotal = 0;
                    $this->catalogPage = 1;
                    $this->catalogLastPage = 1;
                }

                return;
            }

            if ($this->activeCatalog === $catalog) {
                $this->catalogError = $exception->getMessage();
            } else {
                $this->dispatchAlert('No fue posible cargar el catálogo', $exception->getMessage());
            }

            return;
        }

        $this->{$catalog} = collect($page->items)
            ->map(fn (array $item): array => $this->normalizeCatalogItem($catalog, $item))
            ->filter(fn (array $item): bool => $item['id'] > 0)
            ->values()
            ->all();

        if ($this->activeCatalog === $catalog) {
            $this->catalogTotal = $page->total;
            $this->catalogPage = $page->currentPage;
            $this->catalogLastPage = $page->lastPage;
        }
    }

    /**
     * @return array<int, array{key: string, value: string|int, operator?: string}>
     */
    private function activeFilters(): array
    {
        $filters = [];

        if (trim($this->ticketNumber) !== '') {
            $filters[] = ['key' => 'ntiket', 'value' => trim($this->ticketNumber), 'operator' => 'LIKE'];
        }

        foreach ([
            'selectedDraw' => 'promocion',
            'selectedClient' => 'cliente',
            'selectedUser' => 'usuario',
            'selectedStock' => 'stock',
        ] as $property => $key) {
            if ((int) $this->{$property} > 0) {
                $filters[] = ['key' => $key, 'value' => (int) $this->{$property}];
            }
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $ticket
     * @return array<string, mixed>
     */
    private function normalizeTicket(array $ticket): array
    {
        $promotion = is_array($ticket['promocion'] ?? null) ? $ticket['promocion'] : [];
        $client = is_array($ticket['cliente'] ?? null) ? $ticket['cliente'] : [];
        $user = is_array($ticket['usuario'] ?? null) ? $ticket['usuario'] : [];
        $stock = is_array($ticket['stock'] ?? null) ? $ticket['stock'] : [];

        return [
            'id' => (int) ($ticket['id'] ?? 0),
            'number' => (string) ($ticket['ntiket'] ?? $ticket['numero'] ?? ''),
            'promotion' => $this->description($promotion),
            'client' => $this->clientName($client),
            'document' => (string) ($client['docid'] ?? ''),
            'user' => $this->description($user),
            'stock' => $this->description($stock),
            'created_at' => $this->dateTimeValue($ticket['created_at'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{id: int, label: string}
     */
    private function normalizeCatalogItem(string $catalog, array $item): array
    {
        $label = match ($catalog) {
            'clients' => $this->clientName($item),
            default => $this->description($item),
        };

        if ($catalog === 'clients' && is_scalar($item['docid'] ?? null)) {
            $label = trim((string) $item['docid'].' · '.$label);
        }

        return [
            'id' => (int) ($item['id'] ?? 0),
            'label' => $label,
        ];
    }

    /** @param array<string, mixed> $client */
    private function clientName(array $client): string
    {
        $parts = [
            $client['pnombre'] ?? '',
            $client['snombre'] ?? '',
            $client['papellido'] ?? '',
            $client['spaellido'] ?? '',
        ];

        $name = trim(implode(' ', array_filter(
            $parts,
            fn (mixed $part): bool => is_scalar($part) && trim((string) $part) !== '',
        )));

        return $name !== '' ? $name : $this->description($client);
    }

    private function description(mixed $value): string
    {
        if (is_array($value)) {
            foreach (['descripcion', 'nombre', 'name', 'label'] as $key) {
                if (is_scalar($value[$key] ?? null)) {
                    return (string) $value[$key];
                }
            }
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function dateTimeValue(mixed $value): string
    {
        return is_scalar($value) ? str_replace('T', ' ', substr((string) $value, 0, 16)) : '';
    }

    private function applyEmptyResults(): void
    {
        $this->tickets = [];
        $this->total = 0;
        $this->page = 1;
        $this->lastPage = 1;
    }

    private function api(): DrawReportApiService
    {
        return app(DrawReportApiService::class);
    }

    private function exporter(): DrawReportExporter
    {
        return app(DrawReportExporter::class);
    }

    private function permissions(): CurrentUserPermissions
    {
        return app(CurrentUserPermissions::class);
    }

    private function authorizeAbility(string $ability): void
    {
        Gate::authorize(CurrentUserPermissions::GATE, $ability);
    }

    private function redirectToLogin(): void
    {
        $this->loadError = null;
        $this->redirectRoute('login');
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
     * @return array<int, string>
     */
    private function criteriaSummary(): array
    {
        $criteria = [];

        if (trim($this->ticketNumber) !== '') {
            $criteria[] = 'Ticket: '.trim($this->ticketNumber);
        }

        foreach ([
            ['property' => 'selectedDraw', 'catalogKey' => 'draws', 'catalog' => $this->draws, 'label' => 'Sorteo'],
            ['property' => 'selectedClient', 'catalogKey' => 'clients', 'catalog' => $this->clients, 'label' => 'Cliente'],
            ['property' => 'selectedUser', 'catalogKey' => 'users', 'catalog' => $this->users, 'label' => 'Usuario'],
            ['property' => 'selectedStock', 'catalogKey' => 'stocks', 'catalog' => $this->stocks, 'label' => 'Stock'],
        ] as $criterion) {
            $id = (int) $this->{$criterion['property']};

            if ($id <= 0) {
                continue;
            }

            $selected = collect($criterion['catalog'])->firstWhere('id', $id);
            $value = $this->selectedLabels[$criterion['catalogKey']]
                ?? (is_array($selected) && filled($selected['label']) ? $selected['label'] : "#{$id}");
            $criteria[] = $criterion['label'].': '.$value;
        }

        return $criteria;
    }
}
