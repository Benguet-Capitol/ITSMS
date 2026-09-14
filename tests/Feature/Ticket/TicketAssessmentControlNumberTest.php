<?php

use App\Models\ItService;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;

/**
 * ticket_assessments.control_number is generated once, on first creation,
 * in a YYYY-MM-#### format that resets every month -- editing an existing
 * assessment must never regenerate or overwrite it.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    Profile::create([
        'user_id' => $this->actor->id,
        'display_name' => 'Assessment Tester',
        'name' => ['firstname' => 'Assessment', 'lastname' => 'Tester'],
        'gender' => 'male',
        'designation' => 'Tester',
        'engagement' => 'ready',
    ]);

    $role = Role::create(['title' => 'Assessment Tester']);

    $permissionIds = collect(['tickets.update', 'tickets.assess'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);

    $itService = ItService::factory()->create();

    $this->makeTicket = fn (string $number) => Ticket::create([
        'profile_id' => Profile::factory()->create()->id,
        'it_service_id' => $itService->id,
        'ticket_number' => $number,
        'concern' => 'Needs assessment',
        'query_status' => 'in_progress',
        'request_status' => 'accepted',
    ]);

    $this->assessPayload = fn () => [
        'findings' => 'Broken part',
        'recommendations' => 'Replace it',
        'reviewed_by' => 'Reviewer Name',
        'reviewed_by_position' => 'Supervisor',
        'replacement_available' => true,
    ];
});

test('the first assessment on a ticket generates a monthly control number', function () {
    Carbon::setTestNow('2025-10-15 10:00:00');

    $ticket = ($this->makeTicket)('ASSESS-0001');

    $response = $this->postJson("/api/tickets/{$ticket->id}/assess", ($this->assessPayload)());

    $response->assertSuccessful();
    expect($response->json('assessment.control_number'))->toBe('2025-10-0001');
});

test('re-assessing an existing assessment does not change its control number', function () {
    Carbon::setTestNow('2025-10-15 10:00:00');

    $ticket = ($this->makeTicket)('ASSESS-0002');

    $this->postJson("/api/tickets/{$ticket->id}/assess", ($this->assessPayload)())->assertSuccessful();
    $original = $ticket->fresh()->assessment->control_number;

    $response = $this->postJson("/api/tickets/{$ticket->id}/assess", [
        ...($this->assessPayload)(),
        'findings' => 'Updated findings',
    ]);

    $response->assertSuccessful();
    expect($response->json('assessment.control_number'))->toBe($original);
});

test('assessments in the same month get sequential control numbers', function () {
    Carbon::setTestNow('2025-10-15 10:00:00');

    $first = ($this->makeTicket)('ASSESS-0003');
    $second = ($this->makeTicket)('ASSESS-0004');

    $this->postJson("/api/tickets/{$first->id}/assess", ($this->assessPayload)())->assertSuccessful();
    $response = $this->postJson("/api/tickets/{$second->id}/assess", ($this->assessPayload)());

    $response->assertSuccessful();
    expect($response->json('assessment.control_number'))->toBe('2025-10-0002');
});

test('the control number counter resets in a new month', function () {
    Carbon::setTestNow('2025-10-31 23:00:00');
    $october = ($this->makeTicket)('ASSESS-0005');
    $this->postJson("/api/tickets/{$october->id}/assess", ($this->assessPayload)())->assertSuccessful();

    Carbon::setTestNow('2025-11-01 00:05:00');
    $november = ($this->makeTicket)('ASSESS-0006');
    $response = $this->postJson("/api/tickets/{$november->id}/assess", ($this->assessPayload)());

    $response->assertSuccessful();
    expect($response->json('assessment.control_number'))->toBe('2025-11-0001');
});
