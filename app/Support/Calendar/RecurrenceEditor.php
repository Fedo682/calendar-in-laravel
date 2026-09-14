<?php

namespace App\Support\Calendar;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The six ways a series can be changed.
 *
 * Every calendar in existence asks the same question when you edit or delete
 * something that repeats - this event, this and following, or all events -
 * and the three answers are genuinely different operations on the stored
 * data, not three flavours of UPDATE. Keeping them here rather than in the
 * controllers means the two controllers that write events (and the Google
 * puller, later) share one implementation of the hard one.
 *
 * The representation is iCalendar's, exactly:
 *
 *  - "this event" appends an EXDATE, or writes a child row whose
 *    RECURRENCE-ID names the instance it stands in for;
 *  - "this and following" caps the original with UNTIL and starts a second
 *    series at the split;
 *  - "all events" writes the master.
 *
 * That is also Google's representation (recurringEventId plus
 * originalStartTime), so the sync phase that follows is a passthrough rather
 * than a translation.
 */
final class RecurrenceEditor
{
    public function __construct(private readonly RecurrenceExpander $expander) {}

    /**
     * Change one instance, leaving the rest of the series alone.
     *
     * Produces a child row rather than mutating anything on the master, so
     * the series keeps generating the instant as before and the child simply
     * stands in front of it at render time. Editing the same instance twice
     * updates the existing child instead of stacking a second one, which is
     * what keeps RECURRENCE-ID unique per series.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function updateThisOccurrence(Event $master, CarbonImmutable $recurrenceId, array $attrs): Event
    {
        $master = $this->requireMaster($master);
        $recurrenceId = $this->requireInstance($master, $recurrenceId);

        $existing = $this->overrideAt($master, $recurrenceId);

        if ($existing !== null) {
            $existing->update($this->editable($attrs));

            return $existing->refresh();
        }

        $duration = $this->durationSeconds($master);

        return Event::create([
            'calendar_id' => $master->calendar_id,
            'title' => $master->title,
            'description' => $master->description,
            'location' => $master->location,
            // Defaults, not constants: an edit that only changes the title
            // must leave the instance where the rule put it.
            'starts_at' => $recurrenceId,
            'ends_at' => $recurrenceId->addSeconds($duration),
            'all_day' => $master->all_day,
            'visibility' => $master->visibility,
            'created_by' => $master->created_by,
            ...$this->editable($attrs),
            // An override carries no rule of its own. It is one instance, and
            // a rule on it would make it a second series hanging off the
            // first.
            'recurrence_rule' => null,
            'recurrence_timezone' => null,
            'recurrence_exdates' => null,
            'recurrence_rdates' => null,
            'recurrence_parent_id' => $master->id,
            'recurrence_id' => $recurrenceId,
        ]);
    }

    /**
     * Change this instance and every one after it.
     *
     * The hardest of the six, and the only one that ends with two series
     * where there was one:
     *
     *  1. the original is capped with UNTIL one second before the split, so
     *     it keeps generating exactly the instances before it;
     *  2. a new master is created at the split carrying the edited
     *     attributes and the same rule;
     *  3. everything attached to an instance - EXDATEs, RDATEs and override
     *     rows - is divided at the split, each half following the series that
     *     still generates it.
     *
     * Step 3 is the part that is easy to leave out. An override at or after
     * the split whose parent stays pointed at the original is unreachable:
     * the original no longer generates its RECURRENCE-ID, so it would vanish
     * from the calendar without anybody having deleted it.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function updateThisAndFollowing(Event $master, CarbonImmutable $splitAt, array $attrs): Event
    {
        $master = $this->requireMaster($master);
        $splitAt = $this->requireInstance($master, $splitAt);

        // Splitting at the very first instance would leave a head series that
        // generates nothing. "This and following" from the start of a series
        // is "all events", and saying so keeps a dead row out of the table.
        if ($splitAt->getTimestamp() <= CarbonImmutable::parse($master->starts_at)->utc()->getTimestamp()) {
            return $this->updateAll($master, $attrs);
        }

        $rule = (string) $master->recurrence_rule;
        $duration = $this->durationSeconds($master);
        $editable = $this->editable($attrs);

        return DB::transaction(function () use ($master, $splitAt, $rule, $duration, $editable, $attrs) {
            $tail = Event::create([
                'calendar_id' => $master->calendar_id,
                'title' => $master->title,
                'description' => $master->description,
                'location' => $master->location,
                'starts_at' => $splitAt,
                'ends_at' => $splitAt->addSeconds($duration),
                'all_day' => $master->all_day,
                'visibility' => $master->visibility,
                'created_by' => $master->created_by,
                ...$editable,
                // An edit is allowed to change the cadence from the split
                // onward - "make it fortnightly from now on" is a normal
                // thing to want, and it is the tail that carries the new
                // rule. Absent one, the tail inherits the original.
                'recurrence_rule' => $attrs['recurrence_rule'] ?? $this->tailRule($master, $rule, $splitAt),
                'recurrence_timezone' => $attrs['recurrence_timezone'] ?? $master->recurrence_timezone,
                'recurrence_exdates' => $this->partitionInstants($master->recurrence_exdates, $splitAt, true),
                'recurrence_rdates' => $this->partitionInstants($master->recurrence_rdates, $splitAt, true),
                'recurrence_parent_id' => null,
                'recurrence_id' => null,
            ]);

            // Reparent before the head is capped, so an override is never
            // momentarily attached to a series that cannot generate it.
            //
            // The comparison is on RECURRENCE-ID - where the instance
            // originally sat - not on the override's own starts_at. An
            // override dragged from a Monday back to the previous Friday
            // still belongs to whichever series generates the Monday it
            // replaces, and keying off its moved time would send it to the
            // wrong side of the split.
            Event::query()
                ->where('recurrence_parent_id', $master->id)
                ->where('recurrence_id', '>=', $splitAt)
                ->update(['recurrence_parent_id' => $tail->id]);

            $master->update([
                'recurrence_rule' => RecurrenceRuleString::withUntil($rule, $splitAt->subSecond()),
                'recurrence_exdates' => $this->partitionInstants($master->recurrence_exdates, $splitAt, false),
                'recurrence_rdates' => $this->partitionInstants($master->recurrence_rdates, $splitAt, false),
            ]);

            return $tail->refresh();
        });
    }

    /**
     * Change the whole series.
     *
     * Overrides are deliberately left where they are rather than shifted or
     * deleted. If this edit moves the series' start, their RECURRENCE-IDs
     * stop naming instances the rule generates - RecurrenceExpander renders
     * those at their own times instead of dropping them, so an edit to the
     * series never silently destroys an edit somebody made to one instance.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function updateAll(Event $master, array $attrs): Event
    {
        $master = $this->requireMaster($master);

        $master->update($attrs);

        return $master->refresh();
    }

    /**
     * Remove one instance from the series.
     *
     * An EXDATE rather than a tombstone row: the instance stops being
     * generated at all, which is both what iCalendar means and what keeps a
     * deleted instance from having to be filtered out everywhere downstream.
     * Any override standing in for it goes too - the edit described an
     * instance that no longer exists.
     */
    public function deleteThisOccurrence(Event $master, CarbonImmutable $recurrenceId): void
    {
        $master = $this->requireMaster($master);
        $recurrenceId = $this->requireInstance($master, $recurrenceId);

        DB::transaction(function () use ($master, $recurrenceId) {
            $this->overrideAt($master, $recurrenceId)?->delete();

            $exdates = $master->recurrence_exdates ?? [];
            $exdates[] = $recurrenceId->toIso8601String();

            $master->update(['recurrence_exdates' => $this->uniqueInstants($exdates)]);
        });
    }

    /**
     * End the series at $splitAt, discarding everything from there on.
     *
     * UNTIL one second earlier rather than an EXDATE per remaining instance:
     * an open-ended series has infinitely many of those, and even a finite
     * one would turn a single delete into an unbounded list that every
     * consumer then has to read.
     */
    public function deleteThisAndFollowing(Event $master, CarbonImmutable $splitAt): void
    {
        $master = $this->requireMaster($master);
        $splitAt = $this->requireInstance($master, $splitAt);

        if ($splitAt->getTimestamp() <= CarbonImmutable::parse($master->starts_at)->utc()->getTimestamp()) {
            $this->deleteAll($master);

            return;
        }

        DB::transaction(function () use ($master, $splitAt) {
            Event::query()
                ->where('recurrence_parent_id', $master->id)
                ->where('recurrence_id', '>=', $splitAt)
                ->delete();

            $master->update([
                'recurrence_rule' => RecurrenceRuleString::withUntil((string) $master->recurrence_rule, $splitAt->subSecond()),
                'recurrence_exdates' => $this->partitionInstants($master->recurrence_exdates, $splitAt, false),
                'recurrence_rdates' => $this->partitionInstants($master->recurrence_rdates, $splitAt, false),
            ]);
        });
    }

    /**
     * Delete the whole series, overrides included.
     *
     * The children are deleted explicitly rather than left to the foreign
     * key's ON DELETE CASCADE. The constraint is real and does fire, but only
     * where the driver is enforcing foreign keys, and a row deletion that
     * depends on a connection-level pragma being switched on is not something
     * to find out about from a user.
     */
    public function deleteAll(Event $master): void
    {
        DB::transaction(function () use ($master) {
            Event::query()->where('recurrence_parent_id', $master->id)->delete();

            $master->delete();
        });
    }

    /**
     * The rule the tail series carries after a split.
     *
     * A COUNT-terminated series is the awkward case. The head is about to
     * lose its COUNT - RFC 5545 forbids carrying it alongside the UNTIL that
     * caps it - so the tail has to be told how many instances are left, or
     * "every weekday, 10 times" split after the third would silently become
     * 10 more from the split.
     */
    private function tailRule(Event $master, string $rule, CarbonImmutable $splitAt): string
    {
        $count = RecurrenceRuleString::count($rule);

        if ($count === null) {
            // UNTIL, or open-ended: the tail inherits the rule untouched,
            // since where the series ends has not changed.
            return $rule;
        }

        return RecurrenceRuleString::withCount($rule, $count - $this->expander->countBefore($master, $splitAt));
    }

    /**
     * Attributes a caller may set through this editor.
     *
     * Recurrence columns are stripped: which rows are masters, which are
     * overrides, and how they point at each other is this class's own
     * bookkeeping, and a caller passing recurrence_parent_id through an edit
     * would be able to reparent arbitrary events.
     *
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function editable(array $attrs): array
    {
        return array_diff_key($attrs, array_flip([
            'recurrence_rule',
            'recurrence_timezone',
            'recurrence_exdates',
            'recurrence_rdates',
            'recurrence_until',
            'recurrence_parent_id',
            'recurrence_id',
        ]));
    }

    private function overrideAt(Event $master, CarbonImmutable $recurrenceId): ?Event
    {
        return Event::query()
            ->where('recurrence_parent_id', $master->id)
            ->where('recurrence_id', $recurrenceId)
            ->first();
    }

    private function durationSeconds(Event $event): int
    {
        return CarbonImmutable::parse($event->ends_at)->getTimestamp()
            - CarbonImmutable::parse($event->starts_at)->getTimestamp();
    }

    private function requireMaster(Event $event): Event
    {
        if (! $event->isRecurring()) {
            throw new \InvalidArgumentException('Event '.$event->id.' is not a recurring series.');
        }

        return $event;
    }

    /**
     * Refuse a RECURRENCE-ID the series does not actually produce.
     *
     * This is the guard that keeps the child table honest: without it a
     * client could name any timestamp and get back an override that nothing
     * will ever render, because no instance of the series shares its
     * RECURRENCE-ID.
     */
    private function requireInstance(Event $master, CarbonImmutable $recurrenceId): CarbonImmutable
    {
        $recurrenceId = $recurrenceId->utc();

        if (! $this->expander->generatesInstance($master, $recurrenceId)) {
            throw new \InvalidArgumentException(
                $recurrenceId->toIso8601String().' is not an instance of event '.$master->id.'.',
            );
        }

        return $recurrenceId;
    }

    /**
     * One side of a split, for a stored list of instants.
     *
     * @param  list<string>|null  $values
     * @return list<string>|null
     */
    private function partitionInstants(?array $values, CarbonImmutable $splitAt, bool $after): ?array
    {
        if ($values === null || $values === []) {
            return null;
        }

        $kept = [];

        foreach ($values as $value) {
            try {
                $instant = CarbonImmutable::parse($value)->utc();
            } catch (\Exception) {
                continue;
            }

            $isAfter = $instant->getTimestamp() >= $splitAt->getTimestamp();

            if ($isAfter === $after) {
                $kept[] = $instant->toIso8601String();
            }
        }

        return $kept === [] ? null : $kept;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueInstants(array $values): array
    {
        $byTimestamp = [];

        foreach ($values as $value) {
            try {
                $instant = CarbonImmutable::parse($value)->utc();
            } catch (\Exception) {
                continue;
            }

            $byTimestamp[$instant->getTimestamp()] = $instant->toIso8601String();
        }

        ksort($byTimestamp);

        return array_values($byTimestamp);
    }
}
