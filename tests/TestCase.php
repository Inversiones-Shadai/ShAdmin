<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function fakeSelectorChoice(string $source, array $record): void
    {
        $label = $record['label'] ?? $record['nombre'] ?? $record['descripcion'] ?? $record['valor'] ?? (string) $record['id'];
        Http::fake(['*/selectors/'.$source.'*' => Http::response([
            'code' => 200, 'data' => [['id' => (string) $record['id'], 'label' => $label, 'record' => $record + ['label' => $label]]],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 1],
        ])]);
    }

    protected function fakeSelectorsFromLegacy(array $routes): void
    {
        foreach ($routes as $source => $path) {
            Http::fake(['*/selectors/'.$source.'*' => function ($request) use ($path) {
                [$path, $key] = array_pad(explode('|', $path, 2), 2, null);
                $parent = $request['filters']['parent'] ?? '';
                $product = $request['filters']['product'] ?? '';
                $response = Http::get('https://api.example.test/api/'.str_replace(['{parent}', '{product}'], [$parent, $product], $path));
                $rows = $response->json($key ? 'data.'.$key : 'data') ?? [];
                $selected = $request['selected'] ?? null;
                $search = mb_strtolower((string) ($request['search'] ?? ''));
                $items = array_values(array_filter($rows, fn ($row) => $selected !== null
                    ? (string) ($row['id'] ?? '') === (string) $selected
                    : ($search === '' || str_contains(mb_strtolower(implode(' ', array_filter($row, 'is_scalar'))), $search))));
                $items = array_slice($items, 0, 10);

                return Http::response(['code' => 200, 'data' => array_map(function ($record) {
                    $label = $record['label'] ?? $record['nombre'] ?? $record['descripcion'] ?? $record['lote'] ?? (string) $record['id'];

                    return ['id' => (string) $record['id'], 'label' => $label, 'record' => $record + ['label' => $label]];
                }, $items), 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($items)]]);
            }]);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config(['services.shadmin_api.device_ip' => '10.10.0.25', 'services.shadmin_api.device_name' => 'WEB-TEST']);

    }

    protected function tearDown(): void
    {
        try {
            $routes = json_decode(file_get_contents(__DIR__.'/Fixtures/api-routes.json'), true, flags: JSON_THROW_ON_ERROR);
            foreach (Http::recorded() as [$request]) {
                $path = ltrim((string) parse_url($request->url(), PHP_URL_PATH), '/');
                if (! str_starts_with($path, 'api/')) {
                    continue;
                }
                $matched = false;
                foreach ($routes as $route) {
                    $pattern = preg_replace('/\\\\\{[^}]+\\\\\}/', '[^/]+', preg_quote($route['uri'], '#'));
                    if (in_array($request->method(), $route['methods'], true) && preg_match('#^'.$pattern.'$#', $path)) {
                        $matched = true;
                        break;
                    }
                }
                $this->assertTrue($matched, 'La API no registra '.$request->method().' /'.$path.'. Regenera tests/Fixtures/api-routes.json si cambió el backend.');
            }
        } finally {
            parent::tearDown();
        }
    }

    /** @return array<string, mixed> */
    protected function productOptionResponses(array $envelope): array
    {
        $responses = [];
        foreach (['categorias', 'laboratorios', 'unidades', 'familias', 'estados', 'principios_activos'] as $catalog) {
            $responses['api.example.test/api/farma/productos/producto/'.$catalog.'*'] = Http::response([
                'message' => $envelope['message'],
                'code' => $envelope['code'],
                'data' => $envelope['data'][$catalog] ?? [],
                'meta' => ['current_page' => 1, 'last_page' => 1],
            ]);
        }

        return $responses;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
