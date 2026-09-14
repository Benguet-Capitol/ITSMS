<?php

use App\Models\ItService;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;

/**
 * Sprint 5: the dashboard-summary endpoint grew a `trend` (last 14 days,
 * created vs. resolved counts) and `breakdown` (current counts by
 * query_status) payload for the new dashboard charts, alongside the
 * existing 4 KPI numbers.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    Profile::create([
        'user_id' => $this->actor->id,
        'display_name' => 'Dashboard Summary Tester',
        'name' => ['firstname' => 'Dashboard', 'lastname' => 'Tester'],
        'gender' => 'male',
        'designation' => 'Tester',
        'engagement' => 'ready',
    ]);

    $role = Role::create(['title' => 'Dashboard Summary Tester']);

    $permissionIds = collect(['tickets.view'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);

    $this->itService = ItService::factory()->create();
    $this->profile = Profile::factory()->create();
});

test('trend covers exactly the last 14 days, oldest first, zero-filled where there is no data', function () {
    Carbon::setTestNow('2026-09-10 12:00:00');

    $response = $this->getJson('/api/tickets/dashboard-summary');

    $response->assertSuccessful();
    $trend = $response->json('trend');

    expect($trend)->toHaveCount(14);
    expect($trend[0]['date'])->toBe('2026-08-28');
    expect($trend[13]['date'])->toBe('2026-09-10');

    foreach ($trend as $day) {
        expect($day['created'])->toBe(0);
        expect($day['resolved'])->toBe(0);
    }
});

test('trend counts tickets created and resolved on the correct day', function () {
    Carbon::setTestNow('2026-09-10 12:00:00');

    Ticket::create([
        'profile_id' => $this->profile->id,
        'it_service_id' => $this->itService->id,
        'ticket_number' => 'DASH-0001',
        'concern' => 'Created today',
        'query_status' => 'queued',
        'request_status' => 'open',
    ]);

    Carbon::setTestNow('2026-09-08 09:00:00');
    $resolvedTicket = Ticket::create([
        'profile_id' => $this->profile->id,
        'it_service_id' => $this->itService->id,
        'ticket_number' => 'DASH-0002',
        'concern' => 'Created two days ago, resolved same day',
        'query_status' => 'resolved',
        'request_status' => 'closed',
        'resolved_at' => '2026-09-08 15:00:00',
    ]);

    // A ticket that was created and resolved outside the 14-day window
    // shouldn't pollute the window's counts. created_at isn't
    // mass-assignable, so it's controlled via setTestNow instead.
    Carbon::setTestNow('2026-01-01 09:00:00');
    Ticket::create([
        'profile_id' => $this->profile->id,
        'it_service_id' => $this->itService->id,
        'ticket_number' => 'DASH-0003',
        'concern' => 'Resolved long ago',
        'query_status' => 'resolved',
        'request_status' => 'closed',
        'resolved_at' => '2026-01-01 15:00:00',
    ]);

    Carbon::setTestNow('2026-09-10 12:00:00');

    $response = $this->getJson('/api/tickets/dashboard-summary');
    $response->assertSuccessful();

    $trend = collect($response->json('trend'))->keyBy('date');

    expect($trend['2026-09-10']['created'])->toBe(1);
    expect($trend['2026-09-10']['resolved'])->toBe(0);
    expect($trend['2026-09-08']['created'])->toBe(1);
    expect($trend['2026-09-08']['resolved'])->toBe(1);
    expect($trend['2026-01-01'] ?? null)->toBeNull();
});

test('a reopened ticket no longer counts toward the day it was originally resolved', function () {
    Carbon::setTestNow('2026-09-08 09:00:00');

    $ticket = Ticket::create([
        'profile_id' => $this->profile->id,
        'it_service_id' => $this->itService->id,
        'ticket_number' => 'DASH-0004',
        'concern' => 'Will be reopened',
        'query_status' => 'resolved',
        'request_status' => 'closed',
        'resolved_at' => '2026-09-08 15:00:00',
    ]);

    // Mirrors TicketController::reopen() clearing resolved_at.
    $ticket->update(['resolved_at' => null, 'query_status' => 'in_progress', 'request_status' => 'reopened']);

    Carbon::setTestNow('2026-09-10 12:00:00');

    $response = $this->getJson('/api/tickets/dashboard-summary');
    $response->assertSuccessful();

    $trend = collect($response->json('trend'))->keyBy('date');

    expect($trend['2026-09-08']['resolved'])->toBe(0);
});

test('breakdown groups current tickets by query_status', function () {
    Ticket::create([
        'profile_id' => $this->profile->id,
        'it_service_id' => $this->itService->id,
        'ticket_number' => 'DASH-0005',
        'concern' => 'Queued one',
        'query_status' => 'queued',
        'request_status' => 'open',
    ]);

    Ticket::create([
        'profile_id' => $this->profile->id,
        'it_service_id' => $this->itService->id,
        'ticket_number' => 'DASH-0006',
        'concern' => 'Queued two',
        'query_status' => 'queued',
        'request_status' => 'open',
    ]);

    Ticket::create([
        'profile_id' => $this->profile->id,
        'it_service_id' => $this->itService->id,
        'ticket_number' => 'DASH-0007',
        'concern' => 'Resolved one',
        'query_status' => 'resolved',
        'request_status' => 'closed',
    ]);

    $response = $this->getJson('/api/tickets/dashboard-summary');
    $response->assertSuccessful();

    $breakdown = $response->json('breakdown');

    expect($breakdown['queued'])->toBe(2);
    expect($breakdown['resolved'])->toBe(1);
});
