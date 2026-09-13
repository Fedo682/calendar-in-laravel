<?php

namespace App\Enums;

/**
 * How much of an event a viewer who is not its owner may see.
 *
 * Redaction itself happens in EventRedactor - this enum only describes
 * intent and how that intent is expressed at the ICS and Google boundaries.
 */
enum EventVisibility: string
{
    /** Everyone who can see the calendar sees the full event. */
    case Public = 'public';

    /** The owner sees everything; everyone else sees an opaque "Busy" block. */
    case Private = 'private';

    /** Nobody sees details, including the owner's own shared views. */
    case Busy = 'busy';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
            self::Busy => 'Show as busy',
        };
    }

    /**
     * Whether a viewer who is not the owner is allowed to see title,
     * description and location.
     */
    public function revealsDetailsToOthers(): bool
    {
        return $this === self::Public;
    }

    /**
     * The iCalendar CLASS property.
     *
     * Advisory only - most clients ignore it, so it is a courtesy signal
     * rather than a control. Actual redaction must already have happened
     * before an event reaches the ICS builder.
     */
    public function icsClass(): string
    {
        return match ($this) {
            self::Public => 'PUBLIC',
            self::Private => 'PRIVATE',
            self::Busy => 'CONFIDENTIAL',
        };
    }

    /**
     * The Google Calendar API "visibility" field.
     */
    public function googleVisibility(): string
    {
        return match ($this) {
            self::Public => 'public',
            self::Private, self::Busy => 'private',
        };
    }

    /** The title shown in place of the real one when redacted. */
    public const REDACTED_TITLE = 'Busy';
}
