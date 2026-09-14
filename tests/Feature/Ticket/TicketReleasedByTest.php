<?php

use App\Models\ItService;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;

/**
 * Setting a ticket's release date now also captures who set it, so the
 * release can be attributed to a specific staff member.
 */
test('setting a release date captures the acting user as released_by', function () {
    $actor = User::factory()->create();

    Profile::create([
        'user_id' => $actor->id,
        'display_name' => 'Release Tester',
        'name' => ['firstname' => 'Release', 'lastname' => 'Tester'],
        'gender' => 'male',
        'designation' => 'Tester',
        'engagement' => 'ready',
    ]);

    $role = Role::create(['title' => 'Release Tester']);

    $permissionIds = collect(['tickets.update', 'tickets.set_release_date'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $actor->roles()->attach($role->id);

    $this->actingAs($actor);

    $ticket = Ticket::create([
        'profile_id' => Profile::factory()->create()->id,
        'it_service_id' => ItService::factory()->create()->id,
        'ticket_number' => 'RELEASE-0001',
        'concern' => 'Ready for release',
        'query_status' => 'resolved',
        'request_status' => 'closed',
        'service_method' => 'pulled_out',
    ]);

    $response = $this->postJson("/api/tickets/{$ticket->id}/set-release-date", [
        'released_at' => '2026-09-09 10:00:00',
    ]);

    $response->assertSuccessful();
    $ticket->refresh();

    expect($ticket->released_by)->toBe($actor->fresh()->profile->formatted_name);
    expect($response->json('released_by'))->toBe($ticket->released_by);
});
