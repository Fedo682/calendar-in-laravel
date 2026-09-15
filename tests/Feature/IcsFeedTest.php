<?php

use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use App\Support\Calendar\CalendarFeedTokenService;
use Illuminate\Support\Carbon;

test('a valid token returns the calendar', function () {
    $user = User::factory()->create();
    $plaintext = issueFeedToken($user);

    $response = $this->get("/feed/{$plaintext}.ics");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/calendar');
});

test('an invalid token 404s rather than 403s', function () {
    $this->get('/feed/not-a-real-token.ics')->assertNotFound();
});

test('a revoked token 404s', function () {
    $user = User::factory()->create();
    $service = app(CalendarFeedTokenService::class);
    ['token' => $token, 'plaintext' => $plaintext] = $service->issue($user);
    $service->revoke($token);

    $this->get("/feed/{$plaintext}.ics")->assertNotFound();
});

test('a matching If-None-Match returns 304 without rebuilding', function () {
    $user = User::factory()->create();
    $plaintext = issueFeedToken($user);

    $first = $this->get("/feed/{$plaintext}.ics");
    $etag = $first->headers->get('ETag');

    $second = $this->withHeaders(['If-None-Match' => $etag])->get("/feed/{$plaintext}.ics");

    $second->assertStatus(304);
});

test('editing an event changes the fingerprint so the next poll is not a 304', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $plaintext = issueFeedToken($user);

    $first = $this->get("/feed/{$plaintext}.ics");
    $etag = $first->headers->get('ETag');

    // The fingerprint is only as precise as updated_at's own column
    // precision (whole seconds) - a fast test run can touch() within the
    // same second it was created in, which would prove nothing. Carbon's
    // test clock is what actually exercises "the fingerprint changed,"
    // rather than relying on wall-clock luck - save() auto-touches
    // updated_at to whatever "now" resolves to, so moving that forward
    // is what a manually-assigned updated_at can't achieve on its own.
    Carbon::setTestNow(now()->addMinute());
    $event->touch();
    Carbon::setTestNow();

    $second = $this->withHeaders(['If-None-Match' => $etag])->get("/feed/{$plaintext}.ics");

    $second->assertOk();
});

test('fetching the feed records last_used_at', function () {
    $user = User::factory()->create();
    $service = app(CalendarFeedTokenService::class);
    ['token' => $token, 'plaintext' => $plaintext] = $service->issue($user);

    expect($token->last_used_at)->toBeNull();

    $this->get("/feed/{$plaintext}.ics");

    expect($token->fresh()->last_used_at)->not->toBeNull();
});

test('only calendars the token owner can see appear in their feed', function () {
    $groupA = Group::factory()->create();
    $groupB = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($groupA, $user, 'admin');

    $calendarA = Calendar::factory()->create(['group_id' => $groupA->id]);
    $calendarB = Calendar::factory()->create(['group_id' => $groupB->id]);

    Event::factory()->create(['calendar_id' => $calendarA->id, 'title' => 'Mine']);
    Event::factory()->create(['calendar_id' => $calendarB->id, 'title' => 'Not mine']);

    $plaintext = issueFeedToken($user);

    $ics = $this->get("/feed/{$plaintext}.ics")->getContent();

    expect($ics)->toContain('Mine')->not->toContain('Not mine');
});
