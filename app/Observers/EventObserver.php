<?php

namespace App\Observers;

use App\Models\Event;

/**
 * Side effects that follow an event changing.
 *
 * Deliberately empty at this point. Three separate pieces of work need to
 * hang off event writes - busting the cached ICS feed, recomputing the
 * denormalised recurrence end, and queueing an outbound Google push - and
 * they are built on separate branches. Creating the observer up front means
 * each of those adds a method to a file that already exists rather than
 * racing to create the same file.
 *
 * Anything added here that talks to a remote service must first check
 * SyncContext::suppressed(), or applying an inbound sync will bounce
 * straight back out again.
 */
class EventObserver
{
    public function saved(Event $event): void
    {
        //
    }

    public function deleted(Event $event): void
    {
        //
    }
}
