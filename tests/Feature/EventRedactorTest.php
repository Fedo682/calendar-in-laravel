<?php

use App\Enums\EventVisibility;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use App\Support\Calendar\EventRedactor;

/**
 * The redaction table, cell by cell.
 *
 *  visibility | owner        | anybody else
 *  -----------|--------------|-------------
 *  public     | full         | full
 *  private    | full         | redacted
 *  busy       | redacted     | redacted
 */
function eventWithVisibility(EventVisibility $visibility, User $owner, Group $group): Event
{
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    return Event::factory()->create([
        'calendar_id' => $calendar->id,
        'created_by' => $owner->id,
        'title' => 'Real title',
        'description' => 'Real description',
        'location' => 'Real location',
        'visibility' => $visibility,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);
}

dataset('visibility matrix', [
    // [visibility, viewerIsOwner, expectsDetails]
    'public, owner' => [EventVisibility::Public, true, true],
    'public, other' => [EventVisibility::Public, false, true],
    'private, owner' => [EventVisibility::Private, true, true],
    'private, other' => [EventVisibility::Private, false, false],
    'busy, owner' => [EventVisibility::Busy, true, false],
    'busy, other' => [EventVisibility::Busy, false, false],
]);

test('redaction follows the visibility matrix', function (
    EventVisibility $visibility,
    bool $viewerIsOwner,
    bool $expectsDetails,
) {
    $group = Group::factory()->create();
    $owner = User::factory()->create();
    $other = User::factory()->create();

    asGroupRole($group, $owner, 'admin');
    asGroupRole($group, $other, 'admin');

    $event = eventWithVisibility($visibility, $owner, $group);
    $viewer = $viewerIsOwner ? $owner : $other;

    $redacted = app(EventRedactor::class)->redact($event->fresh(), $viewer);

    if ($expectsDetails) {
        expect($redacted->title)->toBe('Real title')
            ->and($redacted->description)->toBe('Real description')
            ->and($redacted->location)->toBe('Real location')
            ->and($redacted->isRedacted)->toBeFalse();

        return;
    }

    expect($redacted->title)->toBe(EventVisibility::REDACTED_TITLE)
        ->and($redacted->description)->toBeNull()
        ->and($redacted->location)->toBeNull()
        ->and($redacted->isRedacted)->toBeTrue();
})->with('visibility matrix');

test('times are never redacted, because busy time is the point', function () {
    $group = Group::factory()->create();
    $owner = User::factory()->create();
    $other = User::factory()->create();

    asGroupRole($group, $owner, 'admin');
    asGroupRole($group, $other, 'admin');

    $event = eventWithVisibility(EventVisibility::Private, $owner, $group);

    $redacted = app(EventRedactor::class)->redact($event->fresh(), $other);

    expect($redacted->startsAt->equalTo($event->starts_at))->toBeTrue()
        ->and($redacted->endsAt->equalTo($event->ends_at))->toBeTrue()
        ->and($redacted->allDay)->toBe($event->all_day);
});

test('the owner of a personal calendar sees events an admin created on it', function () {
    // An admin can create an event on someone's behalf; owning the calendar
    // still confers the right to read what is on it.
    $owner = User::factory()->create();
    $admin = User::factory()->create();

    $calendar = $owner->personalCalendar();

    $event = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'created_by' => $admin->id,
        'title' => 'Booked for you',
        'visibility' => EventVisibility::Private,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $redacted = app(EventRedactor::class)->redact($event->fresh(), $owner);

    expect($redacted->title)->toBe('Booked for you')
        ->and($redacted->isRedacted)->toBeFalse();
});

test('canSeeDetails agrees with what redact actually returns', function () {
    $group = Group::factory()->create();
    $owner = User::factory()->create();
    $other = User::factory()->create();

    asGroupRole($group, $owner, 'admin');
    asGroupRole($group, $other, 'admin');

    $redactor = app(EventRedactor::class);
    $event = eventWithVisibility(EventVisibility::Private, $owner, $group)->fresh();

    expect($redactor->canSeeDetails($event, $owner))->toBeTrue()
        ->and($redactor->redact($event, $owner)->isRedacted)->toBeFalse()
        ->and($redactor->canSeeDetails($event, $other))->toBeFalse()
        ->and($redactor->redact($event, $other)->isRedacted)->toBeTrue();
});

test('redactMany does not issue a role query per event', function () {
    $group = Group::factory()->create();
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    asGroupRole($group, $owner, 'admin');
    asGroupRole($group, $viewer, 'member');

    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    Event::factory()->count(25)->create([
        'calendar_id' => $calendar->id,
        'created_by' => $owner->id,
        'visibility' => EventVisibility::Private,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $events = Event::with('calendar.group')->get();

    DB::enableQueryLog();
    $redacted = app(EventRedactor::class)->redactMany($events, $viewer);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($redacted)->toHaveCount(25)
        // The exact number matters less than it not scaling with the event
        // count - before the group memo this was one query per event.
        ->and($queries)->toBeLessThan(10);
});

test('a redacted personal-calendar event carries the owner name', function () {
    $owner = User::factory()->create(['name' => 'Jane Smith']);
    $viewer = User::factory()->create();
    $personal = $owner->personalCalendar();

    $event = Event::factory()->create([
        'calendar_id' => $personal->id,
        'visibility' => EventVisibility::Private,
        'created_by' => $owner->id,
    ]);

    $redacted = app(EventRedactor::class)->redact($event, $viewer);

    expect($redacted->isRedacted)->toBeTrue();
    expect($redacted->title)->toBe('Busy');
    expect($redacted->ownerName)->toBe('Jane Smith');
});

test('a visible personal-calendar event carries no owner name', function () {
    $owner = User::factory()->create(['name' => 'Jane Smith']);
    $viewer = User::factory()->create();
    $personal = $owner->personalCalendar();

    $event = Event::factory()->create([
        'calendar_id' => $personal->id,
        'visibility' => EventVisibility::Public,
        'created_by' => $owner->id,
    ]);

    $redacted = app(EventRedactor::class)->redact($event, $viewer);

    expect($redacted->isRedacted)->toBeFalse();
    expect($redacted->ownerName)->toBeNull();
});

test('a redacted group-calendar event carries no owner name', function () {
    $group = Group::factory()->create();
    $creator = User::factory()->create();
    asGroupRole($group, $creator, 'member');
    $viewer = User::factory()->create();
    asGroupRole($group, $viewer, 'member');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $event = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'visibility' => EventVisibility::Busy,
        'created_by' => $creator->id,
    ]);

    $redacted = app(EventRedactor::class)->redact($event, $viewer);

    expect($redacted->isRedacted)->toBeTrue();
    expect($redacted->ownerName)->toBeNull();
});

test('an overrides uid matches its masters uid, not its own row id', function () {
    $group = Group::factory()->create();
    $owner = User::factory()->create();
    asGroupRole($group, $owner, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $master = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'created_by' => $owner->id,
        'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
        'recurrence_timezone' => 'Europe/Berlin',
        'starts_at' => now()->addWeek()->startOfWeek(),
        'ends_at' => now()->addWeek()->startOfWeek()->addHour(),
    ]);
    $override = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'created_by' => $owner->id,
        'recurrence_parent_id' => $master->id,
        'recurrence_id' => $master->starts_at,
        'starts_at' => $master->starts_at->copy()->addHour(),
        'ends_at' => $master->ends_at->copy()->addHour(),
    ]);

    $redactor = app(EventRedactor::class);
    $masterRedacted = $redactor->redact($master->fresh(), $owner);
    $overrideRedacted = $redactor->redact($override->fresh(), $owner);

    expect($overrideRedacted->uid('example.test'))
        ->toBe($masterRedacted->uid('example.test'));
});
