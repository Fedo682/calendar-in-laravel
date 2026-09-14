<?php

use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;

/**
 * A personal calendar is not administrable.
 *
 * Gate::before grants a Super Admin every ability, which is right for groups,
 * calendars and events that belong to the organisation. It was also letting
 * them write to calendars that belong to a person - creating, editing and
 * deleting entries on someone else's private calendar.
 *
 * Redaction already stopped them reading the contents; nothing stopped them
 * writing. Read-only is the line: a Super Admin can see that the calendar
 * exists and that its owner is busy, and can do nothing to it.
 */

/** @return array{0: User, 1: User, 2: Calendar, 3: Event} */
function personalCalendarOf(): array
{
    $superAdmin = asSuperAdmin();
    $owner = User::factory()->create();
    $calendar = $owner->personalCalendar();

    $event = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'created_by' => $owner->id,
        'title' => 'Therapy',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    return [$superAdmin, $owner, $calendar, $event];
}

test('a super admin cannot create events on someone elses personal calendar', function () {
    [$superAdmin, $owner, $calendar] = personalCalendarOf();

    expect($superAdmin->can('create', [Event::class, $calendar]))->toBeFalse();
});

test('a super admin cannot edit or delete events on someone elses personal calendar', function () {
    [$superAdmin, $owner, $calendar, $event] = personalCalendarOf();

    expect($superAdmin->can('update', $event))->toBeFalse()
        ->and($superAdmin->can('delete', $event))->toBeFalse();
});

test('a super admin cannot rename or delete someone elses personal calendar', function () {
    [$superAdmin, $owner, $calendar] = personalCalendarOf();

    expect($superAdmin->can('update', $calendar))->toBeFalse()
        ->and($superAdmin->can('delete', $calendar))->toBeFalse();
});

test('a super admin may still see that the calendar exists', function () {
    // Read-only, not invisible. What they can actually read is already
    // reduced to "Busy" by EventRedactor.
    [$superAdmin, $owner, $calendar, $event] = personalCalendarOf();

    expect($superAdmin->can('view', $calendar))->toBeTrue()
        ->and($superAdmin->can('view', $event))->toBeTrue();
});

test('the owner is unaffected', function () {
    [$superAdmin, $owner, $calendar, $event] = personalCalendarOf();

    expect($owner->can('create', [Event::class, $calendar]))->toBeTrue()
        ->and($owner->can('update', $event))->toBeTrue()
        ->and($owner->can('delete', $event))->toBeTrue();
});

test('a super admin keeps full control of group calendars', function () {
    // The narrowing must not cost them anything on calendars that belong to
    // the organisation rather than to a person.
    $superAdmin = asSuperAdmin();
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $event = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    expect($superAdmin->can('create', [Event::class, $calendar]))->toBeTrue()
        ->and($superAdmin->can('update', $event))->toBeTrue()
        ->and($superAdmin->can('delete', $event))->toBeTrue()
        ->and($superAdmin->can('update', $calendar))->toBeTrue();
});

test('a super admin still manages their own personal calendar', function () {
    $superAdmin = asSuperAdmin();
    $own = $superAdmin->personalCalendar();

    expect($superAdmin->can('create', [Event::class, $own]))->toBeTrue()
        ->and($superAdmin->can('update', $own))->toBeTrue();
});

test('the HTTP routes refuse the write too', function () {
    [$superAdmin, $owner, $calendar, $event] = personalCalendarOf();

    // The policy is the guard, but assert through the routes as well - this
    // is the surface an actual request arrives on.
    $this->actingAs($superAdmin)
        ->put("/calendars/personal/events/{$event->id}", [
            'title' => 'Rewritten by an admin',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
        ])
        ->assertForbidden();

    $this->actingAs($superAdmin)
        ->delete("/calendars/personal/events/{$event->id}")
        ->assertForbidden();

    expect($event->fresh()->title)->toBe('Therapy');
});
