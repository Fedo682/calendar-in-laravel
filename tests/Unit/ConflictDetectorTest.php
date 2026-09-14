<?php

use App\Enums\EventVisibility;
use App\Support\Calendar\ConflictDetector;
use App\Support\Calendar\Occurrence;
use App\Support\Calendar\RedactedEvent;
use Carbon\CarbonImmutable;

/**
 * No database here - the detector works on value objects, so it can be
 * exercised directly.
 */
function occurrenceAt(int $id, string $start, string $end): Occurrence
{
    $startsAt = CarbonImmutable::parse($start);
    $endsAt = CarbonImmutable::parse($end);

    return new Occurrence(
        event: new RedactedEvent(
            id: $id,
            calendarId: 1,
            groupId: 1,
            title: "Event {$id}",
            description: null,
            location: null,
            startsAt: $startsAt,
            endsAt: $endsAt,
            allDay: false,
            visibility: EventVisibility::Public,
            isRedacted: false,
            canEdit: true,
            createdBy: 1,
            calendarName: 'Team',
            calendarColor: null,
            groupName: 'Acme',
        ),
        startsAt: $startsAt,
        endsAt: $endsAt,
    );
}

/**
 * The obvious quadratic implementation, kept only as an oracle for the
 * sweep line below.
 *
 * @param  list<Occurrence>  $occurrences
 * @return array<string, int>
 */
function naiveConflictCounts(array $occurrences): array
{
    $counts = [];

    foreach ($occurrences as $a) {
        foreach ($occurrences as $b) {
            if ($a->key() === $b->key()) {
                continue;
            }

            if ($a->overlaps($b)) {
                $counts[$a->key()] = ($counts[$a->key()] ?? 0) + 1;
            }
        }
    }

    return $counts;
}

test('non overlapping events produce no conflicts', function () {
    $conflicts = (new ConflictDetector)->detect(collect([
        occurrenceAt(1, '2026-03-01 09:00', '2026-03-01 10:00'),
        occurrenceAt(2, '2026-03-01 11:00', '2026-03-01 12:00'),
    ]));

    expect($conflicts)->toBe([]);
});

test('touching events do not conflict', function () {
    // Half-open intervals: finishing exactly as the next one starts is not a
    // clash, or every back-to-back meeting would be flagged.
    $conflicts = (new ConflictDetector)->detect(collect([
        occurrenceAt(1, '2026-03-01 09:00', '2026-03-01 10:00'),
        occurrenceAt(2, '2026-03-01 10:00', '2026-03-01 11:00'),
    ]));

    expect($conflicts)->toBe([]);
});

test('an overlap is reported symmetrically', function () {
    $conflicts = (new ConflictDetector)->detect(collect([
        occurrenceAt(1, '2026-03-01 09:00', '2026-03-01 10:30'),
        occurrenceAt(2, '2026-03-01 10:00', '2026-03-01 11:00'),
    ]));

    expect($conflicts)->toHaveKeys(['1', '2'])
        ->and($conflicts['1'][0]->event->id)->toBe(2)
        ->and($conflicts['2'][0]->event->id)->toBe(1);
});

test('an event wholly containing another conflicts with it', function () {
    $conflicts = (new ConflictDetector)->detect(collect([
        occurrenceAt(1, '2026-03-01 09:00', '2026-03-01 17:00'),
        occurrenceAt(2, '2026-03-01 11:00', '2026-03-01 12:00'),
    ]));

    expect($conflicts['1'])->toHaveCount(1)
        ->and($conflicts['2'])->toHaveCount(1);
});

test('a long running event conflicts with every event inside it', function () {
    $conflicts = (new ConflictDetector)->detect(collect([
        occurrenceAt(1, '2026-03-01 08:00', '2026-03-01 18:00'),
        occurrenceAt(2, '2026-03-01 09:00', '2026-03-01 10:00'),
        occurrenceAt(3, '2026-03-01 11:00', '2026-03-01 12:00'),
        occurrenceAt(4, '2026-03-01 13:00', '2026-03-01 14:00'),
    ]));

    expect($conflicts['1'])->toHaveCount(3);
});

test('an empty set produces no conflicts', function () {
    expect((new ConflictDetector)->detect(collect()))->toBe([]);
});

test('the sweep line agrees with the naive implementation on random input', function () {
    // The detector replaced a nested loop. This asserts the replacement is
    // equivalent rather than merely faster.
    mt_srand(20260914);

    $occurrences = [];

    for ($i = 1; $i <= 200; $i++) {
        $startMinutes = mt_rand(0, 60 * 24 * 14);
        $durationMinutes = mt_rand(15, 480);

        $start = CarbonImmutable::parse('2026-03-01 00:00')->addMinutes($startMinutes);

        $occurrences[] = occurrenceAt(
            $i,
            $start->toDateTimeString(),
            $start->addMinutes($durationMinutes)->toDateTimeString(),
        );
    }

    $sweep = (new ConflictDetector)->detect(collect($occurrences));
    $naive = naiveConflictCounts($occurrences);

    $sweepCounts = array_map(fn (array $c) => count($c), $sweep);

    ksort($sweepCounts);
    ksort($naive);

    expect($sweepCounts)->toBe($naive)
        // Guard against the test passing because nothing overlapped at all.
        ->and(count($naive))->toBeGreaterThan(50);
});

test('conflictsFor excludes the subject itself', function () {
    $subject = occurrenceAt(1, '2026-03-01 09:00', '2026-03-01 10:30');
    $other = occurrenceAt(2, '2026-03-01 10:00', '2026-03-01 11:00');
    $apart = occurrenceAt(3, '2026-03-01 15:00', '2026-03-01 16:00');

    $conflicts = (new ConflictDetector)->conflictsFor(
        $subject,
        collect([$subject, $other, $apart]),
    );

    expect($conflicts)->toHaveCount(1)
        ->and($conflicts->first()->event->id)->toBe(2);
});
