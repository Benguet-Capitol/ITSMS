<?php

use App\Enums\TicketStatus;
use App\Models\ItService;
use App\Models\Profile;
use App\Models\ProfileOffice;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketComplexityLevel;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use Illuminate\Support\Facades\Notification;

/**
 *
 */
beforeEach(function () {
    $role = Role::create(['title' => 'IT Technical']);

    $this->matched = User::factory()->create();
    $matchedProfile = Profile::create([
        'user_id' => $this->matched->id,
        'display_name' => 'Matched Tech',
        'name' => ['firstname' => 'Matched', 'lastname' => 'Tech'],
        'engagement' => Profile::ENGAGEMENT_READY,
    ]);
    ProfileOffice::create([
        'profile_id' => $matchedProfile->id,
        'office_id' => 'OFFICE-1',
    ]);
    $this->matched->roles()->attach($role->id);

    $this->unmatched = User::factory()->create();
    Profile::create([
        'user_id' => $this->unmatched->id,
        'display_name' => 'Unmatched Tech',
        'name' => ['firstname' => 'Unmatched', 'lastname' => 'Tech'],
        'engagement' => Profile::ENGAGEMENT_READY,
    ]);
    $this->unmatched->roles()->attach($role->id);

    $this->itService = ItService::factory()->create();
});

test('only the office-matched technician is emailed; the rest only get the in-app notification', function () {
    Notification::fake();

    Ticket::create([
        'profile_id' => Profile::factory()->create()->id,
        'it_service_id' => $this->itService->id,
        'ticket_number' => 'NOTIFY-0001',
        'concern' => 'Test concern',
        'query_status' => TicketStatus::Queued,
        'request_status' => TicketStatus::Open,
        'office_id' => 'OFFICE-1',
        'complexity_level_id' => TicketComplexityLevel::factory()->create()->id,
    ]);

    Notification::assertSentTo(
        $this->matched,
        TicketCreatedNotification::class,
        fn ($notification) => in_array('mail', $notification->via($this->matched), true)
    );

    Notification::assertSentTo(
        $this->unmatched,
        TicketCreatedNotification::class,
        fn ($notification) => ! in_array('mail', $notification->via($this->unmatched), true)
    );
});

test('via only includes the mail channel for a priority match', function () {
    $ticket = Ticket::factory()->make();

    expect((new TicketCreatedNotification($ticket, true))->via($this->matched))
        ->toBe(['database', 'mail']);

    expect((new TicketCreatedNotification($ticket, false))->via($this->unmatched))
        ->toBe(['database']);
});
