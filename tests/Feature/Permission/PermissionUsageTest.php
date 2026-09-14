<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Covers the usage() endpoint added for the Permissions delete-warning UI:
 * permission_role cascades on permission_id, so deleting a permission
 * doesn't destroy anything -- it just unassigns it from every role that
 * has it. usage() surfaces that role count so the frontend can warn before
 * confirming the delete.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    $role = Role::create(['title' => 'Permission Tester']);

    $permissionIds = collect(['permissions.view', 'permissions.delete'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);
});

test('usage reports zero when a permission is not assigned to any role', function () {
    $permission = Permission::create(['title' => 'unused.permission']);

    $response = $this->getJson("/api/permissions/{$permission->id}/usage");

    $response->assertSuccessful();
    $response->assertJson(['roles_count' => 0]);
});

test('usage reports the number of roles a permission is assigned to', function () {
    $permission = Permission::create(['title' => 'shared.permission']);
    $roleA = Role::create(['title' => 'Role A']);
    $roleB = Role::create(['title' => 'Role B']);

    $permission->roles()->attach([$roleA->id, $roleB->id]);

    $response = $this->getJson("/api/permissions/{$permission->id}/usage");

    $response->assertSuccessful();
    $response->assertJson(['roles_count' => 2]);
});

test('deleting a permission unassigns it from roles instead of being blocked', function () {
    $permission = Permission::create(['title' => 'shared.permission.two']);
    $role = Role::create(['title' => 'Role C']);
    $permission->roles()->attach($role->id);

    $response = $this->deleteJson("/api/permissions/{$permission->id}");

    $response->assertSuccessful();
    expect(Permission::find($permission->id))->toBeNull();
    expect($role->permissions()->count())->toBe(0);
});
