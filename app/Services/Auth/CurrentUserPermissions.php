<?php

namespace App\Services\Auth;

use App\Support\Auth\ApiPermissionIndex;
use Illuminate\Support\Facades\Route;

use function is_array;
use function is_string;

final class CurrentUserPermissions
{
    public const string GATE = 'api-permission';

    public function allows(string $ability): bool
    {
        $codes = config("api_permissions.abilities.{$ability}");

        if (! is_array($codes) || $codes === []) {
            return false;
        }

        [$module, $view, $action] = array_pad($codes, 3, null);

        return $this->has(
            (string) $module,
            is_string($view) ? $view : null,
            is_string($action) ? $action : null,
        );
    }

    public function hasModule(string $module): bool
    {
        return $this->has($module);
    }

    public function hasView(string $module, string $view): bool
    {
        return $this->has($module, $view);
    }

    public function hasAction(string $module, string $view, string $action): bool
    {
        return $this->has($module, $view, $action);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    public function allowsAll(array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if (! $this->allows($ability)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $abilities
     */
    public function allowsAny(array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if ($this->allows($ability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Devuelve únicamente módulos y vistas que tienen una pantalla disponible.
     *
     * @return array<int, array{
     *     code: string,
     *     label: string,
     *     icon: string,
     *     views: array<int, array{
     *         code: string,
     *         label: string,
     *         icon: string,
     *         route: string,
     *         routePattern: string,
     *         group: null|array{code: string, label: string, icon: string}
     *     }>
     * }>
     */
    public function navigation(): array
    {
        $catalog = config('api_permissions.catalog', []);
        $navigation = [];

        if (! is_array($catalog)) {
            return [];
        }

        foreach ($catalog as $moduleCode => $module) {
            if (! is_string($moduleCode) || ! is_array($module) || ! $this->hasModule($moduleCode)) {
                continue;
            }

            $visibleViews = [];
            $views = $module['views'] ?? [];

            if (! is_array($views)) {
                continue;
            }

            foreach ($views as $viewCode => $view) {
                if (! is_string($viewCode) || ! is_array($view)) {
                    continue;
                }

                if (! $this->hasView($moduleCode, $viewCode)) {
                    continue;
                }

                $route = $view['route'] ?? null;

                if (! is_string($route) || $route === '' || ! Route::has($route)) {
                    continue;
                }

                $ability = $view['ability'] ?? null;

                if (is_string($ability) && ! $this->allows($ability)) {
                    continue;
                }

                $group = null;
                $groupCode = $view['group'] ?? null;
                $groups = $module['groups'] ?? [];

                if (is_string($groupCode) && is_array($groups) && is_array($groups[$groupCode] ?? null)) {
                    $group = [
                        'code' => $groupCode,
                        'label' => (string) ($groups[$groupCode]['label'] ?? $groupCode),
                        'icon' => (string) ($groups[$groupCode]['icon'] ?? 'folder'),
                    ];
                }

                $visibleViews[] = [
                    'code' => $viewCode,
                    'label' => (string) ($view['label'] ?? $viewCode),
                    'icon' => (string) ($view['icon'] ?? 'rectangle-stack'),
                    'route' => $route,
                    'routePattern' => (string) ($view['route-pattern'] ?? $route),
                    'group' => $group,
                ];
            }

            if ($visibleViews === []) {
                continue;
            }

            $navigation[] = [
                'code' => $moduleCode,
                'label' => (string) ($module['label'] ?? $moduleCode),
                'icon' => (string) ($module['icon'] ?? 'squares-2x2'),
                'views' => $visibleViews,
            ];
        }

        return $navigation;
    }

    private function has(string $module, ?string $view = null, ?string $action = null): bool
    {
        if (! $this->usesApiAuthentication()) {
            return true;
        }

        return isset($this->abilities()[ApiPermissionIndex::key($module, $view, $action)]);
    }

    /**
     * @return array<string, true>
     */
    private function abilities(): array
    {
        $abilities = session('auth.api.abilities');

        if (is_array($abilities)) {
            return $abilities;
        }

        $permissions = session('auth.api.spermisos', []);

        return ApiPermissionIndex::build(is_array($permissions) ? $permissions : []);
    }

    private function usesApiAuthentication(): bool
    {
        return filled(config('services.shadmin_api.base_url'));
    }
}
