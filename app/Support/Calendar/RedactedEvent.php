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
    ) {}

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
