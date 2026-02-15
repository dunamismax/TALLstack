<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionPayloads = [
            [
                'name' => 'View Dashboard',
                'slug' => 'view-dashboard',
                'description' => 'View admin dashboard analytics and summaries.',
            ],
            [
                'name' => 'Manage Users',
                'slug' => 'manage-users',
                'description' => 'Create, edit, and remove user accounts.',
            ],
            [
                'name' => 'Manage Roles',
                'slug' => 'manage-roles',
                'description' => 'Create, edit, and assign role permissions.',
            ],
            [
                'name' => 'Manage Settings',
                'slug' => 'manage-settings',
                'description' => 'Manage privileged settings and preferences.',
            ],
        ];

        foreach ($permissionPayloads as $permissionPayload) {
            Permission::query()->updateOrCreate(
                ['slug' => $permissionPayload['slug']],
                $permissionPayload,
            );
        }

        $permissionsBySlug = Permission::query()->get()->keyBy('slug');

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
                    'description' => $rolePayload['description'],
                    'is_system' => $rolePayload['is_system'],
                ],
            );

            $permissionIds = collect($rolePayload['permission_slugs'])
                ->map(fn (string $permissionSlug): ?int => $permissionsBySlug->get($permissionSlug)?->id)
                ->filter()
                ->values()
                ->all();

            $role->permissions()->sync($permissionIds);
        }

        $superAdminRole = Role::query()->where('slug', 'super-admin')->first();
        $analystRole = Role::query()->where('slug', 'analyst')->first();

        if ($superAdminRole !== null) {
            User::query()
                ->oldest('id')
                ->first()
                ?->roles()
                ->syncWithoutDetaching([$superAdminRole->id]);
        }

        if ($analystRole !== null) {
            User::query()
                ->whereDoesntHave('roles')
                ->get()
                ->each(function (User $user) use ($analystRole): void {
                    $user->roles()->syncWithoutDetaching([$analystRole->id]);
                });
        }
    }
}
