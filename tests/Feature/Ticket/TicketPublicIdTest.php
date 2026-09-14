<?php

use App\Models\ItService;
use App\Models\Profile;
use App\Models\Ticket;

/**
 * Sprint 4 exit-gate item, mirroring the Sprint 3 `inventories.public_id`
 * pattern: a safe, non-guessable identifier auto-generated on create,
 * independent of the raw sequential `id`.
 */
beforeEach(function () {
    $this->profile = Profile::factory()->create();
    $this->itService = ItService::factory()->create();
});

function ticketPayload(Profile $profile, ItService $itService, array $overrides = []): array
{
    return array_merge([
        'profile_id' => $profile->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'PUBLICID-'.uniqid(),
        'concern' => 'Testing public_id',
        'query_status' => 'queued',
        'request_status' => 'open',
    ], $overrides);
}

test('a new ticket is auto-assigned a unique public_id', function () {
    $ticket = Ticket::create(ticketPayload($this->profile, $this->itService));

    expect($ticket->public_id)->not->toBeNull();
    expect(strlen($ticket->public_id))->toBe(26);
});

test('two tickets never receive the same public_id', function () {
    $first = Ticket::create(ticketPayload($this->profile, $this->itService));
    $second = Ticket::create(ticketPayload($this->profile, $this->itService));

    expect($first->public_id)->not->toBe($second->public_id);
});

test('public_id is not mass-assignable', function () {
    $ticket = Ticket::create(ticketPayload($this->profile, $this->itService, [
        'public_id' => 'not-a-real-ulid',
    ]));

    expect($ticket->public_id)->not->toBe('not-a-real-ulid');
    expect(strlen($ticket->public_id))->toBe(26);
});
