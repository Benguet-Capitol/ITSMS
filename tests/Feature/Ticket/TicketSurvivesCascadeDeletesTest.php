<?php

use App\Models\ItService;
use App\Models\Profile;
use App\Models\Ticket;
use App\Models\User;

/**
 * Covers two real data-loss bugs surfaced during review: tickets.profile_id
 * and tickets.it_service_id were cascadeOnDelete() while every other FK on
 * the table (inventory_id, agency_id, item_type_id, solution_id) already
 * used nullOnDelete(). Since deleting a user already cascades to their
 * profile, and it_services are deletable through the IT Services module,
 * either deletion used to silently destroy every ticket referencing them.
 * Both FKs are now nullOnDelete() to match the table's own convention.
 */
function createUserWithProfileForTicketTest(): User
{
    $user = User::factory()->create();

    Profile::create([
        'user_id' => $user->id,
        'display_name' => 'Ticket Submitter',
        'name' => ['firstname' => 'Ticket', 'lastname' => 'Submitter'],
        'gender' => 'male',
        'designation' => 'Tester',
        'engagement' => 'ready',
    ]);

    return $user;
}

test('deleting a user preserves tickets they submitted, with profile cleared', function () {
    $user = createUserWithProfileForTicketTest();
    $profile = $user->profile;
    $itService = ItService::factory()->create();

    $ticket = Ticket::create([
        'profile_id' => $profile->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'TEST-0001',
        'concern' => 'Printer not working',
        'query_status' => 'queued',
        'request_status' => 'open',
    ]);

    $user->delete();

    $ticket->refresh();

    expect($ticket)->not->toBeNull();
    expect($ticket->profile_id)->toBeNull();
});

test('deleting an it service preserves tickets that used it, with it_service cleared', function () {
    $profile = Profile::factory()->create();
    $itService = ItService::factory()->create();

    $ticket = Ticket::create([
        'profile_id' => $profile->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'TEST-0002',
        'concern' => 'Laptop screen cracked',
        'query_status' => 'queued',
        'request_status' => 'open',
    ]);

    $itService->delete();

    $ticket->refresh();

    expect($ticket)->not->toBeNull();
    expect($ticket->it_service_id)->toBeNull();
});
