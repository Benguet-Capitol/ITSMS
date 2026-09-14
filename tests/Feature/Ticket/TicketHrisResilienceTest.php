<?php

use App\Models\ItService;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Covers a real bug: TicketController::index() called the external HRIS
 * service unconditionally (employee-name enrichment, and employee search
 * when the search term has letters). When HRIS is down or erroring, the
 * whole tickets list 500'd for every user even though nothing about the
 * failure has anything to do with ITSMS's own data. Both HRIS calls are
 * now wrapped so the list still loads (degraded, without employee-name
 * enrichment) and meta.hris_unavailable tells the frontend to show a
 * "this isn't an ITSMS problem" notice instead of a blank/broken table.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    Profile::create([
        'user_id' => $this->actor->id,
        'display_name' => 'Hris Resilience Tester',
        'name' => ['firstname' => 'Hris', 'lastname' => 'Tester'],
        'gender' => 'male',
        'designation' => 'Tester',
        'engagement' => 'ready',
    ]);

    $role = Role::create(['title' => 'Hris Resilience Tester']);

    $permissionIds = collect(['tickets.view', 'tickets.update', 'tickets.assess', 'tickets.print_assessment'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);

    $profile = Profile::factory()->create();
    $itService = ItService::factory()->create();

    Ticket::create([
        'profile_id' => $profile->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'HRIS-0001',
        'concern' => 'Test ticket',
        'query_status' => 'queued',
        'request_status' => 'open',
    ]);
});

test('the ticket list still loads and flags hris_unavailable when HRIS is down', function () {
    Http::fake([
        '*/getEmployees*' => Http::response(['message' => 'Internal Server Error'], 500),
    ]);

    $response = $this->getJson('/api/tickets');

    $response->assertSuccessful();
    expect($response->json('meta.hris_unavailable'))->toBeTrue();

    $ticketNumbers = collect($response->json('data'))->pluck('ticket_number');
    expect($ticketNumbers)->toContain('HRIS-0001');
});

test('the ticket list reports hris_unavailable as false when HRIS responds normally', function () {
    Http::fake([
        '*/getEmployees*' => Http::response(['data' => []]),
    ]);

    $response = $this->getJson('/api/tickets');

    $response->assertSuccessful();
    expect($response->json('meta.hris_unavailable'))->toBeFalse();
});

test('searching by name still works and flags hris_unavailable when HRIS is down', function () {
    Http::fake([
        '*/getEmployees*' => Http::response(['message' => 'Internal Server Error'], 500),
    ]);

    $response = $this->getJson('/api/tickets?search=someone');

    $response->assertSuccessful();
    expect($response->json('meta.hris_unavailable'))->toBeTrue();
});

/**
 * Covers a real bug: assess() called an uncached $hris->getEmployees() to
 * compute $authEmployee, which was never actually used anywhere -- the
 * assessed_by/assessed_by_position fields come entirely from the
 * authenticated user's own profile. That dead call still had to succeed
 * for the request to complete, so submitting or updating an assessment --
 * which has nothing to do with HRIS -- was completely blocked whenever
 * HRIS was down. The call is now removed outright.
 */
test('submitting an assessment succeeds even when HRIS is down', function () {
    Http::fake([
        '*/getEmployees*' => Http::response(['message' => 'Internal Server Error'], 500),
    ]);

    $itService = ItService::factory()->create();
    $ticket = Ticket::create([
        'profile_id' => Profile::factory()->create()->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'HRIS-0002',
        'concern' => 'Needs assessment',
        'query_status' => 'queued',
        'request_status' => 'open',
    ]);

    $response = $this->postJson("/api/tickets/{$ticket->id}/assess", [
        'findings' => 'Motherboard is dead.',
        'recommendations' => 'Replace the unit.',
        'reviewed_by' => 'Jane Reviewer',
        'reviewed_by_position' => 'IT Officer',
        'replacement_available' => false,
    ]);

    $response->assertSuccessful();
    expect($ticket->fresh()->assessment)->not->toBeNull();
});

/**
 * Covers a real bug: assessmentReport() called $hris->getEmployeesCached()
 * unconditionally to resolve the employee name/office for the printed PDF.
 * The rest of the method already falls back to "—" when that data is
 * missing, but the HRIS call itself wasn't guarded, so an HRIS outage
 * blocked printing an assessment report entirely.
 */
test('downloading an assessment report succeeds even when HRIS is down', function () {
    Http::fake([
        '*/getEmployees*' => Http::response(['message' => 'Internal Server Error'], 500),
    ]);

    $itService = ItService::factory()->create();
    $ticket = Ticket::create([
        'profile_id' => Profile::factory()->create()->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'HRIS-0003',
        'concern' => 'Needs assessment',
        'query_status' => 'assessed',
        'request_status' => 'closed',
    ]);

    $ticket->assessment()->create([
        'findings' => 'Motherboard is dead.',
        'recommendations' => 'Replace the unit.',
        'reviewed_by' => 'Jane Reviewer',
        'reviewed_by_position' => 'IT Officer',
        'assessed_by' => 'John Assessor',
        'assessed_by_position' => 'IT Officer',
        'replacement_available' => false,
    ]);

    $response = $this->get("/api/tickets/{$ticket->id}/assessment-report");

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
