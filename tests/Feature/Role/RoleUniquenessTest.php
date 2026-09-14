<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Covers a real bug: unlike Permissions, StoreRoleRequest/UpdateRoleRequest
 * had no uniqueness rule on title at all, and roles.title had no DB
 * constraint either -- two roles could be created with the exact same
 * title.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    $role = Role::create(['title' => 'Role Tester']);

    $permissionIds = collect(['roles.view', 'roles.create', 'roles.update'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);
});

test('creating a role with a duplicate title is rejected', function () {
    Role::create(['title' => 'Duplicate Role']);

    $response = $this->postJson('/api/roles', [
        'title' => 'Duplicate Role',
        'permission_ids' => [],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('title');
});

test('updating a role to another role\'s title is rejected', function () {
    $roleA = Role::create(['title' => 'Role A']);
    $roleB = Role::create(['title' => 'Role B']);

    $response = $this->putJson("/api/roles/{$roleB->id}", [
        'title' => 'Role A',
        'permission_ids' => [],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('title');
});

test('updating a role to its own unchanged title is allowed', function () {
    $role = Role::create(['title' => 'Role C']);

    $response = $this->putJson("/api/roles/{$role->id}", [
        'title' => 'Role C',
        'permission_ids' => [],
    ]);

    $response->assertSuccessful();
});
