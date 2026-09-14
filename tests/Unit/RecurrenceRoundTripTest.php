<?php

use App\Support\Calendar\Occurrence;
use App\Support\Calendar\RecurrenceExpander;
use Carbon\CarbonImmutable;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;
use Tests\TestCase;

/**
 * Two RRULE engines now live in this codebase, and this is what stops them
 * drifting.
 *
 * php-rrule expands rules at runtime - it is the fast iterator behind the
 * month grid. sabre/vobject is what will write the ICS feed a later phase
 * publishes, and it parses and expands rules of its own. If the two disagree
 * about a rule, the calendar somebody subscribes to on their phone quietly
 * stops matching the calendar they see in the app, and nothing in either
 * library's own tests would catch it.
 *
 * So: a corpus of rules, both engines, one year, exact agreement required.
 *
 * The comparison runs through a real VCALENDAR document rather than
 * vobject's rule iterator directly, because the serialised ICS is the actual
 * boundary - this exercises the DTSTART/TZID write and read as well as the
 * expansion.
 */
uses(TestCase::class);

/**
 * Every RRULE the application is prepared to store, near enough.
 *
 * Deliberately heavy on the awkward cases rather than the obvious ones: the
 * nth-weekday selections, the intervals that do not divide the period, the
 * month-day that does not exist in every month, and a DST boundary in each
 * direction.
 *
 * @return list<array{string, string, string}> rule, dtstart, timezone
 */
function roundTripCorpus(): array
{
    $utc = 'UTC';
    $berlin = 'Europe/Berlin';
    $newYork = 'America/New_York';

    return [
        ['FREQ=DAILY', '2026-01-05 09:00', $utc],
        ['FREQ=DAILY;INTERVAL=3', '2026-01-05 09:00', $utc],
        ['FREQ=DAILY;COUNT=10', '2026-01-05 09:00', $utc],
        ['FREQ=DAILY;UNTIL=20260401T090000Z', '2026-01-05 09:00', $utc],
        ['FREQ=DAILY;INTERVAL=2;COUNT=25', '2026-02-27 23:30', $utc],

        ['FREQ=WEEKLY', '2026-01-05 09:00', $utc],
        ['FREQ=WEEKLY;BYDAY=MO,WE,FR', '2026-01-05 09:00', $utc],
        ['FREQ=WEEKLY;INTERVAL=2;BYDAY=TU', '2026-01-06 09:00', $utc],
        ['FREQ=WEEKLY;BYDAY=SA,SU', '2026-01-03 09:00', $utc],
        ['FREQ=WEEKLY;BYDAY=MO;WKST=SU', '2026-01-05 09:00', $utc],
        ['FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,TU;WKST=SU', '2026-01-05 09:00', $utc],
        ['FREQ=WEEKLY;COUNT=53', '2026-01-05 09:00', $utc],

        ['FREQ=MONTHLY', '2026-01-15 09:00', $utc],
        ['FREQ=MONTHLY;BYMONTHDAY=1', '2026-01-01 09:00', $utc],
        // The 31st does not exist in every month - both engines must skip
        // rather than roll forward.
        ['FREQ=MONTHLY;BYMONTHDAY=31', '2026-01-31 09:00', $utc],
        ['FREQ=MONTHLY;BYMONTHDAY=-1', '2026-01-31 09:00', $utc],
        ['FREQ=MONTHLY;BYDAY=MO;BYSETPOS=1', '2026-01-05 09:00', $utc],
        ['FREQ=MONTHLY;BYDAY=FR;BYSETPOS=-1', '2026-01-30 09:00', $utc],
        ['FREQ=MONTHLY;BYDAY=TU;BYSETPOS=2', '2026-01-13 09:00', $utc],
        ['FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1', '2026-01-30 09:00', $utc],
        ['FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=15', '2026-01-15 09:00', $utc],

        ['FREQ=YEARLY', '2026-03-21 09:00', $utc],
        ['FREQ=YEARLY;BYMONTH=6;BYMONTHDAY=21', '2026-06-21 09:00', $utc],
        ['FREQ=YEARLY;BYMONTH=11;BYDAY=TH;BYSETPOS=4', '2026-11-26 09:00', $utc],
        // 29 February: the next one is 2028, so a one-year window sees none.
        ['FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29', '2024-02-29 09:00', $utc],

        // Across the European spring forward and autumn fall back.
        ['FREQ=WEEKLY;BYDAY=MO', '2026-03-16 09:00', $berlin],
        ['FREQ=DAILY', '2026-10-20 09:00', $berlin],
        ['FREQ=DAILY;INTERVAL=7;COUNT=20', '2026-03-01 02:30', $berlin],

        // Across the US transitions, which fall on different dates.
        ['FREQ=WEEKLY;BYDAY=WE', '2026-03-04 09:00', $newYork],
        ['FREQ=DAILY', '2026-10-28 09:00', $newYork],
        ['FREQ=MONTHLY;BYDAY=SU;BYSETPOS=1', '2026-03-01 09:00', $newYork],
        // Anchored at the hour the US spring-forward skips. This replaced a
        // FREQ=HOURLY case: the two engines genuinely disagree on HOURLY
        // across a transition, so it is now rejected by ValidRRule rather
        // than round-tripped. Daily keeps the same DST pressure on a
        // frequency we actually store.
        ['FREQ=DAILY;COUNT=10', '2026-03-07 02:30', $newYork],
    ];
}

/**
 * What sabre/vobject makes of a rule, via a real ICS document.
 *
 * @return list<string>
 */
function vobjectStarts(string $rule, string $dtstart, string $timezone, CarbonImmutable $until): array
{
    $start = new DateTimeImmutable($dtstart, new DateTimeZone($timezone));

    $calendar = new VCalendar;
    $event = $calendar->add('VEVENT', [
        'UID' => 'round-trip',
        'SUMMARY' => 'Round trip',
        'RRULE' => $rule,
    ]);
    $event->add('DTSTART', $start);
    $event->add('DTEND', $start->modify('+1 hour'));

    // Serialise and re-parse: the feed a subscriber receives is text, so any
    // disagreement introduced by writing or reading it counts.
    $reparsed = Reader::read($calendar->serialize());

    $iterator = new EventIterator($reparsed, 'round-trip', new DateTimeZone($timezone));

    $starts = [];

    while ($iterator->valid()) {
        $current = $iterator->getDtStart();

        if ($current > $until) {
            break;
        }

        $starts[] = CarbonImmutable::instance($current)->utc()->format('Y-m-d H:i:s');

        // A COUNT/UNTIL rule simply runs out, which ends the loop.
        $iterator->next();

        // Belt and braces against a corpus entry that is denser than
        // intended; the assertion below would fail loudly rather than the
        // test hanging.
        if (count($starts) > 2000) {
            break;
        }
    }

    return $starts;
}

/**
 * @return list<string>
 */
function phpRruleStarts(string $rule, string $dtstart, string $timezone, CarbonImmutable $from, CarbonImmutable $until): array
{
    $master = recurringMaster($dtstart, $timezone, $rule);

    return array_map(
        fn (Occurrence $o) => $o->startsAt->format('Y-m-d H:i:s'),
        app(RecurrenceExpander::class)->expand($master, $from, $until, collect()),
    );
}

test('both RRULE engines agree over a one-year window', function (string $rule, string $dtstart, string $timezone) {
    // Wide enough to need the cap lifted, and to take every corpus entry
    // through at least one DST transition in its own zone.
    config()->set('calendar.recurrence.max_occurrences_per_window', 5000);
    config()->set('calendar.recurrence.max_window_days', 800);

    $from = CarbonImmutable::parse($dtstart, $timezone)->utc();
    $until = $from->addYear();

    $ours = phpRruleStarts($rule, $dtstart, $timezone, $from, $until);
    $theirs = vobjectStarts($rule, $dtstart, $timezone, $until);

    // vobject's iterator is inclusive of an instance landing exactly on the
    // bound; the expansion window is half-open everywhere in this codebase,
    // so trim that one rather than loosen the comparison.
    $theirs = array_values(array_filter(
        $theirs,
        fn (string $instant) => $instant !== $until->format('Y-m-d H:i:s'),
    ));

    expect($ours)->toBe($theirs);
})->with(roundTripCorpus());

test('the corpus is large enough to be worth calling a corpus', function () {
    // A floor, not an exact count - the point is that the round-trip check
    // covers a real spread of rules, and pinning the number turns every
    // deliberate addition or removal into an unrelated test failure.
    expect(count(roundTripCorpus()))->toBeGreaterThanOrEqual(30);
});
