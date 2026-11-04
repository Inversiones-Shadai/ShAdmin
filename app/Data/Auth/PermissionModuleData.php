<?php

namespace App\Data\Auth;

use Illuminate\Support\Collection;

class PermissionModuleData
{
    /**
     * @param  Collection<int, PermissionViewData>  $views
     */
    public function __construct(
        public readonly string $code,
        public readonly Collection $views,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        $module = is_array($payload['modulo_l'] ?? null) ? $payload['modulo_l'] : [];
        $views = is_array($payload['vistas_l'] ?? null) ? $payload['vistas_l'] : [];

        return new self(
            code: (string) ($module['codigo'] ?? ''),
            views: collect($views)
                ->filter(fn ($view) => is_array($view))
                ->map(fn (array $view) => PermissionViewData::fromApi($view))
                ->values(),
        );
    }

    /**
     * @return array{modulo_l: array{codigo: string}, vistas_l: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'modulo_l' => [
                'codigo' => $this->code,
            ],
            'vistas_l' => $this->views
                ->map(fn (PermissionViewData $view) => $view->toArray())
                ->values()
                ->all(),
        ];
    }
}
