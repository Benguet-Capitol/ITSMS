<?php

use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;

function createUserWithProfile(array $overrides = []): User
{
    $user = User::factory()->create($overrides);

    Profile::create([
        'user_id' => $user->id,
        'display_name' => 'Test User',
        'name' => ['firstname' => 'Test', 'lastname' => 'User'],
        'gender' => 'male',
        'designation' => 'Tester',
        'engagement' => 'ready',
    ]);

    return $user;
}

/**
 * Covers a real bug: UpdateUserRequest validated `status`, but
 * UserController::update()'s own field-whitelisting loop (email/username/
 * role only) silently dropped it before the update() call, so changing a
 * user's status via the Update modal appeared to succeed but never
 * actually persisted.
 */
function updateUserPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'username' => $user->username,
        'email' => $user->email,
        'role' => Role::first()->id,
        'display_name' => 'Test User',
        'name' => json_encode(['firstname' => 'Test', 'lastname' => 'User']),
        'gender' => 'male',
        'designation' => 'Tester',
        'status' => 'active',
    ], $overrides);
}

beforeEach(function () {
    $this->actor = User::factory()->create();

    $role = Role::create(['title' => 'User Manager']);

    $permissionIds = collect(['users.view', 'users.update'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);
});

test('updating a user to inactive actually persists the status', function () {
    $target = createUserWithProfile(['status' => 'active']);

    $response = $this->putJson(
        "/api/users/{$target->id}",
        updateUserPayload($target, ['status' => 'inactive'])
    );

    $response->assertSuccessful();

    expect($target->fresh()->status)->toBe('inactive');
});

test('updating a user back to active actually persists the status', function () {
    $target = createUserWithProfile(['status' => 'inactive']);

    $response = $this->putJson(
        "/api/users/{$target->id}",
        updateUserPayload($target, ['status' => 'active'])
    );

    $response->assertSuccessful();

    expect($target->fresh()->status)->toBe('active');
});
