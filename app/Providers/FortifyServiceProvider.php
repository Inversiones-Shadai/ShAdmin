<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Data\Auth\AuthLoginData;
use App\Models\User;
use App\Services\Api\AuthApiService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    private const AUTHENTICATED_USER_ATTRIBUTE = 'shadmin.authenticated_user';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(fn () => view('pages::auth.register'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure authentication.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            if (blank(config('services.shadmin_api.base_url'))) {
                return $this->authenticateLocalUser($request);
            }

            // Una cookie persistente no puede restaurar el token de la sesión remota.
            $request->merge(['remember' => false]);

            $authenticatedUser = $request->attributes->get(self::AUTHENTICATED_USER_ATTRIBUTE);

            if ($authenticatedUser instanceof User) {
                return $authenticatedUser;
            }

            try {
                $credentials = AuthLoginData::fromRequest($request);
                $apiSession = app(AuthApiService::class)->login($credentials);
            } catch (AuthenticationException $exception) {
                throw ValidationException::withMessages([
                    Fortify::username() => [$exception->getMessage()],
                ]);
            }

            $identity = $apiSession->toSession();
            $identity['web_user_id'] = random_int(1, PHP_INT_MAX);
            $request->session()->put('auth.api', $identity);

            $authenticatedUser = (new User)->forceFill([
                'id' => $identity['web_user_id'],
                'name' => $apiSession->userName !== '' ? $apiSession->userName : $credentials->name,
                'email' => '',
            ]);

            $request->attributes->set(self::AUTHENTICATED_USER_ATTRIBUTE, $authenticatedUser);

            return $authenticatedUser;
        });
    }

    private function authenticateLocalUser(Request $request): ?User
    {
        $login = (string) $request->input(Fortify::username());

        $user = User::where('name', $login)
            ->orWhere('email', $login)
            ->first();

        if ($user && Hash::check((string) $request->input('password'), $user->password)) {
            return $user;
        }

        return null;
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $name = $request->input(Fortify::username());
            $throttleKey = hash('sha256', Str::transliterate(Str::lower(is_string($name) ? trim($name) : '')).'|'.$request->ip());

            return Limit::perMinute(config('security.rate_limits.login_per_minute'))->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
