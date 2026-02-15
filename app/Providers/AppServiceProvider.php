<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
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
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureAuthorization();
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
            : null
        );
    }

    /**
     * Configure authorization gates derived from persisted permissions.
     */
    protected function configureAuthorization(): void
    {
        if (! $this->hasAccessControlTables()) {
            return;
        }

        Gate::before(function (User $user): ?bool {
            if ($user->roles()->where('slug', 'super-admin')->exists()) {
                return true;
            }

            return null;
        });

        Gate::define('viewPulse', fn (User $user): bool => $user->can('manage-settings'));
    }

    protected function hasAccessControlTables(): bool
    {
        try {
            return Schema::hasTable('permissions')
                && Schema::hasTable('roles')
                && Schema::hasTable('model_has_roles')
                && Schema::hasTable('role_has_permissions');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Configure API rate limiters.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('admin-api', function (Request $request): array {
            $userLimit = Limit::perMinute(60)
                ->by('admin-api:user:'.($request->user()?->getAuthIdentifier() ?? 'guest'))
                ->response(function (Request $request, array $headers): JsonResponse {
                    return response()->json([
                        'message' => 'Too many requests. Please retry in a minute.',
                    ], Response::HTTP_TOO_MANY_REQUESTS, $headers);
                });

            $ipLimit = Limit::perMinute(240)
                ->by('admin-api:ip:'.$request->ip())
                ->response(function (Request $request, array $headers): JsonResponse {
                    return response()->json([
                        'message' => 'Too many requests. Please retry in a minute.',
                    ], Response::HTTP_TOO_MANY_REQUESTS, $headers);
                });

            return [$userLimit, $ipLimit];
        });
    }
}
