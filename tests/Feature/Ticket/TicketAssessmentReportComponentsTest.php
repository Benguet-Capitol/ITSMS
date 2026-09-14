<?php

use App\Models\ItService;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Covers adding Laptop/Mobile component checklists (matching the existing
 * System Unit/Peripherals ones) to the assess modal and the printed
 * assessment report. Also covers a pre-existing bug caught while making
 * this change: the report's system_unit_parts/peripherals arrays used a
 * bare "OTHERS" label, but the modal has always stored "OTHERS (System
 * Unit)"/"OTHERS (Peripherals)" -- so that checkbox never rendered as
 * checked on the printed report. All four categories' labels must match
 * components/tickets/AssessModal.vue exactly.
 */
beforeEach(function () {
    $this->actor = User::factory()->create();

    Profile::create([
        'user_id' => $this->actor->id,
        'display_name' => 'Assessment Report Tester',
        'name' => ['firstname' => 'Report', 'lastname' => 'Tester'],
        'gender' => 'male',
        'designation' => 'Tester',
        'engagement' => 'ready',
    ]);

    $role = Role::create(['title' => 'Assessment Report Tester']);

    $permissionIds = collect(['tickets.view', 'tickets.update', 'tickets.assess', 'tickets.print_assessment'])
        ->map(fn ($title) => Permission::create(['title' => $title])->id);

    $role->permissions()->attach($permissionIds);
    $this->actor->roles()->attach($role->id);

    $this->actingAs($this->actor);

    Http::fake([
        '*/getEmployees*' => Http::response(['data' => []]),
    ]);
});

test('the assessment report renders successfully with laptop and mobile components checked', function () {
    $itService = ItService::factory()->create();
    $ticket = Ticket::create([
        'profile_id' => Profile::factory()->create()->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'REPORT-0001',
        'concern' => 'Laptop screen and phone battery issue',
        'query_status' => 'assessed',
        'request_status' => 'closed',
    ]);

    $ticket->assessment()->create([
        'findings' => 'Laptop LCD cracked; mobile battery swollen.',
        'recommendations' => 'Replace laptop screen and phone battery.',
        'reviewed_by' => 'Jane Reviewer',
        'reviewed_by_position' => 'IT Officer',
        'assessed_by' => 'John Assessor',
        'assessed_by_position' => 'IT Officer',
        'replacement_available' => true,
        'components' => [
            'OTHERS (System Unit)',
            'OTHERS (Peripherals)',
            'LCD/SCREEN',
            'BATTERY (Mobile)',
        ],
        'component_remarks' => [
            'LCD/SCREEN' => 'Cracked, needs full replacement.',
            'BATTERY (Mobile)' => 'Swollen, unsafe to use.',
        ],
    ]);

    $response = $this->get("/api/tickets/{$ticket->id}/assessment-report");

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

/**
 * Covers adding a per-checkbox remarks field: assess() now accepts and
 * persists component_remarks (a map of component label -> remark text),
 * and TicketResource's assessment block -- which previously omitted
 * reviewed_by_position/assessed_by_position entirely, a second
 * pre-existing bug caught in the same code -- now round-trips all of it
 * back to the frontend so "Edit Assessment" shows what was actually saved.
 */
test('submitting an assessment persists component remarks and round-trips them back', function () {
    $itService = ItService::factory()->create();
    $ticket = Ticket::create([
        'profile_id' => Profile::factory()->create()->id,
        'it_service_id' => $itService->id,
        'ticket_number' => 'REPORT-0002',
        'concern' => 'Laptop keyboard sticking',
        'query_status' => 'queued',
        'request_status' => 'open',
    ]);

    $response = $this->postJson("/api/tickets/{$ticket->id}/assess", [
        'findings' => 'Several keys unresponsive.',
        'recommendations' => 'Replace keyboard assembly.',
        'reviewed_by' => 'Jane Reviewer',
        'reviewed_by_position' => 'IT Officer',
        'replacement_available' => false,
        'components' => ['KEYBOARD (Laptop)'],
        'component_remarks' => [
            'KEYBOARD (Laptop)' => 'Spacebar and arrow keys unresponsive.',
        ],
    ]);

    $response->assertSuccessful();

    // TicketController::show() returns TicketResource::make($ticket)
    // directly -- unlike index(), it isn't wrapped in a "data" key.
    $show = $this->getJson("/api/tickets/{$ticket->id}");

    $show->assertSuccessful();
    expect($show->json('assessment.component_remarks'))->toBe([
        'KEYBOARD (Laptop)' => 'Spacebar and arrow keys unresponsive.',
    ]);
    expect($show->json('assessment.reviewed_by_position'))->toBe('IT Officer');
});
