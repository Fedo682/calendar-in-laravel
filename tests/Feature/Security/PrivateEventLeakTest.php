<?php

use App\Enums\EventVisibility;
use App\Mail\ConflictReportedMail;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * The safety net for the redaction chokepoint.
 *
 * Every endpoint that emits events is listed in the dataset below. A new one
 * that forgets to go through OccurrenceQuery fails here rather than quietly
 * shipping someone's private appointments. Later phases add the ICS feed and
 * the Google push to the same list.
 */

/**
 * A private event owned by $owner, on a group calendar $other can also see.
 *
 * @return array{0: User, 1: User, 2: Group, 3: Calendar, 4: Event}
 */
function makePrivateEventScenario(): array
{
    $group = Group::factory()->create();
    $owner = User::factory()->create();
    $other = User::factory()->create();

    asGroupRole($group, $owner, 'admin');
    asGroupRole($group, $other, 'admin');

    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $event = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'created_by' => $owner->id,
        'title' => 'Oncology appointment',
        'description' => 'Results review',
        'location' => 'St Thomas Hospital',
        'visibility' => EventVisibility::Private,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    return [$owner, $other, $group, $calendar, $event];
}

/**
 * @return array<string, array{0: callable(User, Group, Calendar): string}>
 */
dataset('event emitting endpoints', [
    'dashboard' => [fn (User $user, Group $group, Calendar $calendar) => '/dashboard'],
    'events index' => [fn (User $user, Group $group, Calendar $calendar) => "/groups/{$group->id}/calendars/{$calendar->id}/events"],
]);

test('a private event never leaks its details to another user', function (callable $url) {
    [$owner, $other, $group, $calendar] = makePrivateEventScenario();

    $body = $this->actingAs($other)->get($url($other, $group, $calendar))->getContent();

    expect($body)->not->toContain('Oncology appointment')
        ->and($body)->not->toContain('Results review')
        ->and($body)->not->toContain('St Thomas Hospital')
        ->and($body)->toContain('Busy');
})->with('event emitting endpoints');

test('the owner of a private event still sees it in full', function (callable $url) {
    [$owner, $other, $group, $calendar] = makePrivateEventScenario();

    $body = $this->actingAs($owner)->get($url($owner, $group, $calendar))->getContent();

    expect($body)->toContain('Oncology appointment');
})->with('event emitting endpoints');

test('a busy event hides its details even from its own author', function () {
    [$owner, $other, $group, $calendar, $event] = makePrivateEventScenario();

    // 'busy' is the stronger setting: it is for blocking out time without
    // telling anyone what the time is for, the author's own views included.
    $event->update(['visibility' => EventVisibility::Busy]);

    $body = $this->actingAs($owner)->get('/dashboard')->getContent();

    expect($body)->not->toContain('Oncology appointment')
        ->and($body)->toContain('Busy');
});

test('a public event is visible to everyone on the calendar', function () {
    [$owner, $other, $group, $calendar, $event] = makePrivateEventScenario();

    $event->update(['visibility' => EventVisibility::Public]);

    $body = $this->actingAs($other)->get('/dashboard')->getContent();

    expect($body)->toContain('Oncology appointment');
});

test('a super admin cannot read the details of someone elses private event', function () {
    [$owner, $other, $group, $calendar] = makePrivateEventScenario();

    // Gate::before lets a Super Admin past every policy, but administering
    // the platform is not the same as being entitled to read the contents of
    // someone's private appointments - redaction is deliberately not a
    // policy check, so the bypass does not reach it.
    $superAdmin = asSuperAdmin();

    $body = $this->actingAs($superAdmin)->get('/dashboard')->getContent();

    expect($body)->not->toContain('Oncology appointment')
        ->and($body)->not->toContain('Results review');
});

test('a private event on a personal calendar is invisible to everyone else', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    $admin = User::factory()->create();

    asGroupRole($group, $member, 'member');
    asGroupRole($group, $admin, 'admin');

    $personal = $member->personalCalendar();

    Event::factory()->create([
        'calendar_id' => $personal->id,
        'created_by' => $member->id,
        'title' => 'Therapy session',
        'visibility' => EventVisibility::Private,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    // The admin cannot reach the calendar at all, so there is nothing to
    // redact - but assert on the rendered dashboard rather than on the query,
    // because that is where a leak would actually surface.
    $body = $this->actingAs($admin)->get('/dashboard')->getContent();

    expect($body)->not->toContain('Therapy session');

    $ownBody = $this->actingAs($member)->get('/dashboard')->getContent();

    expect($ownBody)->toContain('Therapy session');
});

test('reporting a conflict does not email the title of a private event', function () {
    $group = Group::factory()->create();
    $owner = User::factory()->create();
    $reporter = User::factory()->create();

    asGroupRole($group, $owner, 'admin');
    asGroupRole($group, $reporter, 'member');

    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $private = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'created_by' => $owner->id,
        'title' => 'Oncology appointment',
        'visibility' => EventVisibility::Private,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(2),
    ]);

    $public = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'created_by' => $owner->id,
        'title' => 'Team sync',
        'visibility' => EventVisibility::Public,
        'starts_at' => now()->addDay()->addHour(),
        'ends_at' => now()->addDay()->addHours(3),
    ]);

    Mail::fake();

    $this->actingAs($reporter)
        ->post("/groups/{$group->id}/calendars/{$calendar->id}/events/{$public->id}/report-conflict")
        ->assertRedirect();

    // Before the chokepoint this path did ->pluck('title') straight off the
    // model and mailed it to every admin in the group.
    Mail::assertQueued(
        ConflictReportedMail::class,
        fn ($mail) => ! $mail->conflictingTitles->contains('Oncology appointment')
            && $mail->conflictingTitles->contains(EventVisibility::REDACTED_TITLE),
    );

    expect($private->fresh()->title)->toBe('Oncology appointment');
});

test('a private event never leaks its details into the ICS feed', function () {
    [$owner, $other, $group, $calendar] = makePrivateEventScenario();

    $plaintext = issueFeedToken($other);
    $body = $this->get("/feed/{$plaintext}.ics")->getContent();

    expect($body)->not->toContain('Oncology appointment')
        ->and($body)->not->toContain('Results review')
        ->and($body)->not->toContain('St Thomas Hospital')
        ->and($body)->toContain('SUMMARY:Busy');
});
