<?php

use App\Enums\TicketStatus;
use App\Models\ItemType;
use App\Models\ItService;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Solution;
use App\Models\Ticket;
use App\Models\TicketComplexityLevel;
use App\Models\User;

/**
 * ProfileEngagementService::sync() is meant to be the single source of truth
 * for engagement (ready/busy). TicketObserver and the TicketPersonnel pivot
 * used to carry their own duplicate copy of the same calculation -- missing
 * the query_status exclusion and the Reopened request_status entirely --
 * which only stayed hidden because TicketController's accept/resolve/reopen
 * actions all explicitly call ProfileEngagementService::syncTicket() right
 * after, overwriting whatever the duplicate logic computed. The plain
 * PUT /tickets/{ticket} update endpoint never made that follow-up call, so
 * editing a *reopened* ticket's details (its concern, its office, etc.)
 * would silently flip the assigned technician from busy back to ready --
 * with no ticket status actually changing.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    $this->profile = Profile::create([
        'user_id' => $this->actor->id,
        'display_name' => 'Engagement Tester',
        'name' => ['firstname' => 'Engagement', 'lastname' => 'Tester'],
        'gender' => 'male',
        'designation' => 'Tester',
        'engagement' => Profile::ENGAGEMENT_READY,
    ]);

    $role = Role::create(['title' => 'Engagement Tester Role']);

    $permissionIds = collect([
        'tickets.update',
        'tickets.accept',
        'tickets.resolve',
        'tickets.reopen',
    ])->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);

    $this->itService = ItService::factory()->create();
    $this->itemType = ItemType::factory()->create();

    $this->ticket = Ticket::create([
        'profile_id' => Profile::factory()->create()->id,
        'it_service_id' => $this->itService->id,
        'ticket_number' => 'ENGAGEMENT-0001',
        'concern' => 'Original concern',
        'query_status' => TicketStatus::Queued,
        'request_status' => TicketStatus::Open,
        'office_id' => 'OFFICE-1',
        'complexity_level_id' => TicketComplexityLevel::factory()->create()->id,
    ]);
});

test('accepting a ticket marks the technician busy', function () {
    $this->postJson("/api/tickets/{$this->ticket->id}/accept")->assertSuccessful();

    expect($this->profile->refresh()->engagement)->toBe(Profile::ENGAGEMENT_BUSY);
});

test('resolving a ticket marks the technician ready again', function () {
    $solution = Solution::create(['title' => 'Fix', 'description' => 'Fix it']);
    $this->postJson("/api/tickets/{$this->ticket->id}/accept")->assertSuccessful();

    $this->postJson("/api/tickets/{$this->ticket->id}/resolve", [
        'service_method' => 'on_site',
        'solution_id' => $solution->id,
    ])->assertSuccessful();

    expect($this->profile->refresh()->engagement)->toBe(Profile::ENGAGEMENT_READY);
});

test('reopening a resolved ticket marks the technician busy again', function () {
    $solution = Solution::create(['title' => 'Fix', 'description' => 'Fix it']);
    $this->postJson("/api/tickets/{$this->ticket->id}/accept")->assertSuccessful();
    $this->postJson("/api/tickets/{$this->ticket->id}/resolve", [
        'service_method' => 'on_site',
        'solution_id' => $solution->id,
    ])->assertSuccessful();

    $this->postJson("/api/tickets/{$this->ticket->id}/reopen")->assertSuccessful();

    expect($this->profile->refresh()->engagement)->toBe(Profile::ENGAGEMENT_BUSY);
});

test('editing a reopened ticket\'s details leaves the assigned technician busy', function () {
    $solution = Solution::create(['title' => 'Fix', 'description' => 'Fix it']);
    $this->postJson("/api/tickets/{$this->ticket->id}/accept")->assertSuccessful();
    $this->postJson("/api/tickets/{$this->ticket->id}/resolve", [
        'service_method' => 'on_site',
        'solution_id' => $solution->id,
    ])->assertSuccessful();
    $this->postJson("/api/tickets/{$this->ticket->id}/reopen")->assertSuccessful();

    expect($this->profile->refresh()->engagement)->toBe(Profile::ENGAGEMENT_BUSY);

    $response = $this->putJson("/api/tickets/{$this->ticket->id}", [
        'item_type_id' => $this->itemType->id,
        'it_service_id' => $this->itService->id,
        'office_id' => 'OFFICE-1',
        'concern' => 'Edited concern text, unrelated to ticket status',
    ]);

    $response->assertSuccessful();

    expect($this->profile->refresh()->engagement)->toBe(Profile::ENGAGEMENT_BUSY);
});

test('editing a closed ticket\'s details leaves an unrelated technician ready', function () {
    $solution = Solution::create(['title' => 'Fix', 'description' => 'Fix it']);
    $this->postJson("/api/tickets/{$this->ticket->id}/accept")->assertSuccessful();
    $this->postJson("/api/tickets/{$this->ticket->id}/resolve", [
        'service_method' => 'on_site',
        'solution_id' => $solution->id,
    ])->assertSuccessful();

    expect($this->profile->refresh()->engagement)->toBe(Profile::ENGAGEMENT_READY);

    $response = $this->putJson("/api/tickets/{$this->ticket->id}", [
        'item_type_id' => $this->itemType->id,
        'it_service_id' => $this->itService->id,
        'office_id' => 'OFFICE-1',
        'concern' => 'Edited concern text after resolution',
    ]);

    $response->assertSuccessful();

    expect($this->profile->refresh()->engagement)->toBe(Profile::ENGAGEMENT_READY);
});
