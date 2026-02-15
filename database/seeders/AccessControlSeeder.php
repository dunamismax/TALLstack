<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class AccessControlSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionPayloads = [
            [
                'name' => 'view-dashboard',
                'slug' => 'view-dashboard',
                'description' => 'View admin dashboard analytics and summaries.',
            ],
            [
                'name' => 'manage-users',
                'slug' => 'manage-users',
                'description' => 'Create, edit, and remove user accounts.',
            ],
            [
                'name' => 'manage-roles',
                'slug' => 'manage-roles',
                'description' => 'Create, edit, and assign role permissions.',
            ],
            [
                'name' => 'manage-settings',
                'slug' => 'manage-settings',
                'description' => 'Manage privileged settings and preferences.',
            ],
        ];

        foreach ($permissionPayloads as $permissionPayload) {
            Permission::query()->updateOrCreate(
                ['slug' => $permissionPayload['slug']],
                [
                    'name' => $permissionPayload['name'],
                    'guard_name' => 'web',
                    'description' => $permissionPayload['description'],
                ],
            );
        }

        $permissionsBySlug = Permission::query()
            ->whereIn('slug', collect($permissionPayloads)->pluck('slug')->all())
            ->get()
            ->keyBy('slug');

        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Unrestricted platform access.',
                'is_system' => true,
                'permission_slugs' => $permissionsBySlug->keys()->all(),
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Administrative access for user and role operations.',
                'is_system' => true,
                'permission_slugs' => ['view-dashboard', 'manage-users', 'manage-roles'],
            ],
            [
                'name' => 'Analyst',
                'slug' => 'analyst',
                'description' => 'Read-only dashboard access.',
                'is_system' => true,
                'permission_slugs' => ['view-dashboard'],
            ],
        ];

        foreach ($roles as $rolePayload) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $rolePayload['slug']],
                [
                    'name' => $rolePayload['name'],
                    'guard_name' => 'web',
                    'description' => $rolePayload['description'],
                    'is_system' => $rolePayload['is_system'],
                ],
            );

            $permissions = collect($rolePayload['permission_slugs'])
                ->map(fn (string $permissionSlug): ?Permission => $permissionsBySlug->get($permissionSlug))
                ->filter()
                ->values();

            $role->syncPermissions($permissions);
        }

        $superAdminRole = Role::query()->where('slug', 'super-admin')->first();
        $analystRole = Role::query()->where('slug', 'analyst')->first();

        if ($superAdminRole !== null) {
            User::query()
                ->oldest('id')
                ->first()
                ?->assignRole($superAdminRole);
        }

        if ($analystRole !== null) {
            User::query()
                ->whereDoesntHave('roles')
                ->get()
                ->each(function (User $user) use ($analystRole): void {
                    $user->assignRole($analystRole);
                });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
