<?php

namespace App\Providers;

use App\Auth\ApiSessionUserProvider;
use App\Console\Commands\ServeShAdmin;
use App\Http\Middleware\EnsureLocalAccount;
use App\Http\Responses\SetDevelopmentHtmlContentLength;
use App\Listeners\RevokeApiSession;
use App\Models\User;
use App\Services\Auth\CurrentUserPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->extend(ServeCommand::class, fn () => new ServeShAdmin);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('shadmin', fn ($app): ApiSessionUserProvider => new ApiSessionUserProvider($app['hash']));
        $this->configureDefaults();
        $this->configureAuthorization();
        Event::listen(Logout::class, RevokeApiSession::class);
        Livewire::addPersistentMiddleware([EnsureLocalAccount::class]);

        // Livewire injects assets on RequestHandled, after response middleware.
        // Register last so the local server advertises the final HTML byte count.
        $this->app->booted(fn () => Event::listen(RequestHandled::class, SetDevelopmentHtmlContentLength::class));

        // Livewire keeps styles/scripts but reinserts preload links on every visit.
        // Assets already loaded by the document need no new preload hints.
        Vite::usePreloadTagAttributes(
            fn (): array|false => request()->hasHeader('X-Livewire-Navigate') ? false : [],
        );
    }

    private function configureAuthorization(): void
    {
        Gate::define(
            CurrentUserPermissions::GATE,
            fn (User $user, string $ability): bool => app(CurrentUserPermissions::class)->allows($ability),
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
