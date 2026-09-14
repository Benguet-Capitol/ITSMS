<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Covers the usage() endpoint added for the Roles delete-warning UI:
 * role_user cascades on role_id, so deleting a role doesn't destroy
 * anything -- it just unassigns it (and every permission it carries) from
 * every user who has it. usage() surfaces that user count so the frontend
 * can warn before confirming the delete.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    $role = Role::create(['title' => 'Role Tester']);

    $permissionIds = collect(['roles.view', 'roles.delete'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);
});

test('usage reports zero when a role is not assigned to any user', function () {
    $role = Role::create(['title' => 'Unused Role']);

    $response = $this->getJson("/api/roles/{$role->id}/usage");

    $response->assertSuccessful();
    $response->assertJson(['users_count' => 0]);
});

test('usage reports the number of users assigned to a role', function () {
    $role = Role::create(['title' => 'Shared Role']);
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $role->users()->attach([$userA->id, $userB->id]);

    $response = $this->getJson("/api/roles/{$role->id}/usage");

    $response->assertSuccessful();
    $response->assertJson(['users_count' => 2]);
});

test('deleting a role unassigns it from users instead of being blocked', function () {
    $role = Role::create(['title' => 'Shared Role Two']);
    $user = User::factory()->create();
    $role->users()->attach($user->id);

    $response = $this->deleteJson("/api/roles/{$role->id}");

    $response->assertSuccessful();
    expect(Role::find($role->id))->toBeNull();
    expect($user->roles()->count())->toBe(0);
});
