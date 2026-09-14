<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketComplexityLevel;
use App\Models\User;

/**
 * tickets.complexity_level_id is restrictOnDelete() at the DB level, but
 * destroy() should still surface a clean 422 with a descriptive message
 * before that constraint ever fires, matching the guard pattern already
 * used for Measurement Units.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    $role = Role::create(['title' => 'Complexity Level Tester']);

    $permissionIds = collect(['ticket_complexity_levels.view', 'ticket_complexity_levels.delete'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);
});

test('deleting a complexity level still in use is blocked', function () {
    $level = TicketComplexityLevel::factory()->create();
    Ticket::factory()->create(['complexity_level_id' => $level->id]);

    $response = $this->deleteJson("/api/ticket-complexity-levels/{$level->id}");

    $response->assertStatus(422);
    expect(TicketComplexityLevel::find($level->id))->not->toBeNull();
});

test('deleting an unused complexity level succeeds', function () {
    $level = TicketComplexityLevel::factory()->create();

    $response = $this->deleteJson("/api/ticket-complexity-levels/{$level->id}");

    $response->assertSuccessful();
    expect(TicketComplexityLevel::find($level->id))->toBeNull();
});
