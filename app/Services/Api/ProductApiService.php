<?php

namespace App\Services\Api;

use App\Data\Api\ApiResponseData;
use App\Data\Api\PaginatedCollectionData;
use App\Exceptions\ApiConfigurationException;
use App\Exceptions\ApiOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

use function is_array;
use function is_scalar;
use function is_string;

class ProductApiService
{
    /** @var array<string, string> */
    private const ACTIONS = [
        'list' => 'prdbtn01',
        'create' => 'prdbtn02',
        'create-child' => 'prdbtn19',
        'search' => 'prdbtn03',
        'update' => 'prdbtn04',
        'delete' => 'prdbtn05',
        'image' => 'prdbtn06',
        'options' => 'prdbtn07',
        'active-list' => 'prdbtn08',
        'active-create' => 'prdbtn09',
        'active-search' => 'prdbtn10',
        'active-update' => 'prdbtn11',
        'active-delete' => 'prdbtn12',
    ];

    public function __construct(
        private readonly ApiClient $client,
        private readonly ApiSessionGuard $sessionGuard,
    ) {}

    public function list(int $page = 1, string $search = ''): PaginatedCollectionData
    {
        return $this->page(
            $this->send('get', '/listar', ['page' => max(1, $page)] + (trim($search) === '' ? [] : ['search' => trim($search)]), 'list'),
            ['products', 'productos'],
        );
    }

    public function parents(string $search, int $page = 1, ?int $exclude = null): PaginatedCollectionData
    {
        return $this->page($this->send('get', '/padres', [
            'search' => trim($search), 'page' => max(1, $page), 'exclude' => $exclude,
        ], 'options'), ['products', 'productos']);
    }

    public function listLaboratories(int $page = 1): PaginatedCollectionData
    {
        return $this->catalogPage('laboratorios', [], $page);
    }

    public function filterLaboratories(string $search, int $page = 1): PaginatedCollectionData
    {
        $search = trim($search);

        return $this->catalogPage('laboratorios', $search === '' ? [] : [
            ['key' => 'nombre', 'value' => $search],
        ], $page);
    }

    public function listFamilies(int $page = 1): PaginatedCollectionData
    {
        return $this->catalogPage('familias', [], $page);
    }

    public function familyPickerPage(string $search = '', int $page = 1): PaginatedCollectionData
    {
        $result = $this->page($this->send('get', '/familias', [
            'page' => max(1, $page),
            'per_page' => 10,
            'search' => trim($search),
        ], 'options'), ['familias']);
        $items = [];
        foreach ($result->items as $record) {
            $id = (int) ($record['id'] ?? 0);
            if ($id < 1) {
                continue;
            }

            $label = (string) ($record['nombre'] ?? $record['descripcion'] ?? $record['label'] ?? '#'.$id);
            $items[] = [
                'id' => $id,
                'nombre' => $label,
                'label' => $label,
                'presentacion' => $this->familyRelationLabel($record['presentacion'] ?? null),
                'administracion' => $this->familyRelationLabel($record['administracion'] ?? null),
            ];
        }

        return new PaginatedCollectionData($items, $result->total, $result->currentPage, $result->lastPage, $result->meta);
    }

    public function filterFamilies(string $search, int $page = 1): PaginatedCollectionData
    {
        $search = trim($search);

        return $this->catalogPage('familias', $search === '' ? [] : [
            ['key' => 'descripcion', 'value' => $search],
        ], $page);
    }

    public function listUnits(int $page = 1): PaginatedCollectionData
    {
        return $this->catalogPage('unidades', [], $page);
    }

    public function activePickerPage(string $search = '', int $page = 1): PaginatedCollectionData
    {
        return $this->page($this->send('get', '/principios_activos', [
            'page' => max(1, $page), 'per_page' => 10, 'search' => trim($search),
        ], 'options'), ['principios_activos']);
    }

    public function filterUnits(string $search, int $page = 1): PaginatedCollectionData
    {
        $search = trim($search);

        return $this->catalogPage('unidades', $search === '' ? [] : [
            ['key' => 'abreviatura_c', 'value' => $search],
            ['key' => 'abreviatura_v', 'value' => $search],
        ], $page);
    }

    /** @param list<array{key: string, value: string}> $filterItems */
    private function catalogPage(string $catalog, array $filterItems, int $page): PaginatedCollectionData
    {
        $parameters = ['page' => max(1, $page)];
        $path = '/'.$catalog;
        if ($filterItems !== []) {
            $parameters['filterItems'] = $filterItems;
            $path = '/filter_'.$catalog;
        }

        return $this->page(
            $this->send('get', $path, $parameters, 'options',
                (string) config('services.shadmin_api.product_lookup_path', '/farma/productos/producto')),
            [$catalog],
        );
    }

    public function advancedSearch(
        int $page = 1,
        string $search = '',
        ?int $laboratory = null,
        ?int $provider = null,
    ): PaginatedCollectionData {
        $parameters = [
            'page' => max(1, $page),
            'per_page' => 10,
        ];

        $search = trim($search);

        if ($search !== '') {
            $parameters['search'] = $search;
        }

        if ($laboratory !== null && $laboratory > 0) {
            $parameters['laboratorio'] = $laboratory;
        }

        if ($provider !== null && $provider > 0) {
            $parameters['proveedor'] = $provider;
        }

        return $this->page(
            $this->send('get', '/listar', $parameters, 'search'),
            ['products', 'productos'],
        );
    }

    /**
     * @param  array<int, string>|null  $catalogs
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function options(bool $includeLaboratories = true, ?array $catalogs = null): array
    {
        $options = [];
        foreach ($catalogs ?? ['categorias', 'laboratorios', 'unidades', 'familias', 'estados', 'principios_activos'] as $catalog) {
            if ($catalog === 'laboratorios' && ! $includeLaboratories) {
                continue;
            }

            $options[$catalog] = [];
            $page = 1;
            do {
                $result = $this->page(
                    $this->send('get', '/'.$catalog, ['all' => 1, 'page' => $page], 'options'),
                    [$catalog],
                );
                foreach ($result->items as $record) {
                    $id = (int) ($record['id'] ?? 0);
                    $label = (string) ($record['label'] ?? $record['nombre'] ?? $record['descripcion'] ?? $record['valor'] ?? '');
                    if ($catalog === 'unidades') {
                        $label = trim(($record['abreviatura_c'] ?? '').' / '.($record['abreviatura_v'] ?? ''), ' /');
                    }
                    if ($catalog === 'principios_activos') {
                        $label = trim($label.' '.data_get($record, 'concentracion_detalle.valor', ''));
                    }
                    if ($catalog === 'familias') {
                        foreach (['presentacion', 'administracion'] as $field) {
                            $record[$field] = $this->familyRelationLabel($record[$field] ?? null);
                        }
                    }
                    if ($id > 0) {
                        $options[$catalog][] = [...$record, 'id' => $id, 'label' => $label !== '' ? $label : '#'.$id];
                    }
                }
                $page++;
            } while ($page <= $result->lastPage);
        }

        return $options;
    }

    private function familyRelationLabel(mixed $value): ?string
    {
        if (is_array($value)) {
            foreach (['descripcion', 'nombre', 'label', 'valor'] as $key) {
                if (is_scalar($value[$key] ?? null)) {
                    return (string) $value[$key];
                }
            }

            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload): string
    {
        return $this->send('post', '/crear', $this->withRelations($payload), 'create')->message;
    }

    public function createChild(int $parentId, string $description): string
    {
        $this->ensureValidId($parentId);

        return $this->send('post', "/crear-hijo/{$parentId}", ['descripcion' => $description], 'create-child')->message;
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        $this->ensureValidId($id);

        return $this->record(
            $this->send('get', "/buscar/{$id}", [], 'search')->data,
        );
    }

    /** @param array<string, mixed> $payload */
    public function update(int $id, array $payload): string
    {
        $this->ensureValidId($id);
        $payload['id'] = $id;

        $message = $this->send('put', "/actualizar/{$id}", $this->withRelations($payload), 'update')->message;
        $this->forgetImage($id);

        return $message;
    }

    public function delete(int $id): string
    {
        $this->ensureValidId($id);

        $message = $this->send('delete', "/eliminar/{$id}", [], 'delete')->message;
        $this->forgetImage($id);

        return $message;
    }

    public function image(int $id): string
    {
        $this->ensureValidId($id);
        $key = $this->imageCacheKey($id);
        $cached = session('products.last_image');

        if ($key !== null && is_array($cached)
            && ($cached['key'] ?? null) === $key
            && ($cached['expires_at'] ?? 0) > now()->timestamp
            && is_string($cached['image'] ?? null)) {
            return $cached['image'];
        }

        $data = $this->send('get', "/imagen/{$id}", [], 'image')->data;

        if (is_array($data) && is_string($data['image'] ?? null)) {
            if ($key !== null) {
                // Keep only the latest image so the session cannot grow with the catalog.
                session()->put('products.last_image', [
                    'key' => $key,
                    'image' => $data['image'],
                    'expires_at' => now()->addMinutes(5)->timestamp,
                ]);
            }

            return $data['image'];
        }

        throw new ApiOperationException('La API no devolvió una imagen válida.');
    }

    private function imageCacheKey(int $id): ?string
    {
        $token = session('auth.api.token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        return hash('sha256', config('services.shadmin_api.base_url').'|'.$this->path("/imagen/{$id}").'|'.$token);
    }

    private function forgetImage(int $id): void
    {
        if (session('products.last_image.key') === $this->imageCacheKey($id)) {
            session()->forget('products.last_image');
        }
    }

    public function listActives(int $page = 1): PaginatedCollectionData
    {
        return $this->page(
            $this->send('get', '/listar', ['page' => max(1, $page)], 'active-list', $this->activePath()),
            ['actives', 'activos', 'associations', 'asociaciones'],
        );
    }

    /** @param array{producto: int, pactivo: int} $payload */
    public function createActive(array $payload): string
    {
        return $this->send('post', '/crear', $payload, 'active-create', $this->activePath())->message;
    }

    /** @return array<string, mixed> */
    public function findActive(int $id): array
    {
        $this->ensureValidId($id);

        return $this->record(
            $this->send('get', "/buscar/{$id}", [], 'active-search', $this->activePath())->data,
        );
    }

    /** @param array{producto: int, pactivo: int} $payload */
    public function updateActive(int $id, array $payload): string
    {
        $this->ensureValidId($id);
        $payload['id'] = $id;

        return $this->send('put', "/update/{$id}", $payload, 'active-update', $this->activePath())->message;
    }

    public function deleteActive(int $id): string
    {
        $this->ensureValidId($id);

        return $this->send('delete', "/eliminar/{$id}", [], 'active-delete', $this->activePath())->message;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $path, array $payload, string $action, ?string $basePath = null): ApiResponseData
    {
        $token = session('auth.api.token');

        if (! is_string($token) || $token === '') {
            throw new ApiOperationException('La sesión API no está disponible. Inicia sesión nuevamente.');
        }

        try {
            $request = $this->client->request()
                ->withToken($token)
                ->withHeader('Action-Btn', self::ACTIONS[$action]);
            $response = $this->dispatch($request, $method, $this->path($path, $basePath), $payload);
        } catch (ApiConfigurationException $exception) {
            throw new ApiOperationException($exception->getMessage());
        } catch (ConnectionException) {
            throw new ApiOperationException('No se pudo conectar con el servidor.');
        }

        $body = $response->json();
        $this->sessionGuard->ensureActive($response, $body);

        if (! is_array($body)) {
            throw new ApiOperationException('La API devolvió una respuesta inválida.', status: $response->status());
        }

        $envelope = ApiResponseData::fromApi($body);
        $code = $envelope->code > 0 ? $envelope->code : $response->status();

        if (! $response->successful() || $code < 200 || $code >= 300) {
            throw new ApiOperationException(
                $envelope->message !== '' ? $envelope->message : 'La operación no pudo completarse.',
                is_array(($body['errors'] ?? data_get($body, 'data.errors'))) ? ($body['errors'] ?? data_get($body, 'data.errors')) : [],
                $code,
            );
        }

        return $envelope;
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(PendingRequest $request, string $method, string $path, array $payload): Response
    {
        return match ($method) {
            'get' => $request->get($path, $payload),
            'post' => $request->post($path, $payload),
            'put' => $request->put($path, $payload),
            'delete' => $request->delete($path, $payload),
            default => throw new ApiOperationException('Método HTTP no soportado.'),
        };
    }

    private function path(string $suffix, ?string $basePath = null): string
    {
        $prefix = $basePath ?? (string) config('services.shadmin_api.product_path', '/farma/productos/producto');

        return '/'.trim($prefix, '/').'/'.ltrim($suffix, '/');
    }

    /**
     * @param  array<int, string>  $collectionKeys
     */
    private function page(ApiResponseData $response, array $collectionKeys): PaginatedCollectionData
    {
        $meta = $response->meta;

        if ($meta === null && is_array($response->data) && ! array_is_list($response->data)) {
            $meta = is_array($response->data['meta'] ?? null)
                ? $response->data['meta']
                : $response->data;
        }

        return PaginatedCollectionData::fromApi(
            $this->collection($response->data, $collectionKeys),
            $meta,
        );
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    private function collection(mixed $data, array $keys): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return array_values(array_filter($data, is_array(...)));
        }

        foreach (['data', 'items', 'results', ...$keys] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->collection($data[$key], $keys);
            }
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function record(mixed $data): array
    {
        if (! is_array($data)) {
            throw new ApiOperationException('La API no devolvió los datos del producto.');
        }

        foreach (['data', 'record', 'registro', 'product', 'producto'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->record($data[$key]);
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function withRelations(array $payload): array
    {
        foreach (['categoria', 'laboratorio', 'unidad', 'familia', 'estado'] as $key) {
            if (isset($payload[$key]) && ! is_array($payload[$key])) {
                $payload[$key] = ['id' => (int) $payload[$key]];
            }
        }

        return $payload;
    }

    private function activePath(): string
    {
        return (string) config('services.shadmin_api.product_active_path', '/farma/productos/productos/activos');
    }

    private function ensureValidId(int $id): void
    {
        if ($id < 1) {
            throw new ApiOperationException('El identificador no es válido.');
        }
    }
}
