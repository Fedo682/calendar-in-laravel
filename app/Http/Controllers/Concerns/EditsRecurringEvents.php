<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Event;
use App\Support\Calendar\RecurrenceEditor;
use App\Support\Calendar\RecurrenceScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Turning "save" and "delete" into the right write when the event repeats.
 *
 * Shared by the two controllers that write events, because the mapping from
 * a scope to an operation is a decision about the domain rather than about
 * either route: a group calendar and a personal calendar mean exactly the
 * same thing by "this and following", and having each controller decide
 * separately is how they would come to disagree.
 */
trait EditsRecurringEvents
{
    abstract protected function recurrenceEditor(): RecurrenceEditor;

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function applyUpdate(
        Event $event,
        array $attrs,
        RecurrenceScope $scope,
        ?CarbonImmutable $occurrenceStart,
    ): void {
        $series = $this->seriesFor($event);

        // A one-off has only one thing "this event" can mean, so a scope
        // sent for one is not an error, it is simply redundant.
        if (! $series->isRecurring() || $scope === RecurrenceScope::AllEvents) {
            $series->update($attrs);

            return;
        }

        $recurrenceId = $this->requireOccurrence($scope, $occurrenceStart);

        // AllEvents returned above, so only the two scoped edits remain.
        $this->guardInstance(function () use ($scope, $series, $recurrenceId, $attrs) {
            if ($scope === RecurrenceScope::ThisOccurrence) {
                $this->recurrenceEditor()->updateThisOccurrence($series, $recurrenceId, $attrs);

                return;
            }

            $this->recurrenceEditor()->updateThisAndFollowing($series, $recurrenceId, $attrs);
        });
    }

    protected function applyDelete(
        Event $event,
        RecurrenceScope $scope,
        ?CarbonImmutable $occurrenceStart,
    ): void {
        $series = $this->seriesFor($event);

        if (! $series->isRecurring()) {
            $event->delete();

            return;
        }

        if ($scope === RecurrenceScope::AllEvents) {
            $this->recurrenceEditor()->deleteAll($series);

            return;
        }

        $recurrenceId = $this->requireOccurrence($scope, $occurrenceStart);

        // AllEvents returned above, so only the two scoped deletes remain.
        $this->guardInstance(function () use ($scope, $series, $recurrenceId) {
            if ($scope === RecurrenceScope::ThisOccurrence) {
                $this->recurrenceEditor()->deleteThisOccurrence($series, $recurrenceId);

                return;
            }

            $this->recurrenceEditor()->deleteThisAndFollowing($series, $recurrenceId);
        });
    }

    /**
     * The row that owns the rule.
     *
     * Clicking an instance that already carries a "this event only" edit
     * hands back the override's id, not the master's - an override is a real
     * row and that is its identity. But every scoped operation is defined
     * against the series, so the parent is what the editor has to be given,
     * or "delete this and following" on an edited instance would be asked of
     * a row that has no rule at all.
     */
    protected function seriesFor(Event $event): Event
    {
        if ($event->recurrence_parent_id === null) {
            return $event;
        }

        return $event->recurrenceParent()->first() ?? $event;
    }

    protected function scopeFrom(Request $request): RecurrenceScope
    {
        return RecurrenceScope::tryFrom((string) $request->input('scope', ''))
            ?? RecurrenceScope::AllEvents;
    }

    protected function occurrenceStartFrom(Request $request): ?CarbonImmutable
    {
        $value = $request->input('occurrence_start');

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (\Exception) {
            return null;
        }
    }

    private function requireOccurrence(RecurrenceScope $scope, ?CarbonImmutable $occurrenceStart): CarbonImmutable
    {
        if ($occurrenceStart !== null) {
            return $occurrenceStart;
        }

        throw ValidationException::withMessages([
            'occurrence_start' => 'Which occurrence this applies to must be given for the "'.$scope->value.'" scope.',
        ]);
    }

    /**
     * Report a RECURRENCE-ID the series does not produce as "not found".
     *
     * The editor refuses it by throwing, which is right for a service - the
     * caller asked for something that does not exist. Over HTTP that is a
     * 404: the instance being named is the resource, and a stale calendar
     * tab asking to delete an occurrence that has since been removed should
     * get the same answer as asking for any other missing row, not a 500.
     *
     * @param  callable(): mixed  $operation
     */
    private function guardInstance(callable $operation): void
    {
        try {
            $operation();
        } catch (\InvalidArgumentException) {
            abort(404);
        }
    }
}
