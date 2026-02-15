<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;

function createApiUserWithRole(string $roleSlug): User
{
    $user = User::factory()->create();
    $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

    $user->roles()->sync([$role->id]);

    return $user;
}

test('admin api blocks users without permissions', function () {
    $this->seed(AccessControlSeeder::class);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson('/api/v1/admin/roles')
        ->assertForbidden();
});

test('admin api supports user lifecycle for authorized admins', function () {
    $this->seed(AccessControlSeeder::class);

    $admin = createApiUserWithRole('admin');
    $analystRole = Role::query()->where('slug', 'analyst')->firstOrFail();

    $createResponse = $this->actingAs($admin)
        ->postJson('/api/v1/admin/users', [
            'name' => 'Template User',
            'email' => 'template.user@example.com',
            'password' => 'password',
            'role_ids' => [$analystRole->id],
        ]);

    $createResponse
        ->assertCreated()
        ->assertJsonPath('data.email', 'template.user@example.com');

    $createdUserId = (int) $createResponse->json('data.id');

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/users/{$createdUserId}", [
            'name' => 'Updated Template User',
            'email' => 'updated.template.user@example.com',
            'role_ids' => [$analystRole->id],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Template User');

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/users/{$createdUserId}")
        ->assertNoContent();

    $this->assertDatabaseMissing('users', [
        'id' => $createdUserId,
        'email' => 'updated.template.user@example.com',
    ]);
});

test('admin api supports role creation for authorized admins', function () {
    $this->seed(AccessControlSeeder::class);

    $admin = createApiUserWithRole('admin');
    $permissionIds = Permission::query()->whereIn('slug', ['view-dashboard', 'manage-users'])->pluck('id')->all();

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/admin/roles', [
            'name' => 'Support Admin',
            'slug' => 'support-admin',
            'description' => 'Support operations and user triage.',
            'permission_ids' => $permissionIds,
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.slug', 'support-admin');

    $this->assertDatabaseHas('roles', [
        'slug' => 'support-admin',
        'name' => 'Support Admin',
    ]);
});
