<x-layouts::app :title="__('Inicio')">
    @php
        $lobbyUserName = session('auth.api.uname') ?: auth()->user()->name;
        $lobbyModules = app(App\Services\Auth\CurrentUserPermissions::class)->navigation();
    @endphp
    <div class="pharmacy-lobby">
        <section class="pharmacy-lobby-hero" aria-labelledby="lobby-heading">
            <div class="pharmacy-lobby-copy">
                <p class="pharmacy-lobby-eyebrow"><span aria-hidden="true"></span> FARMACIAS SHADAI</p>
                <p class="pharmacy-lobby-greeting">Bienvenido, {{ $lobbyUserName }}</p>
                <h1 id="lobby-heading">Una buena jornada<br><span>empieza aquí.</span></h1>
                <p class="pharmacy-lobby-description">Tu equipo, tu farmacia y todo lo que necesitas para cuidar cada detalle de la operación.</p>
                <div class="pharmacy-lobby-signature">
                    <span aria-hidden="true">+</span>
                    <p>Cerca de ti, cada día.<small>Un equipo. Una misma misión.</small></p>
                </div>
            </div>
            <div class="pharmacy-lobby-visual" x-data="{ paused: false }" x-bind:class="{ 'is-paused': paused }">
                <x-pharmacy-motion />
                <button
                    type="button"
                    class="pharmacy-motion-toggle"
                    x-on:click="paused = !paused"
                    x-bind:aria-pressed="paused"
                    x-bind:aria-label="paused ? 'Reanudar animación' : 'Pausar animación'"
                    aria-label="Pausar animación"
                >
                    <svg x-show="!paused" viewBox="0 0 16 16" aria-hidden="true"><path d="M5 3v10M11 3v10" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    <svg x-show="paused" x-cloak viewBox="0 0 16 16" aria-hidden="true"><path d="m5 3 8 5-8 5Z" fill="currentColor" /></svg>
                    <span x-text="paused ? 'Reanudar' : 'Pausar'">Pausar</span>
                </button>
            </div>
        </section>

        <section class="pharmacy-lobby-workspace" aria-labelledby="workspace-heading">
            <div class="pharmacy-lobby-section-heading">
                <div>
                    <p>TU ESPACIO DE TRABAJO</p>
                    <h2 id="workspace-heading">¿Por dónde comenzamos?</h2>
                </div>
                <span>Selecciona un módulo para continuar.</span>
            </div>
            <div class="pharmacy-lobby-modules">
                @forelse ($lobbyModules as $module)
                    <a href="{{ route($module['views'][0]['route']) }}" class="pharmacy-lobby-module" wire:navigate>
                        <span class="pharmacy-lobby-module-icon"><flux:icon :name="$module['icon']" /></span>
                        <span class="pharmacy-lobby-module-label">{{ $module['label'] }}<small>Entrar al módulo</small></span>
                        <flux:icon.arrow-up-right class="pharmacy-lobby-module-arrow" />
                    </a>
                @empty
                    <p class="pharmacy-lobby-empty">Tus accesos aparecerán aquí cuando el administrador asigne los módulos de tu cuenta.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts::app>
