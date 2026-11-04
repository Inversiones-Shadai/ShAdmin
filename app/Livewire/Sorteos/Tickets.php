<?php

namespace App\Livewire\Sorteos;

use App\Data\Api\PaginatedCollectionData;
use App\Data\Api\TicketPrintData;
use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Livewire\Concerns\RemembersSelectorChoice;
use App\Services\Api\SelectorApiService;
use App\Services\Api\TicketApiService;
use App\Services\Auth\CurrentUserPermissions;
use App\Services\Printing\TicketPrinter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Throwable;

use function is_array;
use function is_numeric;
use function is_scalar;

class Tickets extends Component
{
    use RemembersSelectorChoice;

    /** @var array<int, array<string, mixed>> */
    public array $tickets = [];

    /** @var array<string, mixed>|null */
    public ?array $activePromotion = null;

    /** @var array<int, array<string, mixed>> */
    public array $clients = [];

    public int $clientPage = 1;

    public int $clientLastPage = 1;

    /** @var array<int, array{id: string, label: string}> */
    public array $departments = [];

    /** @var array<int, array{id: string, label: string}> */
    public array $municipalities = [];

    public int $total = 0;

    public int $page = 1;

    public int $lastPage = 1;

    public ?string $loadError = null;

    public ?string $promotionError = null;

    public bool $showTicketModal = false;

    public bool $showClientModal = false;

    public string $clientSearch = '';

    public ?int $selectedClientId = null;

    public string $selectedClientName = '';

    public string $selectedClientDocument = '';

    public string $phoneStatus = 'unchecked';

    public string $phone = '';

    public int $copies = 1;

    public string $newDocument = '';

    public string $newFirstName = '';

    public string $newSecondName = '';

    public string $newFirstSurname = '';

    public string $newSecondSurname = '';

    public string $newAge = '';

    public string $newPhone = '';

    public string $newGender = '';

    public string $newDepartment = '';

    public string $newMunicipality = '';

    /** @var array<string, mixed>|null */
    public ?array $lastTicketData = null;

    public function mount(): void
    {
        $this->loadTickets();

        if ($this->permissions()->allows('tickets.current-promotion')) {
            $this->loadCurrentPromotion();
        }
    }

    public function openGenerator(): void
    {
        $this->authorizeAbilities(['tickets.create', 'tickets.current-promotion', 'tickets.check-phone']);

        if (! $this->permissions()->allowsAny(['tickets.clients', 'tickets.search-clients', 'tickets.create-client'])) {
            abort(403);
        }

        if ($this->stockId() <= 0) {
            $this->dispatchAlert(
                'No se puede generar el ticket',
                'El dispositivo no tiene un stock válido asociado a la sesión.',
            );

            return;
        }

        $this->resetTicketSelection();
        $this->resetValidation();

        if ($this->activePromotion === null && ! $this->loadCurrentPromotion()) {
            return;
        }

        $this->copies = $this->permissions()->allows('tickets.copies')
            ? min(10, max(1, (int) ($this->activePromotion['impresiones'] ?? 1)))
            : 1;

        if ($this->permissions()->allows('tickets.clients')) {
            $this->loadClients();
        }

        $this->showTicketModal = true;
    }

    public function closeGenerator(): void
    {
        $this->showTicketModal = false;
        $this->resetValidation();
        $this->resetTicketSelection();
    }

    public function updatedClientSearch(): void
    {
        if (! $this->showTicketModal) {
            return;
        }

        $this->resetValidation('clientSearch');
        if (mb_strlen(trim($this->clientSearch)) < 2) {
            $this->clients = [];
            $this->clientPage = $this->clientLastPage = 1;

            return;
        }

        $this->searchClients();
    }

    public function searchClients(int $page = 1): void
    {
        $this->authorizeAbility('tickets.search-clients');

        $validated = $this->validate([
            'clientSearch' => ['required', 'string', 'min:2', 'max:100'],
        ], [
            'clientSearch.required' => 'Escribe una identidad, nombre o apellido.',
            'clientSearch.min' => 'Escribe al menos 2 caracteres.',
            'clientSearch.max' => 'La búsqueda no puede exceder 100 caracteres.',
        ]);

        try {
            $this->loadClientChoices('ticket-client-search', ['search' => $validated['clientSearch'], 'page' => max(1, $page)]);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            if ($exception->status === 404) {
                $this->clients = [];

                return;
            }

            $this->dispatchAlert('No fue posible buscar clientes', $exception->getMessage());
        }
    }

    public function clearClientSearch(): void
    {
        $this->clientSearch = '';
        $this->resetValidation('clientSearch');

        if ($this->permissions()->allows('tickets.clients')) {
            $this->loadClients();
        } else {
            $this->clients = [];
        }
    }

    public function changeClientPage(int $delta): void
    {
        abort_unless($this->showTicketModal, 403);
        $page = max(1, min($this->clientLastPage, $this->clientPage + $delta));
        if (trim($this->clientSearch) !== '') {
            $this->searchClients($page);
        } else {
            $this->loadClients($page);
        }
    }

    public function selectClient(int $id): void
    {
        $this->authorizeAbility('tickets.check-phone');

        $client = collect($this->clients)->first(
            fn (array $item): bool => (int) $item['id'] === $id,
        );

        if (! is_array($client)) {
            $this->dispatchAlert('Cliente no disponible', 'Vuelve a buscar el cliente e inténtalo nuevamente.');

            return;
        }

        $this->selectedClientId = $id;
        $this->selectedClientName = (string) $client['name'];
        $this->selectedClientDocument = (string) $client['document'];
        $this->phone = '';
        $this->phoneStatus = 'checking';

        try {
            $this->phoneStatus = $this->api()->isPhoneActive($id) ? 'active' : 'required';
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            $this->phoneStatus = 'unchecked';
            $this->dispatchAlert('No fue posible verificar el teléfono', $exception->getMessage());
        }
    }

    public function updatePhone(): void
    {
        $this->authorizeAbility('tickets.update-phone');

        if ($this->selectedClientId === null) {
            $this->addError('phone', 'Selecciona un cliente.');

            return;
        }

        $validated = $this->validate([
            'phone' => ['required', 'regex:/^[0-9]{8}$/'],
        ], [
            'phone.required' => 'Escribe el nuevo teléfono.',
            'phone.regex' => 'El teléfono debe contener exactamente 8 dígitos.',
        ]);

        try {
            $message = $this->api()->updatePhone($this->selectedClientId, $validated['phone']);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->applyApiErrors($exception, ['telefono' => 'phone']);

            return;
        }

        $this->phoneStatus = 'active';
        $this->phone = '';
        $this->resetValidation('phone');
        $this->dispatchToast($message !== '' ? $message : 'Teléfono actualizado correctamente.');
    }

    public function openClientForm(): void
    {
        $this->authorizeAbilities([
            'tickets.create-client',
            'tickets.departments',
            'tickets.municipalities',
        ]);

        $this->resetClientForm();
        $this->resetValidation();

        if (! $this->loadDepartments()) {
            return;
        }

        $this->showClientModal = true;
    }

    public function closeClientForm(): void
    {
        $this->showClientModal = false;
        $this->resetClientForm();
        $this->resetValidation();
    }

    public function updatedNewDepartment(string $departmentId): void
    {
        $this->newMunicipality = '';
        $this->municipalities = [];
    }

    public function createClient(): void
    {
        $this->authorizeAbilities([
            'tickets.create-client',
            'tickets.departments',
            'tickets.municipalities',
        ]);

        $validated = $this->validate([
            'newDocument' => ['required', 'string', 'max:13'],
            'newFirstName' => ['required', 'string', 'max:50'],
            'newSecondName' => ['required', 'string', 'max:50'],
            'newFirstSurname' => ['required', 'string', 'max:50'],
            'newSecondSurname' => ['required', 'string', 'max:50'],
            'newAge' => ['required', 'integer', 'min:1', 'max:120'],
            'newPhone' => ['required', 'regex:/^[0-9]{8}$/'],
            'newGender' => ['required', 'in:MASCULINO,FEMENINO'],
            'newDepartment' => ['required', 'integer', 'min:1'],
            'newMunicipality' => ['required', 'integer', 'min:1'],
        ], [
            'newDocument.required' => 'La identidad es obligatoria.',
            'newDocument.max' => 'La identidad no puede exceder 13 caracteres.',
            'newFirstName.required' => 'El primer nombre es obligatorio.',
            'newSecondName.required' => 'El segundo nombre es obligatorio.',
            'newFirstSurname.required' => 'El primer apellido es obligatorio.',
            'newSecondSurname.required' => 'El segundo apellido es obligatorio.',
            'newAge.required' => 'La edad es obligatoria.',
            'newAge.integer' => 'La edad debe ser un número entero.',
            'newAge.min' => 'La edad no es válida.',
            'newAge.max' => 'La edad no es válida.',
            'newPhone.required' => 'El teléfono es obligatorio.',
            'newPhone.regex' => 'El teléfono debe contener exactamente 8 dígitos.',
            'newGender.required' => 'Selecciona el género.',
            'newGender.in' => 'El género seleccionado no es válido.',
            'newDepartment.required' => 'Selecciona un departamento.',
            'newMunicipality.required' => 'Selecciona un municipio.',
        ]);

        $payload = [
            'docid' => trim($validated['newDocument']),
            'pnombre' => trim($validated['newFirstName']),
            'snombre' => trim($validated['newSecondName']),
            'papellido' => trim($validated['newFirstSurname']),
            'spaellido' => trim($validated['newSecondSurname']),
            'edad' => (int) $validated['newAge'],
            'telefono' => trim($validated['newPhone']),
            'genero' => $validated['newGender'],
            'departamento' => ['id' => (int) $validated['newDepartment']],
            'municipio' => ['id' => (int) $validated['newMunicipality']],
        ];

        try {
            $client = $this->normalizeClient($this->api()->createClient($payload));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->applyApiErrors($exception, [
                'docid' => 'newDocument',
                'pnombre' => 'newFirstName',
                'snombre' => 'newSecondName',
                'papellido' => 'newFirstSurname',
                'spaellido' => 'newSecondSurname',
                'edad' => 'newAge',
                'telefono' => 'newPhone',
                'genero' => 'newGender',
                'departamento.id' => 'newDepartment',
                'municipio.id' => 'newMunicipality',
            ]);

            return;
        }

        if ($client['id'] <= 0) {
            $this->dispatchAlert('No fue posible crear el cliente', 'La API no devolvió el cliente creado.');

            return;
        }

        $this->clients = collect([$client, ...$this->clients])
            ->unique('id')
            ->take(10)
            ->values()
            ->all();
        $this->selectedClientId = $client['id'];
        $this->selectedClientName = $client['name'];
        $this->selectedClientDocument = $client['document'];
        $this->phoneStatus = 'active';
        $this->showClientModal = false;
        $this->resetClientForm();
        $this->dispatchToast('Cliente creado y seleccionado correctamente.');
    }

    public function generateTicket(): void
    {
        $this->authorizeAbilities(['tickets.create', 'tickets.current-promotion', 'tickets.check-phone']);

        if ($this->selectedClientId === null) {
            $this->addError('selectedClientId', 'Selecciona un cliente.');

            return;
        }

        if ($this->phoneStatus !== 'active') {
            $this->addError('phone', 'Debes verificar o actualizar el teléfono antes de generar el ticket.');

            return;
        }

        $promotionId = (int) ($this->activePromotion['id'] ?? 0);
        $stockId = $this->stockId();

        if ($promotionId <= 0 || $stockId <= 0) {
            $this->dispatchAlert(
                'No se puede generar el ticket',
                'El sorteo activo o el stock de la sesión no son válidos.',
            );

            return;
        }

        if ($this->permissions()->allows('tickets.copies')) {
            $this->validate([
                'copies' => ['required', 'integer', 'min:1', 'max:10'],
            ], [
                'copies.required' => 'Indica el número de copias.',
                'copies.integer' => 'Las copias deben ser un número entero.',
                'copies.min' => 'Debe imprimirse al menos una copia.',
                'copies.max' => 'No se permiten más de 10 copias.',
            ]);
        } else {
            $this->copies = 1;
        }

        try {
            $ticket = $this->api()->createTicket($promotionId, $this->selectedClientId, $stockId);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return;
        } catch (ApiOperationException $exception) {
            $this->applyApiErrors($exception, [
                'promocion.id' => 'activePromotion',
                'cliente.id' => 'selectedClientId',
                'stock.id' => 'stock',
            ]);

            return;
        }

        $this->rememberTicket($ticket);
        $this->showTicketModal = false;
        $this->loadTickets(1);

        try {
            $this->printTicket($ticket, $this->copies);
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatchAlert(
                'Ticket creado, pero no impreso',
                'No fue posible enviar el ticket a la impresora predeterminada. Puedes usar “Reimprimir último ticket”.',
            );

            return;
        }

        $this->resetTicketSelection();
        $this->dispatchToast('Ticket generado y enviado a la impresora predeterminada.');
    }

    public function reprintLastTicket(): void
    {
        $this->authorizeAbility('tickets.copies');

        if ($this->lastTicketData === null) {
            $this->dispatchAlert('No hay ticket para reimprimir', 'Genera un ticket antes de usar esta opción.');

            return;
        }

        $this->validate([
            'copies' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $ticket = TicketPrintData::fromApi($this->lastTicketData);

        try {
            $this->printTicket($ticket, $this->copies);
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatchAlert('No fue posible reimprimir', 'Verifica la impresora predeterminada e inténtalo nuevamente.');

            return;
        }

        $this->dispatchToast('Ticket enviado nuevamente a la impresora.');
    }

    public function goToPage(int $page): void
    {
        if ($page < 1 || $page > $this->lastPage || $page === $this->page) {
            return;
        }

        $this->loadTickets($page);
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

        return view('livewire.sorteos.tickets', [
            'access' => [
                'generateTickets' => $permissions->allowsAll([
                    'tickets.create',
                    'tickets.current-promotion',
                    'tickets.check-phone',
                ]) && $permissions->allowsAny([
                    'tickets.clients',
                    'tickets.search-clients',
                    'tickets.create-client',
                ]),
                'searchClients' => $permissions->allows('tickets.search-clients'),
                'createClients' => $permissions->allowsAll([
                    'tickets.create-client',
                    'tickets.departments',
                    'tickets.municipalities',
                ]),
                'updatePhone' => $permissions->allows('tickets.update-phone'),
                'copies' => $permissions->allows('tickets.copies'),
            ],
            'stockName' => (string) session('auth.api.stockname', ''),
        ]);
    }

    private function loadTickets(int $page = 1): void
    {
        $this->authorizeAbility('tickets.list');
        $this->loadError = null;

        try {
            $this->applyPage($this->api()->list($page));
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            if ($exception->status === 404) {
                $this->applyEmptyPage();

                return;
            }

            if ($this->tickets === []) {
                $this->loadError = $exception->getMessage();
            } else {
                $this->dispatchAlert('No fue posible actualizar los tickets', $exception->getMessage());
            }
        }
    }

    private function loadCurrentPromotion(): bool
    {
        $this->authorizeAbility('tickets.current-promotion');
        $this->promotionError = null;

        try {
            $this->activePromotion = $this->normalizePromotion($this->api()->currentPromotion());
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();

            return false;
        } catch (ApiOperationException $exception) {
            $this->activePromotion = null;
            $this->promotionError = $exception->status === 404
                ? 'No hay un sorteo activo disponible.'
                : $exception->getMessage();

            return false;
        }

        return (int) ($this->activePromotion['id'] ?? 0) > 0;
    }

    private function loadClients(int $page = 1): void
    {
        $this->authorizeAbility('tickets.clients');

        try {
            $this->loadClientChoices('ticket-clients', ['page' => max(1, $page)]);
        } catch (ApiSessionExpiredException) {
            $this->redirectToLogin();
        } catch (ApiOperationException $exception) {
            if ($exception->status === 404) {
                $this->clients = [];

                return;
            }

            $this->dispatchAlert('No fue posible cargar los clientes', $exception->getMessage());
        }
    }

    private function loadDepartments(): bool
    {
        return true;
    }

    private function loadClientChoices(string $source, array $input): void
    {
        $response = app(SelectorApiService::class)->page($source, $input);
        $this->clients = collect($response['data'])
            ->map(fn (array $item): array => $this->normalizeClient($item['record']))
            ->filter(fn (array $client): bool => $client['id'] > 0)->take(10)->values()->all();
        $this->clientPage = (int) $response['meta']['current_page'];
        $this->clientLastPage = (int) $response['meta']['last_page'];
    }

    private function applyPage(PaginatedCollectionData $page): void
    {
        $this->tickets = collect($page->items)
            ->map(fn (array $ticket): array => $this->normalizeTicket($ticket))
            ->filter(fn (array $ticket): bool => $ticket['id'] > 0)
            ->values()
            ->all();
        $this->total = $page->total;
        $this->page = $page->currentPage;
        $this->lastPage = $page->lastPage;
    }

    private function applyEmptyPage(): void
    {
        $this->tickets = [];
        $this->total = 0;
        $this->page = 1;
        $this->lastPage = 1;
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
     * @param  array<string, mixed>  $promotion
     * @return array<string, mixed>
     */
    private function normalizePromotion(array $promotion): array
    {
        return [
            'id' => (int) ($promotion['id'] ?? 0),
            'nombre' => (string) ($promotion['nombre'] ?? $promotion['name'] ?? ''),
            'fecha_fin' => $this->dateValue($promotion['fecha_fin'] ?? $promotion['end_date'] ?? ''),
            'valor' => (float) ($promotion['valor'] ?? 0),
            'impresiones' => max(1, (int) ($promotion['impresiones'] ?? 1)),
        ];
    }

    /**
     * @param  array<string, mixed>  $client
     * @return array{id: int, document: string, name: string, phone: string}
     */
    private function normalizeClient(array $client): array
    {
        return [
            'id' => (int) ($client['id'] ?? 0),
            'document' => (string) ($client['docid'] ?? $client['document'] ?? ''),
            'name' => $this->clientName($client),
            'phone' => (string) ($client['telefono'] ?? $client['phone'] ?? ''),
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
                return [
                    'id' => (string) $this->identifier($item),
                    'label' => $this->description($item),
                ];
            })
            ->filter(fn (array $item): bool => $item['id'] !== '')
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $client */
    private function clientName(array $client): string
    {
        $parts = [
            $client['pnombre'] ?? $client['first_name'] ?? '',
            $client['snombre'] ?? $client['second_name'] ?? '',
            $client['papellido'] ?? $client['first_surname'] ?? '',
            $client['spaellido'] ?? $client['second_surname'] ?? '',
        ];
        $name = trim(implode(' ', array_filter($parts, fn (mixed $part): bool => is_scalar($part) && trim((string) $part) !== '')));

        return $name !== '' ? $name : $this->description($client);
    }

    private function identifier(mixed $value): int|string
    {
        if (is_array($value)) {
            foreach (['id', 'value', 'codigo', 'code'] as $key) {
                if (is_scalar($value[$key] ?? null)) {
                    return is_numeric($value[$key]) ? (int) $value[$key] : (string) $value[$key];
                }
            }
        }

        return is_scalar($value) ? (string) $value : '';
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

    private function dateValue(mixed $value): string
    {
        return is_scalar($value) ? substr((string) $value, 0, 10) : '';
    }

    private function dateTimeValue(mixed $value): string
    {
        return is_scalar($value) ? str_replace('T', ' ', substr((string) $value, 0, 16)) : '';
    }

    private function resetTicketSelection(): void
    {
        $this->clientPage = $this->clientLastPage = 1;
        $this->clientSearch = '';
        $this->selectedClientId = null;
        $this->selectedClientName = '';
        $this->selectedClientDocument = '';
        $this->phoneStatus = 'unchecked';
        $this->phone = '';
        $this->clients = [];
    }

    private function resetClientForm(): void
    {
        $this->newDocument = '';
        $this->newFirstName = '';
        $this->newSecondName = '';
        $this->newFirstSurname = '';
        $this->newSecondSurname = '';
        $this->newAge = '';
        $this->newPhone = '';
        $this->newGender = '';
        $this->newDepartment = '';
        $this->newMunicipality = '';
        $this->municipalities = [];
    }

    private function rememberTicket(TicketPrintData $ticket): void
    {
        $this->lastTicketData = $ticket->toArray();
    }

    private function printTicket(TicketPrintData $ticket, int $copies): void
    {
        $printer = app(TicketPrinter::class);

        $this->dispatch('ticket:print', html: $printer->render($ticket, $copies));
    }

    /**
     * @param  array<string, string>  $fieldMap
     */
    private function applyApiErrors(ApiOperationException $exception, array $fieldMap = []): void
    {
        foreach ($exception->errors as $field => $messages) {
            $property = $fieldMap[$field] ?? $field;

            foreach ((array) $messages as $message) {
                $this->addError($property, (string) $message);
            }
        }

        if ($exception->errors === []) {
            $this->dispatchAlert('No fue posible completar la operación', $exception->getMessage());
        }
    }

    private function stockId(): int
    {
        return (int) session('auth.api.stockid', 0);
    }

    private function api(): TicketApiService
    {
        return app(TicketApiService::class);
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
