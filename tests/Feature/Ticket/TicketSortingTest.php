<?php

use App\Models\Inventory;
use App\Models\ItService;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Covers a real bug: sorting the tickets list by property_number joined
 * `inventories` with an inner join(), which silently excluded every ticket
 * with no inventory_id (other-agency tickets, or tickets whose inventory
 * was since deleted) from the entire result set -- not just reordered them
 * to one end. Switched to leftJoin() so those tickets stay in the list.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    Profile::create([
        'user_id' => $this->actor->id,
        'display_name' => 'Ticket Sort Tester',
        'name' => ['firstname' => 'Ticket', 'lastname' => 'Tester'],
        'gender' => 'male',
        'designation' => 'Tester',
        'engagement' => 'ready',
    ]);

    $role = Role::create(['title' => 'Ticket Sort Tester']);

    $permissionIds = collect(['tickets.view'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);

    // TicketController::index() always calls out to the external HRIS
    // service (employee directory lookups) -- fake it rather than let the
    // test depend on that real, unrelated external system being up.
    Http::fake([
        '*/getEmployees*' => Http::response(['data' => []]),
    ]);
});

test('sorting tickets by property number still includes tickets without an inventory', function () {
    $profile = Profile::factory()->create();
    $itService = ItService::factory()->create();
    $inventory = Inventory::factory()->create();

    Ticket::create([
        'profile_id' => $profile->id,
        'it_service_id' => $itService->id,
        'inventory_id' => $inventory->id,
        'ticket_number' => 'SORT-0001',
        'concern' => 'Has an inventory',
        'query_status' => 'queued',
        'request_status' => 'open',
    ]);

    Ticket::create([
        'profile_id' => $profile->id,
        'it_service_id' => $itService->id,
        'inventory_id' => null,
        'is_other_agency' => true,
        'ticket_number' => 'SORT-0002',
        'concern' => 'No inventory (other agency)',
        'query_status' => 'queued',
        'request_status' => 'open',
    ]);

    $response = $this->getJson('/api/tickets?sort=property_number&order=asc');

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(2);

    $ticketNumbers = collect($response->json('data'))->pluck('ticket_number');
    expect($ticketNumbers)->toContain('SORT-0001', 'SORT-0002');
});

/**
 * Covers enabling sorting for Query Status / Request Status: the frontend
 * columns were keyed to the *_formatted display fields (e.g.
 * "query_status_formatted"), which don't match the backend's $sortable map
 * ("query_status") -- so even with sortable: true, the sort param sent
 * would never have matched and silently fallen through to the default
 * ->latest() order. Columns now use the raw field as their key/sort id.
 */
test('tickets can be sorted by query status', function () {
    $profile = Profile::factory()->create();
    $itService = ItService::factory()->create();

    Ticket::create([
        'profile_id' => $profile->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'SORT-0003',
        'concern' => 'Resolved one',
        'query_status' => 'resolved',
        'request_status' => 'closed',
    ]);

    Ticket::create([
        'profile_id' => $profile->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'SORT-0004',
        'concern' => 'Queued one',
        'query_status' => 'queued',
        'request_status' => 'open',
    ]);

    $response = $this->getJson('/api/tickets?sort=query_status&order=asc');

    $response->assertSuccessful();
    $statuses = collect($response->json('data'))->pluck('query_status');
    expect($statuses->all())->toBe($statuses->sort()->values()->all());
});

test('tickets can be sorted by request status', function () {
    $profile = Profile::factory()->create();
    $itService = ItService::factory()->create();

    Ticket::create([
        'profile_id' => $profile->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'SORT-0005',
        'concern' => 'Closed one',
        'query_status' => 'resolved',
        'request_status' => 'closed',
    ]);

    Ticket::create([
        'profile_id' => $profile->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'SORT-0006',
        'concern' => 'Open one',
        'query_status' => 'queued',
        'request_status' => 'open',
    ]);

    $response = $this->getJson('/api/tickets?sort=request_status&order=asc');

    $response->assertSuccessful();
    $statuses = collect($response->json('data'))->pluck('request_status');
    expect($statuses->all())->toBe($statuses->sort()->values()->all());
});
