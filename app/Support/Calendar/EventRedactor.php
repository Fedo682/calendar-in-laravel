<?php

namespace App\Support\Calendar;

use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The single place an Event becomes something that can be serialised.
 *
 * Deliberately not a query scope and not a policy:
 *
 *  - A scope can be forgotten. Event::all() sidesteps a local scope entirely,
 *    and a global scope cannot blank individual columns anyway.
 *  - A policy answers yes or no. "Show a stripped-down version" is neither.
 *
 * Returning a separate type is what makes a leak structurally impossible
 * rather than merely unlikely: the object holding the private title never
 * reaches a serialiser, so forgetting to redact is not an available mistake.
 *
 * Callers go through OccurrenceQuery rather than calling this directly.
 */
final class EventRedactor
{
    /**
     * Whether this viewer is entitled to the real title, description and
     * location.
     *
     * Super Admin is deliberately NOT a bypass here. It short-circuits
     * authorization through Gate::before, but "can administer the platform"
     * is not "may read the contents of someone's private appointments".
     */
    public function canSeeDetails(Event $event, User $viewer): bool
    {
        if ($event->visibility === EventVisibility::Public) {
            return true;
        }

        // 'busy' hides details from everyone, including its own author - that
        // is what distinguishes it from 'private'.
        if ($event->visibility === EventVisibility::Busy) {
            return false;
        }

        return $this->isOwnEvent($event, $viewer);
    }

    public function redact(Event $event, User $viewer): RedactedEvent
    {
        $calendar = $event->calendar;
        $visible = $this->canSeeDetails($event, $viewer);

        return new RedactedEvent(
            id: $event->id,
            calendarId: $event->calendar_id,
            groupId: $calendar->group_id,
            title: $visible ? $event->title : EventVisibility::REDACTED_TITLE,
            description: $visible ? $event->description : null,
            location: $visible ? $event->location : null,
            startsAt: CarbonImmutable::parse($event->starts_at)->utc(),
            endsAt: CarbonImmutable::parse($event->ends_at)->utc(),
            allDay: $event->all_day,
            visibility: $event->visibility,
            isRedacted: ! $visible,
            canEdit: $viewer->can('update', $event),
            createdBy: $event->created_by,
            calendarName: $calendar->name,
            calendarColor: $calendar->color,
            groupName: $calendar->group?->name,
            // Recurrence is carried through unredacted on purpose. A rule
            // describes cadence, not content: "every Monday at 09:00" is
            // already implied by the busy blocks a viewer can see, so
            // withholding it would hide nothing while breaking expansion for
            // exactly the events that most need it.
            recurrenceRule: $event->recurrence_rule,
            recurrenceTimezone: $event->recurrence_timezone,
            recurrenceExdates: $event->recurrence_exdates ?? [],
            recurrenceRdates: $event->recurrence_rdates ?? [],
            recurrenceParentId: $event->recurrence_parent_id,
            recurrenceInstanceId: $event->recurrence_id === null
                ? null
                : CarbonImmutable::parse($event->recurrence_id)->utc(),
            ownerName: ($calendar->isPersonal() && ! $visible) ? $calendar->owner?->name : null,
        );
    }

    /**
     * Redact a batch.
     *
     * Eager-loads the calendar and its group once for the whole set, so the
     * per-event group role lookups hit Group's memo rather than the database.
     *
     * @param  EloquentCollection<int, Event>  $events
     * @return Collection<int, RedactedEvent>
     */
    public function redactMany(EloquentCollection $events, User $viewer): Collection
    {
        $events->loadMissing('calendar.group', 'calendar.owner');

        // One Group instance per group id, so roleFor()'s per-instance memo
        // is actually shared across every event in that group.
        $groups = $events
            ->map(fn (Event $event) => $event->calendar->group)
            ->filter()
            ->keyBy('id');

        $events->each(function (Event $event) use ($groups): void {
            $groupId = $event->calendar->group_id;

            if ($groupId !== null && $groups->has($groupId)) {
                $event->calendar->setRelation('group', $groups->get($groupId));
            }
        });

        return $events->map(fn (Event $event) => $this->redact($event, $viewer))->values();
    }

    /**
     * The viewer authored the event, or it sits on their own calendar.
     *
     * The second case matters because an admin can create an event on a
     * member's behalf; ownership of the calendar still confers the right to
     * read what is on it.
     */
    private function isOwnEvent(Event $event, User $viewer): bool
    {
        return $event->created_by === $viewer->id
            || $event->calendar->isOwnedBy($viewer);
    }
}
