<?php

namespace App\Support\Auth;

use Illuminate\Support\Str;

use function is_array;

final class ApiPermissionIndex
{
    /**
     * @param  array<int, mixed>  $permissions
     * @return array<string, true>
     */
    public static function build(array $permissions): array
    {
        $abilities = [];

        foreach ($permissions as $modulePermission) {
            if (! is_array($modulePermission)) {
                continue;
            }

            $module = self::normalize(data_get($modulePermission, 'modulo_l.codigo'));

            if ($module === '') {
                continue;
            }

            $abilities[self::key($module)] = true;

            $views = $modulePermission['vistas_l'] ?? [];

            if (! is_array($views)) {
                continue;
            }

            foreach ($views as $viewPermission) {
                if (! is_array($viewPermission)) {
                    continue;
                }

                $view = self::normalize(data_get($viewPermission, 'vistai.codigo'));

                if ($view === '') {
                    continue;
                }

                $abilities[self::key($module, $view)] = true;

                $actions = $viewPermission['accionesi'] ?? [];

                if (! is_array($actions)) {
                    continue;
                }

                foreach ($actions as $actionPermission) {
                    if (! is_array($actionPermission)) {
                        continue;
                    }

                    $action = self::normalize($actionPermission['codigo'] ?? null);

                    if ($action !== '') {
                        $abilities[self::key($module, $view, $action)] = true;
                    }
                }
            }
        }

        return $abilities;
    }

    public static function key(string $module, ?string $view = null, ?string $action = null): string
    {
        return implode('|', array_map(
            self::normalize(...),
            array_filter([$module, $view, $action], static fn (?string $code): bool => $code !== null),
        ));
    }

    private static function normalize(mixed $code): string
    {
        return Str::lower(trim((string) $code));
    }
}
