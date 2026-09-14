<?php

namespace App\Support\Calendar;

/**
 * Which part of a series an edit or a delete applies to.
 *
 * Every calendar application asks this same three-way question, and the
 * wording is near-universal, so the values are the ones a user already
 * recognises rather than anything invented here.
 *
 * An enum rather than three loose strings because the choice selects between
 * three genuinely different writes - append an EXDATE, split the series, or
 * update the master - and a typo'd string would silently fall through to the
 * most destructive of them.
 */
enum RecurrenceScope: string
{
    /** Just the named instance. Becomes an override row, or an EXDATE. */
    case ThisOccurrence = 'this';

    /** The named instance and everything after it. Splits the series. */
    case ThisAndFollowing = 'following';

    /**
     * The whole series.
     *
     * The default, and the only meaning a non-recurring event can have, which
     * is what lets every caller that predates recurrence keep working without
     * sending a scope at all.
     */
    case AllEvents = 'all';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Whether this scope names a single point in the series, and therefore
     * needs an occurrence to have been named alongside it.
     */
    public function needsOccurrence(): bool
    {
        return $this !== self::AllEvents;
    }
}
