<?php

namespace App\Data\Auth;

use Illuminate\Support\Collection;

use function is_array;

class PermissionViewData
{
    /**
     * @param  Collection<int, PermissionActionData>  $actions
     */
    public function __construct(
        public readonly string $code,
        public readonly Collection $actions,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        $view = is_array($payload['vistai'] ?? null) ? $payload['vistai'] : [];
        $actions = is_array($payload['accionesi'] ?? null) ? $payload['accionesi'] : [];

        return new self(
            code: (string) ($view['codigo'] ?? ''),
            actions: collect($actions)
                ->filter(fn ($action) => is_array($action))
                ->map(fn (array $action) => PermissionActionData::fromApi($action))
                ->values(),
        );
    }

    /**
     * @return array{vistai: array{codigo: string}, accionesi: array<int, array{codigo: string}>}
     */
    public function toArray(): array
    {
        return [
            'vistai' => [
                'codigo' => $this->code,
            ],
            'accionesi' => $this->actions
                ->map(fn (PermissionActionData $action) => $action->toArray())
                ->values()
                ->all(),
        ];
    }
}
