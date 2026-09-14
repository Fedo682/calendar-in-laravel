<?php

use App\Rules\ValidRRule;

/**
 * What the application will and will not store as a recurrence rule.
 *
 * The allowlist is narrower than RFC 5545 on purpose, so these tests are as
 * much about the rejections as the acceptances: each refusal is either a part
 * we cannot promise to round-trip through ICS and Google alike, or a rule
 * that can only ever hit the expansion cap.
 */
function rruleFailures(string $rule): array
{
    $failures = [];

    (new ValidRRule)->validate('recurrence_rule', $rule, function (string $message) use (&$failures) {
        $failures[] = $message;
    });

    return $failures;
}

test('the allowlisted parts are accepted', function (string $rule) {
    expect(rruleFailures($rule))->toBe([]);
})->with([
    'FREQ=DAILY',
    'FREQ=WEEKLY;BYDAY=MO,WE,FR',
    'FREQ=WEEKLY;INTERVAL=2;BYDAY=TU',
    'FREQ=MONTHLY;BYMONTHDAY=15',
    'FREQ=MONTHLY;BYDAY=MO;BYSETPOS=1',
    'FREQ=MONTHLY;BYDAY=FR;BYSETPOS=-1',
    'FREQ=YEARLY;BYMONTH=6;BYMONTHDAY=21',
    'FREQ=DAILY;COUNT=10',
    'FREQ=WEEKLY;UNTIL=20261231T235959Z',
    'FREQ=WEEKLY;BYDAY=MO;WKST=SU',
]);

test('a leading RRULE: prefix is tolerated', function () {
    // Both Google and hand-written ICS carry it; rejecting it would be a
    // papercut with nothing gained.
    expect(rruleFailures('RRULE:FREQ=WEEKLY;BYDAY=MO'))->toBe([]);
});

test('SECONDLY is rejected', function () {
    // An open-ended SECONDLY rule is 31 million occurrences a year. The
    // per-window cap would contain it, but silently - this refuses it where
    // the user still gets told why.
    expect(rruleFailures('FREQ=SECONDLY'))->not->toBe([]);
});

test('MINUTELY is rejected', function () {
    expect(rruleFailures('FREQ=MINUTELY;INTERVAL=1'))->not->toBe([]);
});

test('HOURLY is rejected because the two engines disagree across DST', function () {
    // Not an expansion-bomb concern - 24 a day is well inside the cap. The
    // two RRULE engines in this codebase produce different series for it
    // across a spring-forward (php-rrule steps wall-clock hours and emits the
    // hour after the gap twice; sabre steps absolute hours), and they stay an
    // hour apart afterwards. php-rrule expands for display while sabre writes
    // the ICS feed, so storing one would mean the app and a subscriber's
    // phone disagreeing about when the event is.
    expect(rruleFailures('FREQ=HOURLY;COUNT=60'))->not->toBe([]);
});

test('parts outside the allowlist are rejected', function (string $rule) {
    expect(rruleFailures($rule))->not->toBe([]);
})->with([
    'FREQ=YEARLY;BYYEARDAY=200',
    'FREQ=WEEKLY;BYWEEKNO=12',
    'FREQ=DAILY;BYHOUR=9',
    'FREQ=DAILY;BYMINUTE=30',
    'FREQ=DAILY;BYSECOND=0',
    // DTSTART belongs to the event's own columns. Accepting it here would
    // create a second, timezone-less answer to when the series begins.
    'DTSTART=20260101T090000Z;FREQ=DAILY',
    // EXDATE and RDATE are their own columns, so that adding one is a JSON
    // append rather than a string rewrite.
    'FREQ=DAILY;EXDATE=20260102T090000Z',
    'FREQ=DAILY;RDATE=20260102T090000Z',
    'FREQ=DAILY;NONSENSE=1',
]);

test('a rule with no FREQ is rejected', function () {
    expect(rruleFailures('INTERVAL=2;COUNT=3'))->not->toBe([]);
});

test('an empty or non-string rule is rejected', function () {
    expect(rruleFailures(''))->not->toBe([])
        ->and(rruleFailures('   '))->not->toBe([]);
});

test('a rule longer than the column is rejected', function () {
    // The column is 512 characters; a rule that would be truncated on write
    // has to be refused rather than silently stored as a different rule.
    expect(rruleFailures('FREQ=WEEKLY;BYDAY='.str_repeat('MO,', 300)))->not->toBe([]);
});

test('parts that the allowlist admits but that do not parse are rejected', function () {
    // The allowlist says which parts may appear; only a real parse says
    // whether their values make sense.
    expect(rruleFailures('FREQ=WEEKLY;BYDAY=XX'))->not->toBe([])
        ->and(rruleFailures('FREQ=NOTAFREQUENCY'))->not->toBe([])
        ->and(rruleFailures('FREQ=DAILY;INTERVAL=0'))->not->toBe([]);
});

test('part names are matched case-insensitively', function () {
    expect(rruleFailures('freq=weekly;byday=mo'))->toBe([]);
});
