<?php

namespace App\Support\Calendar;

use App\Enums\EventVisibility;
use Carbon\CarbonImmutable;

/**
 * An event as one particular viewer is allowed to see it.
 *
 * This type is the reason redaction cannot be forgotten. Only EventRedactor
 * constructs it, and everything that serialises an event - Inertia props, the
 * ICS feed, the Google pusher - consumes this rather than the Event model, so
 * a field the viewer may not see is not merely hidden, it is absent.
 *
 * Instants are UTC. Rendering in the viewer's timezone happens at the edge.
 */
final readonly class RedactedEvent
{
    public function __construct(
        public int $id,
        public int $calendarId,
        public ?int $groupId,
        public string $title,
        public ?string $description,
        public ?string $location,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public bool $allDay,
        public EventVisibility $visibility,
        /** True when fields were withheld. Nothing leaves the system with this set. */
        public bool $isRedacted,
        public bool $canEdit,
        public ?int $createdBy,
        public string $calendarName,
        public ?string $calendarColor,
        public ?string $groupName,
        /**
         * Recurrence travels through redaction intact.
         *
         * A rule says when something repeats, never what it is, so there is
         * nothing here to withhold - and both consumers need it unredacted:
         * the expander has to produce the right instants for a series whose
         * title the viewer may not read, and the ICS feed has to emit one
         * VEVENT carrying the RRULE instead of hundreds of expanded copies.
         */
        public ?string $recurrenceRule = null,
        public ?string $recurrenceTimezone = null,
        /** @var list<string> UTC ISO-8601 instants excluded from the series. */
        public array $recurrenceExdates = [],
        /** @var list<string> UTC ISO-8601 instants added to the series. */
        public array $recurrenceRdates = [],
        public ?int $recurrenceParentId = null,
        /** Set on an override row: the original start it stands in for. */
        public ?CarbonImmutable $recurrenceInstanceId = null,
    ) {}

    /**
     * Whether this row is the master of a series, rather than a one-off event
     * or an override standing in for a single instance.
     */
    public function isRecurring(): bool
    {
        return $this->recurrenceRule !== null && $this->recurrenceRule !== '';
    }

    /**
     * How long one instance of this event lasts, in seconds.
     *
     * Every occurrence in a series inherits it: the rule generates starts and
     * the end is always the same distance away. Per-occurrence ends are the
     * alternative, and they go stale the moment the master's duration changes.
     */
    public function durationSeconds(): int
    {
        return $this->endsAt->getTimestamp() - $this->startsAt->getTimestamp();
    }

    /**
     * A stable iCalendar UID.
     *
     * Must not change once a subscriber has seen it, or their calendar app
     * treats the next sync as a different event rather than an update - so it
     * is derived from the row id and the installation host, nothing mutable.
     */
    public function uid(string $host): string
    {
        return sprintf('event-%d@%s', $this->id, $host);
    }
}
