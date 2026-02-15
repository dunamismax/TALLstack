<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Gate::before(function (User $user): ?bool {
            if ($user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });

        $corePermissionAbilities = ['view-dashboard', 'manage-users', 'manage-roles', 'manage-settings'];

        foreach ($corePermissionAbilities as $permissionSlug) {
            Gate::define($permissionSlug, function (User $user) use ($permissionSlug): bool {
                if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_user') || ! Schema::hasTable('permission_role')) {
                    return false;
                }

                return $user->hasPermission($permissionSlug);
            });
        }
    }
}
