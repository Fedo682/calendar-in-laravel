<?php

namespace App\Rules;

use App\Support\Calendar\RecurrenceRuleString;
use Closure;
use DateTime;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use RRule\RRule;

/**
 * Accepts an RRULE string, but only a deliberately small part of RFC 5545.
 *
 * Two separate jobs.
 *
 * The first is keeping the stored corpus inside what every boundary we cross
 * can round-trip. iCalendar, Google's recurrence[] array and php-rrule all
 * speak RRULE, but they do not agree on the edges of it, so the allowlist is
 * the intersection we are prepared to guarantee rather than everything one
 * parser happens to accept.
 *
 * The second is refusing expansion bombs at the door. FREQ=SECONDLY on an
 * open-ended series is 31 million occurrences a year; php-rrule will happily
 * parse it. The per-window cap in RecurrenceExpander bounds the damage, but
 * a rule that can only ever hit that cap is not a calendar entry, so it is
 * rejected here where the user still gets a sentence explaining why rather
 * than a silently truncated series.
 *
 * Both halves are needed. The allowlist alone would admit SECONDLY, since
 * FREQ is an allowed *part*; the frequency check alone would admit BYYEARDAY
 * and the other parts we cannot promise to preserve.
 */
class ValidRRule implements ValidationRule
{
    /**
     * The parts a stored rule may use.
     *
     * DTSTART is absent on purpose: the series' start is the event's own
     * starts_at column plus recurrence_timezone, and a DTSTART inside the
     * string would be a second, competing - and timezone-less - answer to
     * the same question.
     *
     * EXDATE and RDATE are absent for the same reason: they are their own
     * columns, so that adding one is a JSON append rather than a string
     * rewrite that has to preserve everything else in the rule.
     */
    public const ALLOWED_PARTS = [
        'FREQ',
        'INTERVAL',
        'BYDAY',
        'BYMONTHDAY',
        'BYMONTH',
        'BYSETPOS',
        'COUNT',
        'UNTIL',
        'WKST',
    ];

    /**
     * Frequencies we will not store.
     *
     * SECONDLY and MINUTELY are expansion bombs: open-ended, the first is 31
     * million occurrences a year, and a rule that can only ever hit the
     * expander's per-window cap is not a calendar entry.
     *
     * HOURLY is here for a different and more interesting reason. The two
     * RRULE engines in this codebase disagree about it across a DST
     * transition, which is exactly the guarantee the allowlist exists to
     * make. Expanding FREQ=HOURLY;COUNT=60 from 2026-03-07 20:00
     * America/New_York over the spring-forward:
     *
     *   php-rrule   ... 01:00, 03:00, 03:00, 04:00 ...   (emits 03:00 twice)
     *   sabre       ... 01:00, 03:00, 04:00, 05:00 ...
     *
     * php-rrule steps wall-clock hours and collides on the 02:00 that does
     * not exist that day; sabre steps absolute hours. The series stay an hour
     * apart for the rest of their length. Since php-rrule expands for display
     * and sabre writes the ICS feed subscribers actually see, storing an
     * HOURLY rule would mean the app and the user's phone disagreeing about
     * when the event is.
     *
     * No real calendar entry repeats hourly, so this costs nothing.
     */
    public const FORBIDDEN_FREQUENCIES = ['SECONDLY', 'MINUTELY', 'HOURLY'];

    /** Matches the recurrence_rule column. */
    private const MAX_LENGTH = 512;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('The :attribute must be a recurrence rule.');

            return;
        }

        if (strlen($value) > self::MAX_LENGTH) {
            $fail('The :attribute may not be longer than '.self::MAX_LENGTH.' characters.');

            return;
        }

        $parts = RecurrenceRuleString::parse($value);

        if ($parts === []) {
            $fail('The :attribute must be a recurrence rule.');

            return;
        }

        $unknown = array_diff(array_keys($parts), self::ALLOWED_PARTS);

        if ($unknown !== []) {
            $fail('The :attribute uses unsupported recurrence parts: '.implode(', ', $unknown).'.');

            return;
        }

        if (! isset($parts['FREQ']) || $parts['FREQ'] === '') {
            $fail('The :attribute must specify a FREQ.');

            return;
        }

        if (in_array(strtoupper($parts['FREQ']), self::FORBIDDEN_FREQUENCIES, true)) {
            $fail('The :attribute may not repeat more often than daily.');

            return;
        }

        // The allowlist says which parts may appear; only a real parse says
        // whether their values make sense together - BYSETPOS without a BY*
        // rule to select from, BYDAY=XX, COUNT and UNTIL at once.
        try {
            new RRule($value, new DateTime('2000-01-01 00:00:00', new DateTimeZone('UTC')));
        } catch (\Throwable $e) {
            $fail('The :attribute is not a valid recurrence rule: '.$e->getMessage());
        }
    }
}
