<?php

namespace App\Observers;

use App\Models\Event;
use App\Support\Calendar\RecurrenceExpander;
use Illuminate\Support\Carbon;

/**
 * Side effects that follow an event changing.
 *
 * Three separate pieces of work hang off event writes - busting the cached
 * ICS feed, recomputing the denormalised recurrence end, and queueing an
 * outbound Google push - and they are built on separate branches. Only the
 * recurrence end exists so far.
 *
 * Anything added here that talks to a remote service must first check
 * SyncContext::suppressed(), or applying an inbound sync will bounce straight
 * back out again. Recomputing recurrence_until deliberately does *not* check
 * it: it is a pure function of columns already written to this row, so
 * skipping it during a sync would leave the row internally inconsistent
 * rather than avoid a loop.
 */
class EventObserver
{
    public function saved(Event $event): void
    {
        $this->syncRecurrenceUntil($event);
    }

    public function deleted(Event $event): void
    {
        //
    }

    /**
     * Keep recurrence_until in step with the rule that produced it.
     *
     * This lives in an observer rather than in the controllers because there
     * are already several write paths into the events table - two
     * controllers, the recurrence editor, the factory, and later the Google
     * puller - and a denormalised column that only some of them maintain is
     * worse than no denormalised column at all: the candidate query would
     * silently miss series rather than merely be slow.
     *
     * Written with saveQuietly() and only when the value actually changes.
     * Both matter: a plain save() here would re-enter this observer, and
     * writing unconditionally would touch every non-recurring event on every
     * save for nothing.
     */
    private function syncRecurrenceUntil(Event $event): void
    {
        $computed = app(RecurrenceExpander::class)->computeUntil($event);

        $current = $event->recurrence_until;

        $unchanged = $computed === null
            ? $current === null
            : $current !== null && $current->equalTo($computed);

        if ($unchanged) {
            return;
        }

        // CarbonImmutable is the right type for a service to return; the
        // column reads back as a mutable Carbon, so convert at the boundary
        // rather than widening either side.
        $event->recurrence_until = $computed === null ? null : Carbon::instance($computed);
        $event->saveQuietly();
    }
}
