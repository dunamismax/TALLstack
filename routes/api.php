<?php

use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:web', 'verified', 'throttle:admin-api'])
    ->prefix('v1/admin')
    ->name('api.v1.admin.')
    ->group(function (): void {
        Route::get('dashboard', function (): JsonResponse {
            Gate::authorize('view-dashboard');

            return response()->json([
                'data' => [
                    'users_count' => User::query()->count(),
                    'roles_count' => Role::query()->count(),
                    'permissions_count' => Permission::query()->count(),
                    'verified_users_count' => User::query()->whereNotNull('email_verified_at')->count(),
                ],
            ]);
        })->name('dashboard');

        Route::apiResource('users', UserController::class)
            ->middleware('permission:manage-users');

        Route::apiResource('roles', RoleController::class)
            ->middleware('permission:manage-roles');
    });
