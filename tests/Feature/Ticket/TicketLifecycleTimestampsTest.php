<?php

use App\Models\ItService;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Solution;
use App\Models\Ticket;
use App\Models\TicketComplexityLevel;
use App\Models\User;
use Carbon\Carbon;

/**
 * accept()/resolve() now auto-stamp accepted_at/resolved_at so the elapsed
 * time can be validated against a ticket's assigned complexity bracket --
 * previously only the query_status/request_status strings changed, with no
 * record of when either transition actually happened.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    $this->profile = Profile::create([
        'user_id' => $this->actor->id,
        'display_name' => 'Ticket Lifecycle Tester',
        'name' => ['firstname' => 'Ticket', 'lastname' => 'Tester'],
        'gender' => 'male',
        'designation' => 'Tester',
        'engagement' => 'ready',
    ]);

    $role = Role::create(['title' => 'Ticket Lifecycle Tester']);

    $permissionIds = collect([
        'tickets.update',
        'tickets.accept',
        'tickets.unaccept',
        'tickets.resolve',
        'tickets.reopen',
    ])->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);

    $itService = ItService::factory()->create();

    $this->ticket = Ticket::create([
        'profile_id' => Profile::factory()->create()->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'LIFECYCLE-0001',
        'concern' => 'Timestamp tracking',
        'query_status' => 'queued',
        'request_status' => 'open',
        'complexity_level_id' => TicketComplexityLevel::factory()->create()->id,
    ]);
});

test('accepting a ticket stamps accepted_at', function () {
    expect($this->ticket->accepted_at)->toBeNull();

    Carbon::setTestNow('2026-09-09 08:00:00');

    $response = $this->postJson("/api/tickets/{$this->ticket->id}/accept");

    $response->assertSuccessful();
    $this->ticket->refresh();

    expect($this->ticket->accepted_at?->toDateTimeString())->toBe('2026-09-09 08:00:00');
});

test('unaccepting a ticket clears accepted_at', function () {
    $this->postJson("/api/tickets/{$this->ticket->id}/accept")->assertSuccessful();
    $this->ticket->refresh();
    expect($this->ticket->accepted_at)->not->toBeNull();

    $response = $this->postJson("/api/tickets/{$this->ticket->id}/unaccept");

    $response->assertSuccessful();
    $this->ticket->refresh();

    expect($this->ticket->accepted_at)->toBeNull();
    expect($this->ticket->request_status)->toBe(\App\Enums\TicketStatus::Open);
});

test('resolving a ticket stamps resolved_at', function () {
    $solution = Solution::create(['title' => 'Fix', 'description' => 'Fix it']);
    $this->postJson("/api/tickets/{$this->ticket->id}/accept")->assertSuccessful();

    Carbon::setTestNow('2026-09-09 09:15:00');

    $response = $this->postJson("/api/tickets/{$this->ticket->id}/resolve", [
        'service_method' => 'on_site',
        'solution_id' => $solution->id,
    ]);

    $response->assertSuccessful();
    $this->ticket->refresh();

    expect($this->ticket->resolved_at?->toDateTimeString())->toBe('2026-09-09 09:15:00');
});

test('reopening a ticket clears resolved_at but leaves accepted_at untouched', function () {
    $solution = Solution::create(['title' => 'Fix', 'description' => 'Fix it']);

    Carbon::setTestNow('2026-09-01 08:00:00');
    $this->postJson("/api/tickets/{$this->ticket->id}/accept")->assertSuccessful();

    Carbon::setTestNow('2026-09-01 08:05:00');
    $this->postJson("/api/tickets/{$this->ticket->id}/resolve", [
        'service_method' => 'on_site',
        'solution_id' => $solution->id,
    ])->assertSuccessful();

    Carbon::setTestNow('2026-09-05 10:00:00');
    $response = $this->postJson("/api/tickets/{$this->ticket->id}/reopen");

    $response->assertSuccessful();
    $this->ticket->refresh();

    expect($this->ticket->resolved_at)->toBeNull();
    expect($this->ticket->accepted_at?->toDateTimeString())->toBe('2026-09-01 08:00:00');
});
