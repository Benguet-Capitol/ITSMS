<?php

use App\Models\Profile;
use App\Models\User;
use Carbon\Carbon;

/**
 * Covers the online/idle/offline presence system: the three heartbeat
 * endpoints (PUT me/heartbeat, me/stop-heartbeat, me/idle) and the
 * scheduled `users:mark-offline` command that catches sessions that never
 * called stop-heartbeat (browser crash, lost network, closed laptop).
 *
 * A real bug fixed alongside these tests: all three status transitions
 * (and the scheduled command) used to null out `engagement` (ready/busy),
 * which is actually maintained independently by ProfileEngagementService
 * off a profile's active tickets. A technician who stepped away from an
 * accepted ticket for 2+ minutes would silently lose their "busy"
 * indicator on return, until their next ticket action.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    $this->profile = Profile::create([
        'user_id' => $this->user->id,
        'display_name' => 'Presence Tester',
        'name' => ['firstname' => 'Presence', 'lastname' => 'Tester'],
        'gender' => 'male',
        'designation' => 'Tester',
        'status' => Profile::STATUS_OFFLINE,
        'engagement' => Profile::ENGAGEMENT_BUSY,
    ]);

    $this->actingAs($this->user);
});

test('heartbeat marks the profile online, stamps last_seen_at, and leaves engagement untouched', function () {
    Carbon::setTestNow('2026-09-11 09:00:00');

    $response = $this->putJson('/api/me/heartbeat');

    $response->assertNoContent();
    $this->profile->refresh();

    expect($this->profile->status)->toBe(Profile::STATUS_ONLINE);
    // last_seen_at isn't cast to a Carbon datetime on the model (every
    // consumer, e.g. UserController::onlineList(), parses it manually) --
    // compare as a raw string rather than assuming a cast that isn't there.
    expect(Carbon::parse($this->profile->last_seen_at)->toDateTimeString())->toBe('2026-09-11 09:00:00');
    expect($this->profile->engagement)->toBe(Profile::ENGAGEMENT_BUSY);
});

test('stop-heartbeat marks the profile offline and leaves engagement untouched', function () {
    $this->putJson('/api/me/heartbeat')->assertNoContent();

    $response = $this->putJson('/api/me/stop-heartbeat');

    $response->assertNoContent();
    $this->profile->refresh();

    expect($this->profile->status)->toBe(Profile::STATUS_OFFLINE);
    expect($this->profile->engagement)->toBe(Profile::ENGAGEMENT_BUSY);
});

test('marking idle leaves engagement untouched', function () {
    $this->putJson('/api/me/heartbeat')->assertNoContent();

    $response = $this->putJson('/api/me/idle');

    $response->assertNoContent();
    $this->profile->refresh();

    expect($this->profile->status)->toBe(Profile::STATUS_IDLE);
    expect($this->profile->engagement)->toBe(Profile::ENGAGEMENT_BUSY);
});

test('the online-list endpoint exposes both status and engagement', function () {
    $this->putJson('/api/me/heartbeat')->assertNoContent();

    $response = $this->getJson('/api/users/online-list');

    $response->assertSuccessful();

    $entry = collect($response->json())->firstWhere('id', $this->user->id);

    expect($entry)->not->toBeNull();
    expect($entry['status'])->toBe(Profile::STATUS_ONLINE);
    expect($entry['engagement'])->toBe(Profile::ENGAGEMENT_BUSY);
});

test('the mark-offline command idles a stale online profile without touching engagement', function () {
    Carbon::setTestNow('2026-09-11 09:00:00');
    $this->profile->update([
        'status' => Profile::STATUS_ONLINE,
        'last_seen_at' => now(),
    ]);

    Carbon::setTestNow('2026-09-11 09:03:00'); // 3 minutes later, past the 2-minute idle threshold

    $this->artisan('users:mark-offline')->assertSuccessful();

    $this->profile->refresh();

    expect($this->profile->status)->toBe(Profile::STATUS_IDLE);
    expect($this->profile->engagement)->toBe(Profile::ENGAGEMENT_BUSY);
});

test('the mark-offline command does not touch a profile still within the idle threshold', function () {
    Carbon::setTestNow('2026-09-11 09:00:00');
    $this->profile->update([
        'status' => Profile::STATUS_ONLINE,
        'last_seen_at' => now(),
    ]);

    Carbon::setTestNow('2026-09-11 09:01:00'); // 1 minute later, within the 2-minute threshold

    $this->artisan('users:mark-offline')->assertSuccessful();

    $this->profile->refresh();

    expect($this->profile->status)->toBe(Profile::STATUS_ONLINE);
});

test('the mark-offline command marks a stale online or idle profile fully offline after 15 minutes', function () {
    Carbon::setTestNow('2026-09-11 09:00:00');
    $this->profile->update([
        'status' => Profile::STATUS_IDLE,
        'last_seen_at' => now(),
    ]);

    Carbon::setTestNow('2026-09-11 09:16:00'); // 16 minutes later, past the 15-minute offline threshold

    $this->artisan('users:mark-offline')->assertSuccessful();

    $this->profile->refresh();

    expect($this->profile->status)->toBe(Profile::STATUS_OFFLINE);
    expect($this->profile->engagement)->toBe(Profile::ENGAGEMENT_BUSY);
});

test('the mark-offline command leaves an already-offline profile alone', function () {
    Carbon::setTestNow('2026-09-11 09:00:00');
    $this->profile->update([
        'status' => Profile::STATUS_OFFLINE,
        'last_seen_at' => now()->subHours(2),
    ]);

    $this->artisan('users:mark-offline')->assertSuccessful();

    $this->profile->refresh();

    expect($this->profile->status)->toBe(Profile::STATUS_OFFLINE);
});

test('the mark-offline command ignores a profile with no last_seen_at at all', function () {
    $this->profile->update([
        'status' => Profile::STATUS_ONLINE,
        'last_seen_at' => null,
    ]);

    $this->artisan('users:mark-offline')->assertSuccessful();

    $this->profile->refresh();

    expect($this->profile->status)->toBe(Profile::STATUS_ONLINE);
});
