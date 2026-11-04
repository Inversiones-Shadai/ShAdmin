<x-layouts::auth.pharmacy :title="__('Iniciar sesión')">
    <div class="pharmacy-login-form-container">
        <div class="pharmacy-login-access-label">
            <span aria-hidden="true"></span>
            ACCESO AL SISTEMA
        </div>
        <h1 id="login-heading">Bienvenido de nuevo</h1>
        <p class="pharmacy-login-form-description">Ingresa tus credenciales para comenzar tu jornada.</p>

        <x-auth-session-status class="mt-6" :status="session('status')" />

        <form
            method="POST"
            action="{{ route('login.store') }}"
            class="pharmacy-login-form"
            x-data="{ submitting: false }"
            x-on:submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
            x-bind:aria-busy="submitting"
        >
            @csrf

            <div class="**:data-flux-field:w-full **:data-flux-control:w-full">
                <flux:input
                    name="name"
                    label="Usuario"
                    :value="old('name')"
                    type="text"
                    icon="user"
                    required
                    autofocus
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="Ingresa tu usuario"
                />
            </div>

            <div class="**:data-flux-field:w-full **:data-flux-control:w-full">
                <flux:input
                    name="password"
                    label="Contraseña"
                    type="password"
                    icon="lock-closed"
                    required
                    viewable
                    autocomplete="current-password"
                    placeholder="Ingresa tu contraseña"
                />
            </div>

            <button type="submit" class="pharmacy-login-submit" data-test="login-button" x-bind:disabled="submitting">
                <span x-text="submitting ? 'Ingresando…' : 'Iniciar sesión'">Iniciar sesión</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M5 12h14m-6-6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </form>

        <div class="pharmacy-login-help">
            <p>¿Necesitas ayuda para ingresar?</p>
            <span>Contacta al administrador de tu farmacia.</span>
        </div>
        <div class="pharmacy-login-privacy">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <path d="m12 3 8 3v6c0 4-3.5 7-8 9-4.5-2-8-5-8-9V6l8-3Z" stroke-linejoin="round" />
                <path d="m8.5 12 2.5 2.5 4.5-5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span>Acceso exclusivo para personal autorizado.</span>
        </div>
    </div>
</x-layouts::auth.pharmacy>
