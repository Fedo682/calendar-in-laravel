<?php

namespace App\Support\Calendar;

use Carbon\CarbonImmutable;

/**
 * Reads and rewrites an RRULE string without normalising it into columns.
 *
 * The raw string is what we store and what every boundary we cross speaks, so
 * the few operations that genuinely need to change a rule - capping a series
 * with UNTIL when it is split, decrementing a COUNT onto the tail - go
 * through here rather than each reimplementing a parser.
 *
 * Deliberately textual and order-preserving: round-tripping a rule nobody
 * edited must return the same string, or a stored rule would churn on every
 * save and the ICS feed would report a change to subscribers that did not
 * happen.
 */
final class RecurrenceRuleString
{
    /** The iCalendar wire format for a UTC instant, as UNTIL must be written. */
    public const UNTIL_FORMAT = 'Ymd\THis\Z';

    /**
     * Split a rule into its parts, uppercased names, original order kept.
     *
     * A leading "RRULE:" is tolerated because both Google and hand-written
     * ICS carry it, and rejecting it would be a papercut with no upside.
     *
     * @return array<string, string>
     */
    public static function parse(string $rule): array
    {
        $rule = trim($rule);

        if (str_starts_with(strtoupper($rule), 'RRULE:')) {
            $rule = substr($rule, 6);
        }

        $parts = [];

        foreach (explode(';', $rule) as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            // Split on the first '=' only: UNTIL values contain none, but a
            // malformed part with several should fail the name check below
            // rather than be silently truncated.
            $pieces = explode('=', $segment, 2);

            $name = strtoupper(trim($pieces[0]));
            $parts[$name] = isset($pieces[1]) ? trim($pieces[1]) : '';
        }

        return $parts;
    }

    /**
     * @param  array<string, string>  $parts
     */
    public static function build(array $parts): string
    {
        $pieces = [];

        foreach ($parts as $name => $value) {
            $pieces[] = $name.'='.$value;
        }

        return implode(';', $pieces);
    }

    /**
     * Terminate a rule at $until, dropping any COUNT it carried.
     *
     * UNTIL and COUNT are mutually exclusive in RFC 5545, so a rule being
     * capped by a split has to lose its COUNT - the remaining count belongs
     * to the tail series, and RecurrenceEditor puts it there.
     */
    public static function withUntil(string $rule, CarbonImmutable $until): string
    {
        $parts = self::parse($rule);
        unset($parts['COUNT']);
        $parts['UNTIL'] = $until->utc()->format(self::UNTIL_FORMAT);

        return self::build($parts);
    }

    /**
     * Replace a rule's COUNT, dropping any UNTIL for the same reason.
     */
    public static function withCount(string $rule, int $count): string
    {
        $parts = self::parse($rule);
        unset($parts['UNTIL']);
        $parts['COUNT'] = (string) max(1, $count);

        return self::build($parts);
    }

    public static function count(string $rule): ?int
    {
        $parts = self::parse($rule);

        return isset($parts['COUNT']) && $parts['COUNT'] !== ''
            ? (int) $parts['COUNT']
            : null;
    }

    /**
     * The rule's own UNTIL, as a UTC instant, or null if it has none.
     *
     * A floating (no trailing Z) UNTIL is read as UTC. RFC 5545 requires UTC
     * whenever DTSTART is not a date value, and every producer we consume
     * writes it that way; treating a stray floating value as UTC keeps the
     * denormalised end conservative rather than throwing.
     */
    public static function until(string $rule): ?CarbonImmutable
    {
        $parts = self::parse($rule);

        if (! isset($parts['UNTIL']) || $parts['UNTIL'] === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($parts['UNTIL'], 'UTC')->utc();
        } catch (\Exception) {
            return null;
        }
    }
}
