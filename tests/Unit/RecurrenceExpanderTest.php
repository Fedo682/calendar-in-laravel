<?php

use App\Enums\EventVisibility;
use App\Support\Calendar\Occurrence;
use App\Support\Calendar\RecurrenceExpander;
use App\Support\Calendar\RedactedEvent;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Everything about expansion that is not a DST question - bounding,
 * termination, exclusion and substitution.
 *
 * No database: the expander works on value objects, so the whole surface can
 * be driven directly. The Laravel app is still needed, for the configured
 * caps.
 */
uses(TestCase::class);

/**
 * An override standing in for the instance at $recurrenceId, keyed the way
 * OccurrenceQuery keys them.
 *
 * @return Collection<int, RedactedEvent>
 */
function overrideFor(string $recurrenceId, string $newStart, string $title = 'Moved', int $durationMinutes = 60): Illuminate\Support\Collection
{
    $startsAt = CarbonImmutable::parse($newStart, 'UTC')->utc();
    $instance = CarbonImmutable::parse($recurrenceId, 'UTC')->utc();

    return collect([
        $instance->getTimestamp() => new RedactedEvent(
            id: 99,
            calendarId: 1,
            groupId: 1,
            title: $title,
            description: null,
            location: null,
            startsAt: $startsAt,
            endsAt: $startsAt->addMinutes($durationMinutes),
            allDay: false,
            visibility: EventVisibility::Public,
            isRedacted: false,
            canEdit: true,
            createdBy: 1,
            calendarName: 'Team',
            calendarColor: null,
            groupName: 'Acme',
            recurrenceParentId: 1,
            recurrenceInstanceId: $instance,
        ),
    ]);
}

test('an infinite series is bounded by the requested window, not by the rule', function () {
    // The rule never ends, so the only thing that can stop expansion is the
    // window. A series that yields more than the window contains is the bug
    // this bounds check exists for.
    $master = recurringMaster('2026-01-05 09:00', 'UTC', 'FREQ=DAILY');

    expect(expandSeries($master, '2026-01-05 00:00', '2026-01-12 00:00'))->toHaveCount(7)
        ->and(expandSeries($master, '2026-01-05 00:00', '2026-01-08 00:00'))->toHaveCount(3);
});

test('a window before the series begins yields nothing', function () {
    $master = recurringMaster('2026-06-01 09:00', 'UTC', 'FREQ=DAILY');

    expect(expandSeries($master, '2026-01-01 00:00', '2026-01-31 00:00'))->toBe([]);
});

test('COUNT terminates the series', function () {
    $master = recurringMaster('2026-01-05 09:00', 'UTC', 'FREQ=WEEKLY;COUNT=3');

    $starts = localStarts(expandSeries($master, '2026-01-01 00:00', '2026-06-01 00:00'), 'UTC');

    expect($starts)->toBe([
        '2026-01-05 09:00',
        '2026-01-12 09:00',
        '2026-01-19 09:00',
    ]);
});

test('UNTIL terminates the series', function () {
    // UNTIL is inclusive of an occurrence falling exactly on it.
    $master = recurringMaster('2026-01-05 09:00', 'UTC', 'FREQ=WEEKLY;UNTIL=20260119T090000Z');

    $starts = localStarts(expandSeries($master, '2026-01-01 00:00', '2026-06-01 00:00'), 'UTC');

    expect($starts)->toBe([
        '2026-01-05 09:00',
        '2026-01-12 09:00',
        '2026-01-19 09:00',
    ]);
});

test('an EXDATE removes exactly one instance', function () {
    $master = recurringMaster(
        '2026-01-05 09:00',
        'UTC',
        'FREQ=WEEKLY;COUNT=4',
        exdates: ['2026-01-12T09:00:00+00:00'],
    );

    $starts = localStarts(expandSeries($master, '2026-01-01 00:00', '2026-03-01 00:00'), 'UTC');

    expect($starts)->toBe([
        '2026-01-05 09:00',
        '2026-01-19 09:00',
        '2026-01-26 09:00',
    ]);
});

test('an EXDATE written in a different zone still matches the instant', function () {
    // EXDATEs are stored as UTC instants, but a client could send the same
    // moment with an offset. Matching on the timestamp rather than the string
    // is what makes the two equivalent.
    $master = recurringMaster(
        '2026-01-05 09:00',
        'UTC',
        'FREQ=WEEKLY;COUNT=3',
        exdates: ['2026-01-12T11:00:00+02:00'],
    );

    expect(expandSeries($master, '2026-01-01 00:00', '2026-03-01 00:00'))->toHaveCount(2);
});

test('an RDATE adds an instance the rule does not generate', function () {
    $master = recurringMaster(
        '2026-01-05 09:00',
        'UTC',
        'FREQ=WEEKLY;COUNT=2',
        rdates: ['2026-01-07T15:00:00+00:00'],
    );

    $starts = localStarts(expandSeries($master, '2026-01-01 00:00', '2026-03-01 00:00'), 'UTC');

    // Sorted by start, so the extra date lands between the two weekly ones.
    expect($starts)->toBe([
        '2026-01-05 09:00',
        '2026-01-07 15:00',
        '2026-01-12 09:00',
    ]);
});

test('an override replaces the instance it names, at its own time', function () {
    $master = recurringMaster('2026-01-05 09:00', 'UTC', 'FREQ=WEEKLY;COUNT=3');

    $occurrences = expandSeries(
        $master,
        '2026-01-01 00:00',
        '2026-03-01 00:00',
        'UTC',
        overrideFor('2026-01-12 09:00', '2026-01-13 14:00', 'Moved to Tuesday'),
    );

    expect($occurrences)->toHaveCount(3);

    $moved = collect($occurrences)->firstWhere(fn (Occurrence $o) => $o->event->title === 'Moved to Tuesday');

    expect($moved)->not->toBeNull()
        ->and($moved->startsAt->format('Y-m-d H:i'))->toBe('2026-01-13 14:00')
        // The RECURRENCE-ID still points at where the rule put it, which is
        // what keeps the override attached to the instance it replaces.
        ->and($moved->recurrenceId->format('Y-m-d H:i'))->toBe('2026-01-12 09:00');

    // And the instance it replaced is not also rendered in its original slot.
    expect(localStarts($occurrences, 'UTC'))->not->toContain('2026-01-12 09:00');
});

test('an EXDATE beats an override for the same instance', function () {
    // Deleting an instance that also carries an edit has to delete it. The
    // opposite order resurrects a meeting somebody cancelled.
    $master = recurringMaster(
        '2026-01-05 09:00',
        'UTC',
        'FREQ=WEEKLY;COUNT=3',
        exdates: ['2026-01-12T09:00:00+00:00'],
    );

    $occurrences = expandSeries(
        $master,
        '2026-01-01 00:00',
        '2026-03-01 00:00',
        'UTC',
        overrideFor('2026-01-12 09:00', '2026-01-13 14:00', 'Should not appear'),
    );

    expect($occurrences)->toHaveCount(2)
        ->and(array_map(fn (Occurrence $o) => $o->event->title, $occurrences))
        ->not->toContain('Should not appear');
});

test('an override whose instance the rule no longer generates is still shown', function () {
    // This is what happens after "all events" moves a series' start: the
    // override's RECURRENCE-ID stops naming anything. Dropping it silently
    // would delete a meeting nobody asked to delete.
    $master = recurringMaster('2026-01-05 09:00', 'UTC', 'FREQ=WEEKLY;COUNT=3');

    $occurrences = expandSeries(
        $master,
        '2026-01-01 00:00',
        '2026-03-01 00:00',
        'UTC',
        // 2026-01-13 is a Tuesday; the weekly Monday rule never produces it.
        overrideFor('2026-01-13 09:00', '2026-01-14 10:00', 'Orphaned'),
    );

    expect($occurrences)->toHaveCount(4)
        ->and(array_map(fn (Occurrence $o) => $o->event->title, $occurrences))
        ->toContain('Orphaned');
});

test('an occurrence that began before the window but is still running is included', function () {
    // The overlap rule the non-recurring query already used, preserved for
    // series. Filtering on start alone would drop the meeting you are
    // currently sitting in.
    $master = recurringMaster('2026-01-05 09:00', 'UTC', 'FREQ=DAILY', durationMinutes: 180);

    $occurrences = expandSeries($master, '2026-01-06 10:00', '2026-01-06 11:00');

    expect($occurrences)->toHaveCount(1)
        ->and($occurrences[0]->startsAt->format('Y-m-d H:i'))->toBe('2026-01-06 09:00');
});

test('an occurrence starting exactly when the window ends is excluded', function () {
    // Half-open, matching Occurrence::overlaps() and the SQL predicate, so an
    // instance is never counted by two adjacent windows.
    $master = recurringMaster('2026-01-05 09:00', 'UTC', 'FREQ=DAILY');

    $occurrences = expandSeries($master, '2026-01-05 00:00', '2026-01-06 09:00');

    expect(localStarts($occurrences, 'UTC'))->toBe(['2026-01-05 09:00']);
});

test('a single master is capped at max_occurrences_per_window', function () {
    config()->set('calendar.recurrence.max_occurrences_per_window', 10);

    // Hourly over a month is ~720 instances. The cap is what stands between
    // a rule like this and the request that tries to render all of them.
    $master = recurringMaster('2026-01-05 00:00', 'UTC', 'FREQ=HOURLY');

    expect(expandSeries($master, '2026-01-05 00:00', '2026-02-05 00:00'))->toHaveCount(10);
});

test('a window wider than max_window_days is refused', function () {
    config()->set('calendar.recurrence.max_window_days', 30);

    $master = recurringMaster('2026-01-05 09:00', 'UTC', 'FREQ=DAILY');

    expect(fn () => expandSeries($master, '2026-01-01 00:00', '2026-12-31 00:00'))
        ->toThrow(InvalidArgumentException::class);
});

test('a non-recurring event yields one occurrence with no recurrence id', function () {
    // One-offs go through the same path so callers have one, and only one,
    // way to turn a row into occurrences.
    $master = recurringMaster('2026-01-05 09:00', 'UTC', null);

    $occurrences = expandSeries($master, '2026-01-01 00:00', '2026-01-31 00:00');

    expect($occurrences)->toHaveCount(1)
        ->and($occurrences[0]->recurrenceId)->toBeNull()
        ->and($occurrences[0]->key())->toBe('1');
});

test('occurrences of one series get distinct keys', function () {
    // The whole reason Occurrence::key() exists. Keying on the event id would
    // give every instance of a series the same React key.
    $master = recurringMaster('2026-01-05 09:00', 'UTC', 'FREQ=WEEKLY;COUNT=4');

    $keys = array_map(fn (Occurrence $o) => $o->key(), expandSeries($master, '2026-01-01 00:00', '2026-03-01 00:00'));

    expect($keys)->toHaveCount(4)
        ->and(array_unique($keys))->toHaveCount(4);
});

test('occurrenceAt resolves an instance the series produces and refuses one it does not', function () {
    $expander = app(RecurrenceExpander::class);
    $master = recurringMaster('2026-01-05 09:00', 'UTC', 'FREQ=WEEKLY;COUNT=4');

    $real = $expander->occurrenceAt($master, CarbonImmutable::parse('2026-01-12 09:00', 'UTC'), collect());
    $imaginary = $expander->occurrenceAt($master, CarbonImmutable::parse('2026-01-13 09:00', 'UTC'), collect());

    expect($real)->not->toBeNull()
        ->and($real->startsAt->format('Y-m-d H:i'))->toBe('2026-01-12 09:00')
        ->and($imaginary)->toBeNull();
});

test('occurrenceAt refuses an instance that has been excluded', function () {
    $expander = app(RecurrenceExpander::class);
    $master = recurringMaster(
        '2026-01-05 09:00',
        'UTC',
        'FREQ=WEEKLY;COUNT=4',
        exdates: ['2026-01-12T09:00:00+00:00'],
    );

    expect($expander->occurrenceAt($master, CarbonImmutable::parse('2026-01-12 09:00', 'UTC'), collect()))
        ->toBeNull();
});
