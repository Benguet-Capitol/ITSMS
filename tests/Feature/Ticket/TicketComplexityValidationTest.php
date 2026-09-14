<?php

use App\Models\ItService;
use App\Models\ItemType;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\TicketComplexityLevel;
use App\Models\User;

/**
 * `complexity_level_id` replaced the old free-text `priority`/`complexity`
 * field and is now a real foreign key into the admin-managed
 * `ticket_complexity_levels` table, validated with `exists`, instead of a
 * fixed PHP enum.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    Profile::create([
        'user_id' => $this->actor->id,
        'display_name' => 'Complexity Tester',
        'name' => ['firstname' => 'Complexity', 'lastname' => 'Tester'],
        'gender' => 'male',
        'designation' => 'Tester',
        'engagement' => 'ready',
    ]);

    $role = Role::create(['title' => 'Complexity Tester']);

    $permissionIds = collect(['tickets.create'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);

    $this->itService = ItService::factory()->create();
    $this->itemType = ItemType::factory()->create();
});

test('a non-existent complexity_level_id is rejected', function () {
    $response = $this->postJson('/api/tickets', [
        'profile_id' => $this->actor->profile->id,
        'it_service_id' => $this->itService->id,
        'item_type_id' => $this->itemType->id,
        'concern' => 'Test concern',
        'query_status' => 'queued',
        'service_method' => 'on_site',
        'is_other_agency' => false,
        'complexity_level_id' => 999999,
        'office_id' => 'OFF-1',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('complexity_level_id');
});

test('a real complexity level id is accepted and its data is returned', function () {
    $level = TicketComplexityLevel::factory()->create(['label' => 'Urgent']);

    $response = $this->postJson('/api/tickets', [
        'profile_id' => $this->actor->profile->id,
        'it_service_id' => $this->itService->id,
        'item_type_id' => $this->itemType->id,
        'concern' => 'Test concern',
        'query_status' => 'queued',
        'service_method' => 'on_site',
        'is_other_agency' => false,
        'complexity_level_id' => $level->id,
        'office_id' => 'OFF-1',
    ]);

    $response->assertSuccessful();
    expect($response->json('complexity_level.id'))->toBe($level->id);
    expect($response->json('complexity_level.label'))->toBe('Urgent');
});
