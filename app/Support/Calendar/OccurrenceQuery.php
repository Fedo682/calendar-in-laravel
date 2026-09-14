<?php

namespace App\Support\Calendar;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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
 * Recurrence lives behind this interface, which is why no consumer changed
 * when it landed: the candidate predicate grew a rule branch and
 * RecurrenceExpander turns each master into many occurrences, but callers
 * still receive occurrences and never learn whether one row or one rule
 * produced them.
 *
 * Two queries, whatever the event count. The first fetches candidate masters
 * using an indexed predicate; the second fetches the overrides belonging to
 * them. Expansion then happens in PHP over a bounded window. The alternative
 * - asking the database to generate instances - has no index that can serve
 * it, so it degrades into a scan of every recurring row.
 */
final class OccurrenceQuery
{
    public function __construct(
        private readonly EventRedactor $redactor,
        private readonly RecurrenceExpander $expander,
    ) {}

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

        $from = CarbonImmutable::parse($from)->utc();
        $to = CarbonImmutable::parse($to)->utc();

        // Masters only. An override is not a candidate in its own right - it
        // is reached through the series it belongs to, so that an edit which
        // moved an instance outside the window still substitutes correctly
        // for the instance inside it.
        $masters = Event::query()
            ->whereIn('calendar_id', $ids)
            ->whereNull('recurrence_parent_id')
            ->where(fn (Builder $query) => $this->touchingWindow($query, $from, $to))
            ->with('calendar.group')
            ->orderBy('starts_at')
            ->get();

        if ($masters->isEmpty()) {
            return collect();
        }

        return $this->expandAll($masters, $this->overridesFor($masters), $from, $to, $viewer);
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
     * $recurrenceId names which instance is meant. It matters because "this
     * event" stops being a single thing the moment the event is a series: a
     * caller that means next Tuesday's standup has to be able to say so, and
     * naming an instant the series does not produce has to come back empty
     * rather than silently resolving to the master.
     *
     * Passing null returns the row's own times, which is exactly what the
     * non-recurring callers already expected.
     */
    public function forEvent(Event $event, User $viewer, ?CarbonInterface $recurrenceId = null): ?Occurrence
    {
        if (! $viewer->can('view', $event)) {
            return null;
        }

        $redacted = $this->redactor->redact($event, $viewer);

        if ($recurrenceId === null || ! $redacted->isRecurring()) {
            return new Occurrence(
                event: $redacted,
                startsAt: $redacted->startsAt,
                endsAt: $redacted->endsAt,
                recurrenceId: $recurrenceId === null ? null : CarbonImmutable::parse($recurrenceId)->utc(),
            );
        }

        $overrides = $this->overridesFor(EloquentCollection::make([$event]));

        return $this->expander->occurrenceAt(
            $redacted,
            CarbonImmutable::parse($recurrenceId)->utc(),
            $this->redactOverrides($overrides[$event->id] ?? [], $viewer),
        );
    }

    /**
     * Redacted events with their recurrence left intact, for consumers that
     * expand it themselves.
     *
     * The ICS feed wants this: emitting one VEVENT carrying an RRULE keeps an
     * open-ended weekly series to a single entry instead of hundreds.
     *
     * Unlike the display path this does *not* exclude override rows. A feed
     * has to emit them too - as their own VEVENTs carrying RECURRENCE-ID - or
     * a subscriber's copy of the series would be missing every "this event
     * only" change. They satisfy the non-recurring branch of the predicate on
     * their own terms, since an override carries real start and end times and
     * no rule of its own.
     *
     * @return Collection<int, RedactedEvent>
     */
    public function mastersFor(User $viewer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $ids = $viewer->accessibleCalendarIds()->all();

        if ($ids === []) {
            return collect();
        }

        $from = CarbonImmutable::parse($from)->utc();
        $to = CarbonImmutable::parse($to)->utc();

        $events = Event::query()
            ->whereIn('calendar_id', $ids)
            ->where(fn (Builder $query) => $this->touchingWindow($query, $from, $to))
            ->with('calendar.group')
            ->orderBy('starts_at')
            ->get();

        return $this->redactor->redactMany($events, $viewer);
    }

    /**
     * Rows whose lifetime touches [$from, $to).
     *
     * Two branches, because a series and a one-off are bounded by different
     * columns:
     *
     *  - a one-off overlaps the window when it starts before the window ends
     *    and ends after the window begins. Overlap, not containment: an event
     *    that started before the window and is still running belongs in it,
     *    and filtering on starts_at alone silently drops those.
     *
     *  - a series is a candidate when it starts before the window ends and
     *    has not already finished before the window begins, where "finished"
     *    reads the denormalised recurrence_until. NULL there means infinite,
     *    so such a series can never be excluded on that ground.
     *
     * Both branches lead with starts_at, so the existing
     * (calendar_id, starts_at) index serves the first and the new
     * (calendar_id, recurrence_until) index serves the second. Expansion then
     * does the exact filtering, which is why this only has to avoid false
     * negatives - a candidate that turns out to contribute nothing is merely
     * wasted work, a missing candidate is a missing event.
     *
     * @param  Builder<Event>  $query
     */
    private function touchingWindow(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query
            ->where(function (Builder $plain) use ($from, $to) {
                $plain->whereNull('recurrence_rule')
                    ->where('starts_at', '<', $to)
                    ->where('ends_at', '>', $from);
            })
            ->orWhere(function (Builder $series) use ($from, $to) {
                $series->whereNotNull('recurrence_rule')
                    ->where('starts_at', '<', $to)
                    ->where(function (Builder $end) use ($from) {
                        $end->whereNull('recurrence_until')
                            ->orWhere('recurrence_until', '>', $from);
                    });
            });
    }

    /**
     * The second of the two queries: every override belonging to the masters
     * just fetched, grouped by master and keyed by RECURRENCE-ID timestamp.
     *
     * Unbounded by date on purpose. An override exists precisely because
     * somebody moved an instance, and it may well have been moved outside the
     * window that produced its parent - excluding it by its own start would
     * then resurrect the instance it was meant to replace.
     *
     * @param  EloquentCollection<int, Event>  $masters
     * @return array<int, array<int, Event>>
     */
    private function overridesFor(EloquentCollection $masters): array
    {
        $masterIds = $masters->modelKeys();

        if ($masterIds === []) {
            return [];
        }

        $rows = Event::query()
            ->whereIn('recurrence_parent_id', $masterIds)
            ->whereNotNull('recurrence_id')
            ->with('calendar.group')
            ->get();

        /** @var array<int, array<int, Event>> $byMaster */
        $byMaster = [];

        foreach ($rows as $override) {
            $parentId = (int) $override->recurrence_parent_id;
            $instant = CarbonImmutable::parse($override->recurrence_id)->utc()->getTimestamp();

            $byMaster[$parentId][$instant] = $override;
        }

        return $byMaster;
    }

    /**
     * @param  EloquentCollection<int, Event>  $masters
     * @param  array<int, array<int, Event>>  $overrides
     * @return Collection<int, Occurrence>
     */
    private function expandAll(
        EloquentCollection $masters,
        array $overrides,
        CarbonImmutable $from,
        CarbonImmutable $to,
        User $viewer,
    ): Collection {
        /** @var list<Occurrence> $occurrences */
        $occurrences = [];

        foreach ($masters as $master) {
            $ownOverrides = $overrides[$master->id] ?? [];

            $occurrences = [...$occurrences, ...$this->expander->expand(
                $this->redactor->redact($master, $viewer),
                $from,
                $to,
                $this->redactOverrides($ownOverrides, $viewer),
            )];
        }

        // Consumers were handed events in starts_at order before recurrence
        // existed and several still assume it - the dashboard's "upcoming"
        // list most visibly. Expansion produces one sorted run per master, so
        // the runs have to be merged back into a single order.
        usort(
            $occurrences,
            fn (Occurrence $a, Occurrence $b) => $a->startsAt->getTimestamp() <=> $b->startsAt->getTimestamp(),
        );

        return collect($occurrences);
    }

    /**
     * Redact a master's overrides, preserving the RECURRENCE-ID keying.
     *
     * Overrides go through EventRedactor like everything else: an override
     * can carry its own title, and a private one must reach the expander
     * already blanked rather than be trusted to be blanked afterwards.
     *
     * @param  array<int, Event>  $overrides
     * @return Collection<int, RedactedEvent>
     */
    private function redactOverrides(array $overrides, User $viewer): Collection
    {
        return collect($overrides)->map(fn (Event $event) => $this->redactor->redact($event, $viewer));
    }
}
