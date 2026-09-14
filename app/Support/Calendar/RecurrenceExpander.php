<?php

namespace App\Support\Calendar;

use App\Models\Event;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Collection;
use RRule\RRule;

/**
 * Turns one stored series into the dated instances that fall in a window.
 *
 * Two rules govern everything here.
 *
 * The first is that expansion happens in the series' own timezone, never in
 * UTC. A weekly standup at 09:00 Europe/Berlin is 08:00 UTC in winter and
 * 07:00 UTC in summer; stepping seven days at a time from a UTC instant
 * silently moves every occurrence after a DST transition by an hour, and
 * because the shift only shows up twice a year it tends to reach production.
 * recurrence_timezone is what makes the correct expansion possible, so a
 * recurring event without one is a data bug and is treated as one.
 *
 * The second is that expansion is always bounded. A rule can be infinite, and
 * an infinite rule meeting an unbounded window is an expansion bomb - whether
 * by accident or on purpose. Two independent limits apply: the window itself
 * is refused above config('calendar.recurrence.max_window_days'), and each
 * master yields at most max_occurrences_per_window instances however wide the
 * window is.
 */
final class RecurrenceExpander
{
    /**
     * How far computeUntil() will iterate a COUNT-terminated rule before
     * giving up and recording "no known end".
     *
     * Giving up is safe in the direction that matters: a NULL
     * recurrence_until makes the series an unconditional candidate for every
     * window, so the SQL filter merely gets less selective. Returning a value
     * that is too *early* would be the dangerous failure, because it would
     * drop real occurrences out of the query before expansion ever saw them.
     */
    private const MAX_COUNT_ITERATIONS = 5000;

    /**
     * Every instance of $master that overlaps [$from, $to], with overrides
     * substituted in.
     *
     * The overrides collection is keyed by the UTC timestamp of the instance
     * each one replaces - iCalendar's RECURRENCE-ID - which is what lets the
     * substitution be a hash lookup per occurrence rather than a scan.
     *
     * It holds RedactedEvents rather than Event models so that this class
     * never sees an unredacted row: OccurrenceQuery redacts masters and
     * overrides together before calling in, and the chokepoint stays a
     * chokepoint.
     *
     * @param  Collection<int, RedactedEvent>  $overrides
     * @return list<Occurrence>
     */
    public function expand(
        RedactedEvent $master,
        CarbonImmutable $from,
        CarbonImmutable $to,
        Collection $overrides,
    ): array {
        $this->guardWindow($from, $to);

        if (! $master->isRecurring()) {
            // A one-off still goes through here so callers have a single
            // path. Overlap, not containment: something that began before
            // the window and is still running belongs in it.
            return $master->startsAt < $to && $master->endsAt > $from
                ? [new Occurrence($master, $master->startsAt, $master->endsAt)]
                : [];
        }

        $duration = $master->durationSeconds();
        $exdates = $this->instantSet($master->recurrenceExdates);

        /** @var list<Occurrence> $occurrences */
        $occurrences = [];

        /** @var array<int, true> $consumed */
        $consumed = [];

        $cap = $this->maxOccurrences();

        foreach ($this->starts($master, $from, $to, $duration) as $start) {
            if (count($occurrences) >= $cap) {
                break;
            }

            $timestamp = $start->getTimestamp();

            // EXDATE is checked before the override lookup: deleting one
            // instance of a series that also carries an edit for that
            // instance must delete it, not resurrect the edit.
            //
            // Marking it consumed is what makes that true. Without it the
            // override is left looking orphaned, and the orphan pass below -
            // whose whole job is to rescue edits the rule stopped generating
            // - would put the cancelled instance straight back on the
            // calendar.
            if (isset($exdates[$timestamp])) {
                $consumed[$timestamp] = true;

                continue;
            }

            if ($overrides->has($timestamp)) {
                /** @var RedactedEvent $override */
                $override = $overrides->get($timestamp);
                $consumed[$timestamp] = true;

                // The override carries its own start and end - the whole
                // point of "this event only" is that it may have moved.
                $occurrences[] = new Occurrence(
                    event: $override,
                    startsAt: $override->startsAt,
                    endsAt: $override->endsAt,
                    recurrenceId: $start,
                );

                continue;
            }

            $occurrences[] = new Occurrence(
                event: $master,
                startsAt: $start,
                endsAt: $start->addSeconds($duration),
                recurrenceId: $start,
            );
        }

        return [...$occurrences, ...$this->orphanedOverrides($overrides, $consumed, $from, $to)];
    }

    /**
     * The last instant any instance of this event can *end*, or null when the
     * series never ends.
     *
     * EventObserver denormalises this onto the row so that "does this series
     * touch the requested window" is a plain indexed comparison. Without it
     * the only way to answer that question is to load every recurring row and
     * expand it, which is the cost this whole design exists to avoid.
     *
     * Deliberately an upper bound rather than the exact last end. Overshooting
     * only means a master stays a candidate slightly longer than it needs to
     * and expansion then yields nothing for it; undershooting would drop real
     * occurrences before expansion ever ran.
     */
    public function computeUntil(Event $event): ?CarbonImmutable
    {
        if (! $event->isRecurring()) {
            return null;
        }

        $rule = (string) $event->recurrence_rule;
        $duration = CarbonImmutable::parse($event->ends_at)->getTimestamp()
            - CarbonImmutable::parse($event->starts_at)->getTimestamp();

        $end = $this->ruleEnd($event, $rule, $duration);

        // An RDATE can extend a series past where its rule stops, and an
        // RDATE on an otherwise infinite series cannot extend it further, so
        // this only applies where an end was found at all.
        if ($end !== null) {
            foreach ($this->instants($event->recurrence_rdates ?? []) as $rdate) {
                $candidate = $rdate->addSeconds($duration);

                if ($candidate > $end) {
                    $end = $candidate;
                }
            }
        }

        return $end;
    }

    /**
     * One named instance of a series, or null if the rule does not generate
     * that instant (or an EXDATE has removed it).
     *
     * This is what makes "report a conflict on event X" answerable once X is
     * a series: the caller names an instance and gets back either that
     * instance or nothing, so a fabricated timestamp 404s instead of being
     * treated as the master.
     *
     * @param  Collection<int, RedactedEvent>  $overrides  keyed by RECURRENCE-ID timestamp
     */
    public function occurrenceAt(
        RedactedEvent $master,
        CarbonImmutable $recurrenceId,
        Collection $overrides,
    ): ?Occurrence {
        if (! $master->isRecurring()) {
            return null;
        }

        $recurrenceId = $recurrenceId->utc();
        $timestamp = $recurrenceId->getTimestamp();

        $exdates = $this->instantSet($master->recurrenceExdates);

        if (isset($exdates[$timestamp])) {
            return null;
        }

        if (! $this->generates($master, $recurrenceId)) {
            return null;
        }

        if ($overrides->has($timestamp)) {
            /** @var RedactedEvent $override */
            $override = $overrides->get($timestamp);

            return new Occurrence($override, $override->startsAt, $override->endsAt, $recurrenceId);
        }

        return new Occurrence(
            event: $master,
            startsAt: $recurrenceId,
            endsAt: $recurrenceId->addSeconds($master->durationSeconds()),
            recurrenceId: $recurrenceId,
        );
    }

    /**
     * Whether $master's rule really produces an instance beginning at
     * $recurrenceId.
     *
     * RecurrenceEditor asks before acting on any single occurrence. Without
     * it, "edit this occurrence" with a timestamp that names nothing would
     * quietly create an override that can never be rendered, because nothing
     * in the series would ever match its RECURRENCE-ID.
     */
    public function generatesInstance(Event $master, CarbonImmutable $recurrenceId): bool
    {
        if (! $master->isRecurring()) {
            return false;
        }

        return $this->generates($this->seriesShape($master), $recurrenceId->utc());
    }

    /**
     * How many instances the rule produces strictly before $instant.
     *
     * Only splitting a COUNT-terminated series needs this. COUNT and UNTIL
     * are mutually exclusive in RFC 5545, so capping the head with UNTIL
     * costs it its COUNT, and the tail has to be given the remainder - which
     * is the original COUNT minus however many the head kept.
     */
    public function countBefore(Event $master, CarbonImmutable $instant): int
    {
        if (! $master->isRecurring()) {
            return 0;
        }

        $rrule = $this->rruleFor(
            (string) $master->recurrence_rule,
            $master->recurrence_timezone,
            CarbonImmutable::parse($master->starts_at),
        );

        $instant = $instant->utc();
        $seen = 0;

        foreach ($rrule as $occurrence) {
            if ($occurrence->getTimestamp() >= $instant->getTimestamp()) {
                break;
            }

            if (++$seen >= self::MAX_COUNT_ITERATIONS) {
                break;
            }
        }

        return $seen;
    }

    /**
     * The recurrence-relevant shape of an Event, so the private expansion
     * helpers can be reached from the Event-shaped public API without a
     * viewer and without inventing a second redaction path.
     *
     * Only the rule, its timezone, the anchor start and the RDATEs are
     * populated - nothing here reads a title, a description or a location, so
     * there is nothing to redact.
     */
    private function seriesShape(Event $master): RedactedEvent
    {
        return new RedactedEvent(
            id: $master->id,
            calendarId: $master->calendar_id,
            groupId: null,
            title: '',
            description: null,
            location: null,
            startsAt: CarbonImmutable::parse($master->starts_at)->utc(),
            endsAt: CarbonImmutable::parse($master->ends_at)->utc(),
            allDay: $master->all_day,
            visibility: $master->visibility,
            isRedacted: true,
            canEdit: false,
            createdBy: $master->created_by,
            calendarName: '',
            calendarColor: null,
            groupName: null,
            recurrenceRule: $master->recurrence_rule,
            recurrenceTimezone: $master->recurrence_timezone,
            recurrenceExdates: $master->recurrence_exdates ?? [],
            recurrenceRdates: $master->recurrence_rdates ?? [],
        );
    }

    /**
     * The instance starts this series produces in a window, as UTC instants,
     * RDATEs merged in and sorted.
     *
     * The window is widened backwards by the event's duration because an
     * instance that began before it and is still running overlaps it - the
     * same overlap rule the non-recurring query uses.
     *
     * @return list<CarbonImmutable>
     */
    private function starts(
        RedactedEvent $master,
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $duration,
    ): array {
        $windowStart = $from->subSeconds(max(0, $duration));

        $rrule = $this->rruleFor(
            $master->recurrenceRule ?? '',
            $master->recurrenceTimezone,
            $master->startsAt,
        );

        /** @var array<int, CarbonImmutable> $starts */
        $starts = [];

        // The cap is applied to the iterator itself, not to the list after
        // the fact, so an infinite high-frequency rule stops generating
        // rather than generating everything and being trimmed.
        foreach ($rrule->getOccurrencesBetween(
            $windowStart->toDateTime(),
            $to->toDateTime(),
            $this->maxOccurrences(),
        ) as $occurrence) {
            $instant = CarbonImmutable::instance($occurrence)->utc();

            // getOccurrencesBetween treats its end as inclusive; the window
            // is half-open everywhere else in this codebase, so an instance
            // starting exactly at $to belongs to the next window, not this one.
            if ($instant >= $to) {
                continue;
            }

            $starts[$instant->getTimestamp()] = $instant;
        }

        foreach ($this->instants($master->recurrenceRdates) as $rdate) {
            if ($rdate < $windowStart || $rdate >= $to) {
                continue;
            }

            $starts[$rdate->getTimestamp()] = $rdate;
        }

        ksort($starts);

        return array_values($starts);
    }

    /**
     * Whether the rule alone produces this exact instant.
     *
     * Asked over a one-day window around the instant rather than by expanding
     * the whole series, so naming an instance ten years out costs the same as
     * naming tomorrow's.
     */
    private function generates(RedactedEvent $master, CarbonImmutable $recurrenceId): bool
    {
        foreach ($this->instants($master->recurrenceRdates) as $rdate) {
            if ($rdate->getTimestamp() === $recurrenceId->getTimestamp()) {
                return true;
            }
        }

        $rrule = $this->rruleFor(
            $master->recurrenceRule ?? '',
            $master->recurrenceTimezone,
            $master->startsAt,
        );

        foreach ($rrule->getOccurrencesBetween(
            $recurrenceId->subDay()->toDateTime(),
            $recurrenceId->addDay()->toDateTime(),
            $this->maxOccurrences(),
        ) as $occurrence) {
            if ($occurrence->getTimestamp() === $recurrenceId->getTimestamp()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Overrides that no longer correspond to any instance the rule generates.
     *
     * This happens when a series' start time is moved with "all events": the
     * edits made to individual instances still exist as rows, but their
     * RECURRENCE-IDs now name instants the rule never produces. Dropping them
     * silently would delete somebody's meeting without asking, so they are
     * shown at their own times instead. They keep their RECURRENCE-ID, so
     * they stay distinct from the master in Occurrence::key().
     *
     * @param  Collection<int, RedactedEvent>  $overrides
     * @param  array<int, true>  $consumed
     * @return list<Occurrence>
     */
    private function orphanedOverrides(
        Collection $overrides,
        array $consumed,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $orphans = [];

        foreach ($overrides as $timestamp => $override) {
            if (isset($consumed[$timestamp])) {
                continue;
            }

            if ($override->startsAt >= $to || $override->endsAt <= $from) {
                continue;
            }

            $orphans[] = new Occurrence(
                event: $override,
                startsAt: $override->startsAt,
                endsAt: $override->endsAt,
                recurrenceId: $override->recurrenceInstanceId
                    ?? CarbonImmutable::createFromTimestampUTC((int) $timestamp),
            );
        }

        return $orphans;
    }

    /**
     * Where a rule stops, ignoring RDATEs.
     */
    private function ruleEnd(Event $event, string $rule, int $duration): ?CarbonImmutable
    {
        // UNTIL is already the answer, no expansion needed: the last start is
        // at or before it, so the last end is at or before UNTIL + duration.
        $until = RecurrenceRuleString::until($rule);

        if ($until !== null) {
            return $until->addSeconds($duration);
        }

        if (RecurrenceRuleString::count($rule) === null) {
            // Neither UNTIL nor COUNT: infinite, unless the rule is one of
            // the pathological ones that can never fire at all, which
            // php-rrule still reports as infinite. Either way there is no
            // usable end.
            return null;
        }

        $rrule = $this->rruleFor(
            $rule,
            $event->recurrence_timezone,
            CarbonImmutable::parse($event->starts_at),
        );

        if ($rrule->isInfinite()) {
            return null;
        }

        $last = null;
        $seen = 0;

        foreach ($rrule as $occurrence) {
            $last = $occurrence;

            if (++$seen >= self::MAX_COUNT_ITERATIONS) {
                // Truncated, so the real end is later than what we have.
                // Recording it would under-report the series' reach.
                return null;
            }
        }

        return $last === null
            ? null
            : CarbonImmutable::instance($last)->utc()->addSeconds($duration);
    }

    /**
     * Build the iterator for a stored rule.
     *
     * The single place an RRule is constructed, because the one thing that
     * must never vary is the timezone the DTSTART carries: it is a wall-clock
     * time in the series' own zone, which is what keeps "09:00" at 09:00 when
     * the UTC offset moves underneath it. A second construction site is how
     * that invariant gets broken.
     *
     * @return RRule<int, DateTime>
     */
    private function rruleFor(string $rule, ?string $timezone, CarbonImmutable $startsAt): RRule
    {
        $zone = $this->timezone($timezone);

        $dtstart = new DateTime(
            $startsAt->setTimezone($zone)->format('Y-m-d H:i:s'),
            $zone,
        );

        return new RRule($this->ruleWithoutDtstart($rule), $dtstart);
    }

    /**
     * DTSTART is supplied separately, as a real DateTime in the series'
     * timezone. A DTSTART carried inside the stored string would be a second,
     * competing source of truth for when the series begins - and a floating
     * one, with no zone attached.
     */
    private function ruleWithoutDtstart(string $rule): string
    {
        $parts = RecurrenceRuleString::parse($rule);
        unset($parts['DTSTART']);

        return RecurrenceRuleString::build($parts);
    }

    /**
     * A recurring event must name its timezone. Falling back to the app or
     * UTC default would produce occurrences that look right in testing and
     * drift by an hour twice a year in production, which is precisely the
     * failure this column exists to prevent - so an absent one is loud.
     */
    private function timezone(?string $timezone): DateTimeZone
    {
        if ($timezone === null || $timezone === '') {
            throw new \InvalidArgumentException(
                'A recurring event must carry recurrence_timezone; expanding without it silently shifts occurrences across DST boundaries.',
            );
        }

        try {
            return new DateTimeZone($timezone);
        } catch (\Exception) {
            throw new \InvalidArgumentException("Unknown recurrence timezone [{$timezone}].");
        }
    }

    private function guardWindow(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $maxDays = (int) config('calendar.recurrence.max_window_days');

        if ($from->addDays($maxDays) < $to) {
            throw new \InvalidArgumentException(
                "Refusing to expand recurrence over a window wider than {$maxDays} days.",
            );
        }
    }

    private function maxOccurrences(): int
    {
        return max(1, (int) config('calendar.recurrence.max_occurrences_per_window'));
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, true> keyed by UTC timestamp
     */
    private function instantSet(array $values): array
    {
        $set = [];

        foreach ($this->instants($values) as $instant) {
            $set[$instant->getTimestamp()] = true;
        }

        return $set;
    }

    /**
     * @param  array<int, string>  $values
     * @return list<CarbonImmutable>
     */
    private function instants(array $values): array
    {
        $instants = [];

        foreach ($values as $value) {
            try {
                $instants[] = CarbonImmutable::parse($value)->utc();
            } catch (\Exception) {
                // A malformed stored instant is skipped rather than fatal:
                // one bad EXDATE should not make a calendar unrenderable.
                continue;
            }
        }

        return $instants;
    }
}
