<?php

namespace App\Livewire\Concerns;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Services\Api\SelectorApiService;

trait RemembersSelectorChoice
{
    public function rememberSelectorChoice(string $source, string $id): array
    {
        $binding = config('selector_bindings')[static::class][$source] ?? null;
        abort_unless($binding && preg_match('/^[1-9][0-9]{0,19}$/', $id), 403);
        $input = ['selected' => $id];
        if (isset($binding['parent'])) {
            $input['filters'] = ['parent' => data_get($this, $binding['parent'])];
        }
        if (isset($binding['product'])) {
            $input['filters']['product'] = data_get($this, $binding['product']);
        }
        try {
            $response = app(SelectorApiService::class)->page($source, $input);
            $item = $response['data'][0] ?? null;
            if (! is_array($item) || (string) $item['id'] !== $id || ! is_array($item['record'] ?? null) || (string) ($item['record']['id'] ?? '') !== $id) {
                return ['ok' => false, 'message' => 'La opción ya no está disponible. Actualiza la búsqueda.'];
            }
            if ($source === 'transfer-children' && $this->matrixStockId !== null && ! isset($this->matrixProducts[(int) $item['id']])) {
                return ['ok' => false, 'message' => 'Selecciona un hijo incluido en la matriz.'];
            }
            data_set($this, $binding['cache'], [$item['record']]);

            return ['ok' => true];
        } catch (ApiSessionExpiredException) {
            $this->redirectRoute('login');

            return ['ok' => false, 'message' => 'La sesión terminó.'];
        } catch (ApiOperationException $error) {
            return ['ok' => false, 'message' => $error->getMessage()];
        }
    }
}
