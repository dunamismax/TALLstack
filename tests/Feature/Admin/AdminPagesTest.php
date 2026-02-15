<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;

function createUserWithRole(string $roleSlug): User
{
    $user = User::factory()->create();
    $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

    $user->syncRoles([$role->id]);

    return $user->fresh();
}

test('guests are redirected when visiting admin pages', function () {
    $this->get(route('admin.users'))->assertRedirect(route('login'));
    $this->get(route('admin.roles'))->assertRedirect(route('login'));
});

test('authenticated users without permissions are forbidden from admin pages', function () {
    $this->seed(AccessControlSeeder::class);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.roles'))
        ->assertForbidden();
});

test('users with admin role can access admin pages', function () {
    $this->seed(AccessControlSeeder::class);

    $admin = createUserWithRole('admin');
    $adminRole = $admin->roles()->where('slug', 'admin')->firstOrFail();

    expect($admin->can('manage-users'))->toBeTrue();
    expect($admin->can('manage-roles'))->toBeTrue();

    $this->assertDatabaseHas('model_has_roles', [
        'role_id' => $adminRole->id,
        'model_id' => $admin->id,
        'model_type' => User::class,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get(route('admin.roles'))
        ->assertSuccessful();
});
