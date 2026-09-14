<?php

use App\Models\Agency;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;

/**
 * Covers the usage() endpoint added for the Agencies delete-warning UI.
 * profile_agency cascades on agency_id (it's a pivot table), so deleting
 * an agency destroys those assignment rows outright; tickets.agency_id is
 * nullOnDelete, so tickets just lose the reference. usage() surfaces both
 * counts so the frontend can warn before confirming the delete.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    $role = Role::create(['title' => 'Agency Tester']);

    $permissionIds = collect(['agencies.view', 'agencies.delete'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);
});

test('usage reports zero counts for an unused agency', function () {
    $agency = Agency::factory()->create();

    $response = $this->getJson("/api/agencies/{$agency->id}/usage");

    $response->assertSuccessful();
    $response->assertJson([
        'assigned_profiles_count' => 0,
        'tickets_count' => 0,
    ]);
});

test('usage reports assigned profiles and tickets referencing the agency', function () {
    $agency = Agency::factory()->create();

    $profile = Profile::factory()->create();
    $profile->agencies()->attach($agency->id);

    Ticket::factory()->create(['agency_id' => $agency->id, 'is_other_agency' => true]);
    Ticket::factory()->create(['agency_id' => $agency->id, 'is_other_agency' => true]);

    $response = $this->getJson("/api/agencies/{$agency->id}/usage");

    $response->assertSuccessful();
    $response->assertJson([
        'assigned_profiles_count' => 1,
        'tickets_count' => 2,
    ]);
});

test('deleting an agency removes profile assignments and nulls out ticket references', function () {
    $agency = Agency::factory()->create();

    $profile = Profile::factory()->create();
    $profile->agencies()->attach($agency->id);

    $ticket = Ticket::factory()->create(['agency_id' => $agency->id, 'is_other_agency' => true]);

    $response = $this->deleteJson("/api/agencies/{$agency->id}");

    $response->assertSuccessful();
    expect($profile->agencies()->count())->toBe(0);
    expect($ticket->fresh()->agency_id)->toBeNull();
});
