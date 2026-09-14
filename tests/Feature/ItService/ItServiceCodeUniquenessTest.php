<?php

use App\Models\ItService;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Covers a real data-integrity gap surfaced during review: the dev DB had
 * two different services ("Software Assistance" and "System
 * Troubleshooting") sharing code "SA" despite StoreItServiceRequest already
 * validating uniqueness at the app level. The colliding row was reassigned
 * a distinct code and it_services.code now has a DB-level unique
 * constraint too, so a duplicate can't slip in even if app validation is
 * ever bypassed.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    $role = Role::create(['title' => 'IT Service Tester']);

    $permissionIds = collect(['it_services.view', 'it_services.create', 'it_services.update'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);
});

test('creating an it service with a duplicate code is rejected', function () {
    ItService::factory()->create(['code' => 'DUP']);

    $response = $this->postJson('/api/it-services', [
        'name' => 'Another Service',
        'description' => 'Some description',
        'code' => 'DUP',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('code');
});

test('updating an it service to its own unchanged code is allowed', function () {
    $itService = ItService::factory()->create(['code' => 'OWN']);

    $response = $this->putJson("/api/it-services/{$itService->id}", [
        'name' => $itService->name,
        'description' => $itService->description,
        'code' => 'OWN',
    ]);

    $response->assertSuccessful();
});
