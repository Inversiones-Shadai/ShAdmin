<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['appName' => 'Farmacias Shadai'])
    </head>
    <body class="pharmacy-login antialiased">
        <div class="pharmacy-login-page">
            <header class="pharmacy-login-masthead">
                <span class="pharmacy-login-product"><span aria-hidden="true">+</span> ShAdmin</span>
                <span>Portal del equipo</span>
            </header>

            <main class="pharmacy-login-shell">
                <aside class="pharmacy-login-brand" aria-label="Farmacias Shadai">
                    <a href="{{ route('home') }}" class="pharmacy-login-logo" wire:navigate>
                        <img
                            src="{{ asset('images/shadai-logo.png') }}"
                            alt="Farmacias Shadai"
                            width="826"
                            height="279"
                        />
                    </a>

                    <div class="pharmacy-login-intro">
                        <p class="pharmacy-login-eyebrow">CERCA DE TI, CADA DÍA</p>
                        <h2>Tu farmacia.<br><span>Todo en orden.</span></h2>
                        <p class="pharmacy-login-description">La gestión de tu farmacia en un solo lugar, para que tu equipo se concentre en lo que más importa.</p>
                    </div>

                    <svg class="pharmacy-login-art" viewBox="0 0 480 360" fill="none" aria-hidden="true">
                        <circle cx="300" cy="220" r="151" stroke="currentColor" stroke-opacity=".12" />
                        <circle cx="300" cy="220" r="112" stroke="currentColor" stroke-opacity=".1" />
                        <path d="M276 144h48v52h52v48h-52v52h-48v-52h-52v-48h52z" fill="currentColor" fill-opacity=".055" stroke="currentColor" stroke-opacity=".24" />
                        <circle cx="171" cy="141" r="5" fill="currentColor" fill-opacity=".5" />
                        <circle cx="407" cy="113" r="3" fill="currentColor" fill-opacity=".5" />
                        <path d="M114 269h18m-9-9v18M408 291h14m-7-7v14" stroke="currentColor" stroke-opacity=".4" stroke-width="2" />
                    </svg>

                    <div class="pharmacy-login-brand-footer">
                        <span class="pharmacy-login-rule"></span>
                        <p>Inventario <span aria-hidden="true">·</span> Operaciones <span aria-hidden="true">·</span> Reportes</p>
                        <span>Un equipo. Una misma misión.</span>
                    </div>
                </aside>

                <section class="pharmacy-login-access" aria-labelledby="login-heading">
                    {{ $slot }}
                </section>
            </main>

            <footer class="pharmacy-login-page-footer">
                <span>&copy; {{ date('Y') }} Farmacias Shadai</span>
                <span>Comprometidos con tu bienestar.</span>
            </footer>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
