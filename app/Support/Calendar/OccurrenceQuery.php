<?php

namespace App\Support\Calendar;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * How controllers read events.
 *
 * Nothing outside this class queries the events table for display. Going
 * through here is what guarantees two things at once: every row is redacted
 * for its viewer before it escapes, and every query is bounded by a date
 * range instead of loading a calendar's entire history.
 *
 * Recurrence slots in behind this interface later - the candidate query grows
 * a rule predicate and an expander turns each master into many occurrences -
 * without any caller changing, which is the point of returning occurrences
 * rather than events.
 */
final class OccurrenceQuery
{
    public function __construct(private readonly EventRedactor $redactor) {}

    /**
     * @param  Collection<int, int>|array<int, int>  $calendarIds
     * @return Collection<int, Occurrence>
     */
    public function forCalendars(
        Collection|array $calendarIds,
        CarbonInterface $from,
        CarbonInterface $to,
        User $viewer,
    ): Collection {
        $ids = collect($calendarIds)->all();

        if ($ids === []) {
            return collect();
        }

        $events = Event::query()
            ->whereIn('calendar_id', $ids)
            // Overlap, not containment: an event that started before the
            // window and is still running belongs in it. Filtering on
            // starts_at alone silently drops those.
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->with('calendar.group')
            ->orderBy('starts_at')
            ->get();

        return $this->toOccurrences($events, $viewer);
    }

    /**
     * @return Collection<int, Occurrence>
     */
    public function forCalendar(
        Calendar $calendar,
        CarbonInterface $from,
        CarbonInterface $to,
        User $viewer,
    ): Collection {
        return $this->forCalendars([$calendar->id], $from, $to, $viewer);
    }

    /**
     * Everything this viewer can see, across every group and their own
     * personal calendar.
     *
     * @return Collection<int, Occurrence>
     */
    public function forUser(User $viewer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->forCalendars($viewer->accessibleCalendarIds(), $from, $to, $viewer);
    }

    /**
     * A single event as one occurrence, or null if the viewer cannot see it.
     *
     * $recurrenceId is accepted now and unused until recurrence lands, so the
     * callers that will need to name an instance do not have to change shape
     * twice.
     */
    public function forEvent(Event $event, User $viewer, ?CarbonInterface $recurrenceId = null): ?Occurrence
    {
        if (! $viewer->can('view', $event)) {
            return null;
        }

        $redacted = $this->redactor->redact($event, $viewer);

        return new Occurrence(
            event: $redacted,
            startsAt: $redacted->startsAt,
            endsAt: $redacted->endsAt,
            recurrenceId: $recurrenceId === null ? null : CarbonImmutable::parse($recurrenceId)->utc(),
        );
    }

    /**
     * Redacted events with their recurrence left intact, for consumers that
     * expand it themselves.
     *
     * The ICS feed wants this: emitting one VEVENT carrying an RRULE keeps an
     * open-ended weekly series to a single entry instead of hundreds.
     *
     * @return Collection<int, RedactedEvent>
     */
    public function mastersFor(User $viewer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $ids = $viewer->accessibleCalendarIds()->all();

        if ($ids === []) {
            return collect();
        }

        $events = Event::query()
            ->whereIn('calendar_id', $ids)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->with('calendar.group')
            ->orderBy('starts_at')
            ->get();

        return $this->redactor->redactMany($events, $viewer);
    }

    /**
     * @param  EloquentCollection<int, Event>  $events
     * @return Collection<int, Occurrence>
     */
    private function toOccurrences(EloquentCollection $events, User $viewer): Collection
    {
        return $this->redactor
            ->redactMany($events, $viewer)
            ->map(fn (RedactedEvent $event) => new Occurrence(
                event: $event,
                startsAt: $event->startsAt,
                endsAt: $event->endsAt,
            ))
            ->values();
    }
}
