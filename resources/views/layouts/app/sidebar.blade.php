@props(['fixedViewport' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $apiUserName = session('auth.api.uname');
        $apiUserRole = session('auth.api.urol');
        $apiStockName = session('auth.api.stockname');
        $headerUserName = is_string($apiUserName) && $apiUserName !== ''
            ? $apiUserName
            : auth()->user()->name;
        $headerUserRole = is_string($apiUserRole) && $apiUserRole !== ''
            ? $apiUserRole
            : 'Usuario';
        $headerStockName = is_string($apiStockName) && trim($apiStockName) !== ''
            ? trim($apiStockName)
            : 'Sin stock asignado';
        $headerUserInitials = Illuminate\Support\Str::initials($headerUserName, true);
        $permissionNavigation = app(App\Services\Auth\CurrentUserPermissions::class)->navigation();
    @endphp

    <head>
        @include('partials.head', ['appName' => 'Farmacias Shadai'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-100">
        <div @class(['lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]', 'h-dvh overflow-hidden' => $fixedViewport, 'min-h-screen' => ! $fixedViewport])>
            <aside class="hidden border-e border-slate-200 bg-white text-slate-700 lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200">
                <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-5 dark:border-slate-800">
                    <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" class="!px-0" wire:navigate />
                </div>


                <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                    @foreach ($permissionNavigation as $module)
                        @php
                            $moduleIsCurrent = collect($module['views'])
                                ->contains(fn (array $view): bool => request()->routeIs($view['routePattern']));
                            $ungroupedViews = collect($module['views'])
                                ->filter(fn (array $view): bool => ! is_array($view['group'] ?? null));
                            $groupedViews = collect($module['views'])
                                ->filter(fn (array $view): bool => is_array($view['group'] ?? null))
                                ->groupBy(fn (array $view): string => $view['group']['code']);
                        @endphp

                        <details
                            class="group permission-menu-accordion"
                            data-animated-menu
                            data-test="{{ $module['code'] === 'cfg' ? 'config-accordion' : 'permission-module-'.$module['code'] }}"
                            data-permission-module="{{ $module['code'] }}"
                            @if ($moduleIsCurrent) open @endif
                        >
                            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors duration-200 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white [&::-webkit-details-marker]:hidden">
                                <flux:icon :name="$module['icon']" class="size-5" />
                                <span class="flex-1">{{ $module['label'] }}</span>
                                <flux:icon.chevron-down class="permission-menu-chevron size-4 group-open:rotate-180" />
                            </summary>

                            <div class="permission-menu-panel mt-1 space-y-1 ps-4">
                                @foreach ($ungroupedViews as $view)
                                    <a
                                        href="{{ route($view['route']) }}"
                                        wire:navigate
                                        class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium {{ request()->routeIs($view['routePattern']) ? 'bg-cyan-500 text-slate-950 shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white' }}"
                                        data-test="permission-view-{{ $module['code'] }}-{{ $view['code'] }}"
                                    >
                                        <flux:icon :name="$view['icon']" class="size-5" />
                                        {{ $view['label'] }}
                                    </a>
                                @endforeach

                                @foreach ($groupedViews as $groupViews)
                                    @php
                                        $group = $groupViews->first()['group'];
                                        $groupIsCurrent = $groupViews
                                            ->contains(fn (array $view): bool => request()->routeIs($view['routePattern']));
                                    @endphp
                                    <details
                                        class="group/product permission-submenu-accordion"
                                        data-animated-menu
                                        data-test="permission-submenu-{{ $module['code'] }}-{{ $group['code'] }}"
                                        @if ($groupIsCurrent) open @endif
                                    >
                                        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors duration-200 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white [&::-webkit-details-marker]:hidden">
                                            <flux:icon :name="$group['icon']" class="size-5" />
                                            <span class="flex-1">{{ $group['label'] }}</span>
                                            <flux:icon.chevron-down class="permission-menu-chevron size-4 group-open/product:rotate-180" />
                                        </summary>

                                        <div class="permission-menu-panel mt-1 space-y-1 ps-4">
                                            @foreach ($groupViews as $view)
                                                <a
                                                    href="{{ route($view['route']) }}"
                                                    wire:navigate
                                                    class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium {{ request()->routeIs($view['routePattern']) ? 'bg-cyan-500 text-slate-950 shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white' }}"
                                                    data-test="permission-view-{{ $module['code'] }}-{{ $view['code'] }}"
                                                >
                                                    <flux:icon :name="$view['icon']" class="size-5" />
                                                    {{ $view['label'] }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </nav>
            </aside>

            <div @class(['min-w-0', 'flex h-full min-h-0 flex-col' => $fixedViewport])>
                <header class="sticky top-0 z-10 shrink-0 border-b border-slate-200 bg-white/95 backdrop-blur lg:z-30 dark:border-slate-800 dark:bg-slate-900/95">
                    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

                        <div class="ml-auto flex items-center gap-2">
                            <x-theme-switcher />
                            @persist('work-lunch-controls')
                                <livewire:auth.work-lunch-controls lazy="on-load" />
                            @endpersist

                            <div
                                data-test="header-stock-info"
                                class="flex min-w-0 items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 dark:border-slate-700 dark:bg-slate-950"
                                title="Stock asignado: {{ $headerStockName }}"
                            >
                                <span class="grid size-7 shrink-0 place-items-center rounded-md bg-cyan-100 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300">
                                    <flux:icon.stock class="size-4" />
                                </span>
                                <span class="min-w-0 text-left">
                                    <span class="block text-[10px] font-semibold uppercase leading-none tracking-wide text-slate-400">
                                        Stock
                                    </span>
                                    <span class="mt-1 block max-w-32 truncate text-xs font-medium leading-none text-slate-700 sm:max-w-48 dark:text-slate-200">
                                        {{ $headerStockName }}
                                    </span>
                                </span>
                            </div>
                            <div data-test="header-user-identity" class="hidden items-center gap-3 sm:flex">
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-cyan-100 text-xs font-semibold text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300">
                                    {{ $headerUserInitials }}
                                </span>
                                <span class="min-w-0 text-right">
                                    <span class="block max-w-48 truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                                        {{ $headerUserName }}
                                    </span>
                                    <span class="block max-w-48 truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ $headerUserRole }}
                                    </span>
                                </span>
                            </div>

                            <flux:dropdown position="bottom" align="end">
                                <flux:button
                                    icon="ellipsis-vertical"
                                    variant="ghost"
                                    square
                                    aria-label="Opciones de sesión"
                                    data-test="session-options-button"
                                />

                                <flux:menu>
                                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                                        @csrf
                                        <flux:menu.item
                                            as="button"
                                            type="submit"
                                            icon="arrow-right-start-on-rectangle"
                                            class="w-full cursor-pointer"
                                            data-test="logout-button"
                                        >
                                            Cerrar sesión
                                        </flux:menu.item>
                                    </form>

                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                </header>

                <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
                    <flux:sidebar.header class="min-h-14">
                        <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                        <flux:sidebar.collapse />
                    </flux:sidebar.header>

                    <flux:sidebar.nav>
                        <flux:sidebar.group heading="Principal">
                            <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                                Dashboard
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                        @foreach ($permissionNavigation as $module)
                            @php
                                $ungroupedViews = collect($module['views'])
                                    ->filter(fn (array $view): bool => ! is_array($view['group'] ?? null));
                                $groupedViews = collect($module['views'])
                                    ->filter(fn (array $view): bool => is_array($view['group'] ?? null))
                                    ->groupBy(fn (array $view): string => $view['group']['code']);
                            @endphp
                            <flux:sidebar.group
                                expandable
                                :heading="$module['label']"
                                :icon="$module['icon']"
                                :expanded="collect($module['views'])->contains(fn (array $view): bool => request()->routeIs($view['routePattern']))"
                                class="permission-menu-mobile-group"
                            >
                                @foreach ($ungroupedViews as $view)
                                    <flux:sidebar.item
                                        :icon="$view['icon']"
                                        :href="route($view['route'])"
                                        :current="request()->routeIs($view['routePattern'])"
                                        wire:navigate
                                    >
                                        {{ $view['label'] }}
                                    </flux:sidebar.item>
                                @endforeach

                                @foreach ($groupedViews as $groupViews)
                                    @php($group = $groupViews->first()['group'])
                                    <flux:sidebar.group
                                        expandable
                                        :heading="$group['label']"
                                        :icon="$group['icon']"
                                        :expanded="$groupViews->contains(fn (array $view): bool => request()->routeIs($view['routePattern']))"
                                        class="permission-submenu-mobile-group"
                                    >
                                        @foreach ($groupViews as $view)
                                            <flux:sidebar.item
                                                :icon="$view['icon']"
                                                :href="route($view['route'])"
                                                :current="request()->routeIs($view['routePattern'])"
                                                wire:navigate
                                            >
                                                {{ $view['label'] }}
                                            </flux:sidebar.item>
                                        @endforeach
                                    </flux:sidebar.group>
                                @endforeach
                            </flux:sidebar.group>
                        @endforeach
                    </flux:sidebar.nav>
                </flux:sidebar>

                <main @class(['px-4 py-6 sm:px-6 lg:px-8', 'flex! min-h-0 flex-1 flex-col' => $fixedViewport])>
                    {{ $slot }}
                </main>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @persist('global-image-viewer')
            <x-global-image-viewer />
        @endpersist

        @fluxScripts
    </body>
</html>
