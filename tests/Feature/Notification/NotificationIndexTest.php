<?php

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * The index endpoint used to return Laravel's raw paginator (a flat
 * current_page/total/data shape) while every other list endpoint in this
 * app -- and the frontend store reading it -- expects {data, meta: {total,
 * ...}}. That mismatch meant `meta.total` was always undefined on the
 * frontend, so `totalNotifications` was silently stuck at 0.
 *
 * Separately, the bell badge's unread count used to be derived from
 * whatever notifications happened to be on the currently loaded page(s)
 * rather than a true total, so it silently undercounted once a user had
 * more unread notifications than fit on one page.
 */
function createNotificationFor(User $user, bool $read = false): DatabaseNotification
{
    return DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TicketCreatedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['notification_type' => 'ticket_created', 'ticket_id' => 1],
        'read_at' => $read ? now() : null,
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('the index endpoint returns data/meta shaped pagination', function () {
    collect(range(1, 25))->each(fn () => createNotificationFor($this->user));

    $response = $this->getJson('/api/notifications');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(20);
    expect($response->json('meta.total'))->toBe(25);
    expect($response->json('meta.per_page'))->toBe(20);
    expect($response->json('meta.current_page'))->toBe(1);
    expect($response->json('meta.last_page'))->toBe(2);
});

test('meta.unread_count reflects every unread notification, not just the current page', function () {
    collect(range(1, 25))->each(fn () => createNotificationFor($this->user));

    $response = $this->getJson('/api/notifications?per_page=20');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(20);
    expect($response->json('meta.unread_count'))->toBe(25);
});

test('meta.unread_count excludes already-read notifications', function () {
    collect(range(1, 5))->each(fn () => createNotificationFor($this->user, read: true));
    collect(range(1, 3))->each(fn () => createNotificationFor($this->user, read: false));

    $response = $this->getJson('/api/notifications');

    $response->assertSuccessful();
    expect($response->json('meta.unread_count'))->toBe(3);
});

test('a second page returns the older notifications not present on page one', function () {
    collect(range(1, 25))->each(fn () => createNotificationFor($this->user));

    $firstPage = $this->getJson('/api/notifications?page=1')->json('data');
    $secondPage = $this->getJson('/api/notifications?page=2')->json('data');

    expect($secondPage)->toHaveCount(5);

    $firstPageIds = collect($firstPage)->pluck('id');
    $secondPageIds = collect($secondPage)->pluck('id');

    expect($firstPageIds->intersect($secondPageIds))->toBeEmpty();
});

test('a notification only belonging to another user is not returned', function () {
    $otherUser = User::factory()->create();
    createNotificationFor($otherUser);
    createNotificationFor($this->user);

    $response = $this->getJson('/api/notifications');

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('meta.total'))->toBe(1);
});
