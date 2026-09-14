<?php

use App\Enums\EventVisibility;
use App\Support\Calendar\Occurrence;
use App\Support\Calendar\RedactedEvent;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * The tests this whole design exists for.
 *
 * A weekly 09:00 standup is 09:00 to the people in the room, not "13:00 UTC".
 * Those two descriptions agree for about five months of the year and then
 * diverge by an hour, and because they diverge on a Sunday in March nobody
 * notices until Monday's meeting is at the wrong time. Expanding from the UTC
 * instant alone produces exactly that bug, which is why recurrence_timezone
 * is mandatory and why these are the first tests in the phase.
 *
 * Both directions of both hemispheres' usual transitions are covered, and the
 * two zones deliberately shift on different dates - the US moved to March and
 * November in 2007, the EU still uses the last Sundays in March and October -
 * so a fix that happens to work for one is not mistaken for a fix.
 *
 * The Laravel app is needed for config() (the expansion caps); the database
 * is not, because the expander works on value objects.
 */
uses(TestCase::class);

test('a weekly series stays at 09:00 local across the US spring forward', function () {
    // America/New_York springs forward on 2026-03-08, between the Wednesday
    // occurrences on the 4th and the 11th.
    $master = recurringMaster('2026-03-04 09:00', 'America/New_York', 'FREQ=WEEKLY;BYDAY=WE');

    $starts = localStarts(
        expandSeries($master, '2026-03-01 00:00', '2026-03-26 00:00', 'America/New_York'),
        'America/New_York',
    );

    expect($starts)->toBe([
        '2026-03-04 09:00',
        '2026-03-11 09:00',
        '2026-03-18 09:00',
        '2026-03-25 09:00',
    ]);
});

test('the UTC instant shifts by an hour across the US spring forward', function () {
    // The mirror of the test above, and the one that would fail if expansion
    // ran in UTC. Local time holding still *requires* the underlying instant
    // to move; a series whose UTC times are all identical is the bug.
    $master = recurringMaster('2026-03-04 09:00', 'America/New_York', 'FREQ=WEEKLY;BYDAY=WE');

    $utc = array_map(
        fn (Occurrence $o) => $o->startsAt->format('Y-m-d H:i'),
        expandSeries($master, '2026-03-01 00:00', '2026-03-19 00:00', 'America/New_York'),
    );

    expect($utc)->toBe([
        '2026-03-04 14:00', // EST, UTC-5
        '2026-03-11 13:00', // EDT, UTC-4
        '2026-03-18 13:00',
    ]);
});

test('a weekly series stays at 09:00 local across the US fall back', function () {
    // America/New_York falls back on 2026-11-01.
    $master = recurringMaster('2026-10-21 09:00', 'America/New_York', 'FREQ=WEEKLY;BYDAY=WE');

    $starts = localStarts(
        expandSeries($master, '2026-10-19 00:00', '2026-11-19 00:00', 'America/New_York'),
        'America/New_York',
    );

    expect($starts)->toBe([
        '2026-10-21 09:00',
        '2026-10-28 09:00',
        '2026-11-04 09:00',
        '2026-11-11 09:00',
        '2026-11-18 09:00',
    ]);
});

test('a weekly series stays at 09:00 local across the European spring forward', function () {
    // Europe/Berlin springs forward on 2026-03-29 - three weeks after New
    // York, which is exactly why a zone-agnostic fix is not a fix.
    $master = recurringMaster('2026-03-16 09:00', 'Europe/Berlin', 'FREQ=WEEKLY;BYDAY=MO');

    $starts = localStarts(
        expandSeries($master, '2026-03-15 00:00', '2026-04-14 00:00', 'Europe/Berlin'),
        'Europe/Berlin',
    );

    expect($starts)->toBe([
        '2026-03-16 09:00',
        '2026-03-23 09:00',
        '2026-03-30 09:00',
        '2026-04-06 09:00',
        '2026-04-13 09:00',
    ]);
});

test('a weekly series stays at 09:00 local across the European fall back', function () {
    // Europe/Berlin falls back on 2026-10-25.
    $master = recurringMaster('2026-10-12 09:00', 'Europe/Berlin', 'FREQ=WEEKLY;BYDAY=MO');

    $occurrences = expandSeries($master, '2026-10-11 00:00', '2026-11-10 00:00', 'Europe/Berlin');

    expect(localStarts($occurrences, 'Europe/Berlin'))->toBe([
        '2026-10-12 09:00',
        '2026-10-19 09:00',
        '2026-10-26 09:00',
        '2026-11-02 09:00',
        '2026-11-09 09:00',
    ]);

    // The offset really did change underneath the series, so this is a
    // genuine transition rather than a week that happens to look stable.
    expect($occurrences[0]->startsAt->format('H:i'))->toBe('07:00')   // CEST
        ->and($occurrences[2]->startsAt->format('H:i'))->toBe('08:00'); // CET
});

test('a daily series keeps its duration across a spring forward', function () {
    // The end has to move with the start. Storing per-occurrence ends, or
    // deriving the end in UTC while deriving the start locally, makes the
    // meeting on the transition day an hour longer or shorter than every
    // other one.
    $master = recurringMaster('2026-03-06 09:00', 'America/New_York', 'FREQ=DAILY', durationMinutes: 90);

    $durations = array_map(
        fn (Occurrence $o) => $o->endsAt->getTimestamp() - $o->startsAt->getTimestamp(),
        expandSeries($master, '2026-03-06 00:00', '2026-03-11 00:00', 'America/New_York'),
    );

    expect($durations)->toBe([5400, 5400, 5400, 5400, 5400]);
});

test('a daily 02:30 series survives the hour that does not exist', function () {
    // 2026-03-08 02:30 never happens in New York - the clock jumps 02:00 to
    // 03:00. The series must not lose the day, produce a duplicate, or throw.
    $master = recurringMaster('2026-03-06 02:30', 'America/New_York', 'FREQ=DAILY');

    $occurrences = expandSeries($master, '2026-03-06 00:00', '2026-03-11 00:00', 'America/New_York');

    expect($occurrences)->toHaveCount(5);

    $instants = array_map(fn (Occurrence $o) => $o->startsAt->getTimestamp(), $occurrences);
    expect($instants)->toBe(array_values(array_unique($instants)));
});

test('a daily 01:30 series survives the hour that happens twice', function () {
    // 2026-11-01 01:30 occurs twice in New York. Exactly one instance should
    // come out of it, or the calendar shows the same meeting side by side.
    $master = recurringMaster('2026-10-30 01:30', 'America/New_York', 'FREQ=DAILY');

    $occurrences = expandSeries($master, '2026-10-30 00:00', '2026-11-04 00:00', 'America/New_York');

    expect($occurrences)->toHaveCount(5);

    $localStarts = localStarts($occurrences, 'America/New_York');
    expect($localStarts)->toBe(array_values(array_unique($localStarts)));
});

test('two series in different zones expand independently', function () {
    // The same wall-clock rule in two zones is two different sets of
    // instants. Sharing any per-process timezone state between expansions -
    // date_default_timezone_set, say - would make the second one follow the
    // first.
    $berlin = recurringMaster('2026-03-16 09:00', 'Europe/Berlin', 'FREQ=WEEKLY;BYDAY=MO');
    $newYork = recurringMaster('2026-03-16 09:00', 'America/New_York', 'FREQ=WEEKLY;BYDAY=MO');

    $window = ['2026-03-15 00:00', '2026-04-14 00:00'];

    $berlinUtc = array_map(
        fn (Occurrence $o) => $o->startsAt->format('Y-m-d H:i'),
        expandSeries($berlin, ...[...$window, 'UTC']),
    );
    $newYorkUtc = array_map(
        fn (Occurrence $o) => $o->startsAt->format('Y-m-d H:i'),
        expandSeries($newYork, ...[...$window, 'UTC']),
    );

    expect($berlinUtc)->not->toBe($newYorkUtc)
        ->and($berlinUtc[0])->toBe('2026-03-16 08:00')  // CET, UTC+1
        ->and($newYorkUtc[0])->toBe('2026-03-16 13:00'); // EDT, UTC-4
});

test('expanding a recurring event without a timezone is refused', function () {
    // Silently defaulting to UTC here is the single most common way this bug
    // ships: everything looks correct until a DST boundary, months later.
    $startsAt = CarbonImmutable::parse('2026-03-04 09:00', 'UTC');

    $master = new RedactedEvent(
        id: 1,
        calendarId: 1,
        groupId: null,
        title: 'Standup',
        description: null,
        location: null,
        startsAt: $startsAt,
        endsAt: $startsAt->addHour(),
        allDay: false,
        visibility: EventVisibility::Public,
        isRedacted: false,
        canEdit: true,
        createdBy: 1,
        calendarName: 'Team',
        calendarColor: null,
        groupName: null,
        recurrenceRule: 'FREQ=WEEKLY',
        recurrenceTimezone: null,
    );

    expect(fn () => expandSeries($master, '2026-03-01 00:00', '2026-03-26 00:00', 'UTC'))
        ->toThrow(InvalidArgumentException::class);
});
