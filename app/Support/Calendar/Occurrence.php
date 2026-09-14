<?php

namespace App\Support\Calendar;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One dated instance of an event.
 *
 * Today an event produces exactly one of these. It exists as its own type so
 * that recurrence can later produce many from a single row without any
 * consumer changing: the month grid, conflict detection and the feeds all
 * already speak in occurrences rather than in events.
 *
 * toArray() is the only shape sent to the frontend.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Occurrence implements Arrayable
{
    public function __construct(
        public RedactedEvent $event,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        /**
         * For a recurring series, the original start of the instance this
         * occurrence stands for. Null for a one-off event.
         */
        public ?CarbonImmutable $recurrenceId = null,
    ) {}

    /**
     * Identifies this occurrence uniquely.
     *
     * The event id alone is not enough once one row can yield many
     * occurrences, which is why this - not the id - is the React key.
     */
    public function key(): string
    {
        return $this->recurrenceId === null
            ? (string) $this->event->id
            : $this->event->id.':'.$this->recurrenceId->getTimestamp();
    }

    /**
     * Half-open overlap: an event ending exactly when another starts does not
     * conflict with it.
     */
    public function overlaps(self $other): bool
    {
        return $this->startsAt < $other->endsAt
            && $this->endsAt > $other->startsAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            // Both: 'id' keeps the grid components, which take a plain event,
            // working unchanged, while 'key' is what React should key on -
            // one recurring row will later yield many occurrences sharing an
            // id.
            'id' => $this->event->id,
            'event_id' => $this->event->id,
            'calendar_id' => $this->event->calendarId,
            'group_id' => $this->event->groupId,
            'title' => $this->event->title,
            'description' => $this->event->description,
            'location' => $this->event->location,
            'starts_at' => $this->startsAt->toIso8601String(),
            'ends_at' => $this->endsAt->toIso8601String(),
            'all_day' => $this->event->allDay,
            'visibility' => $this->event->visibility->value,
            'is_redacted' => $this->event->isRedacted,
            'can_edit' => $this->event->canEdit,
            'recurrence_id' => $this->recurrenceId?->toIso8601String(),
            // The rule itself, so opening an occurrence for edit can show
            // what the series does without a second request. Null on a
            // one-off, and on an override, which is a row in its own right.
            'recurrence_rule' => $this->event->recurrenceRule,
            'recurrence_timezone' => $this->event->recurrenceTimezone,
            'is_recurring' => $this->event->isRecurring(),
            'calendar_name' => $this->event->calendarName,
            'calendar_color' => $this->event->calendarColor,
            'group_name' => $this->event->groupName,
        ];
    }
}
