<?php

namespace App\Policies;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use App\Policies\CalendarPolicy as Calendars;
use App\Support\Calendar\EventRedactor;

/**
 * Who may read and write events.
 *
 * Writing follows the calendar: a group calendar is an admin's to manage, a
 * personal calendar is its owner's. This is what finally gives the member
 * role somewhere to create events, having previously been read-only
 * everywhere.
 *
 * Note that being allowed to *view* an event is not the same as being allowed
 * to read its title - that second question is EventRedactor's, and a policy
 * cannot answer it because the answer is "yes, but with fields removed".
 */
class EventPolicy
{
    /**
     * Both collaborators are stateless, so they are defaulted rather than
     * required - a policy must stay constructible with `new EventPolicy()`,
     * which is how it is exercised directly in tests and how Laravel's
     * auto-discovery treats it.
     */
    public function __construct(
        private readonly Calendars $calendars = new Calendars,
        private readonly EventRedactor $redactor = new EventRedactor,
    ) {}

    public function viewAny(User $user, Calendar $calendar): bool
    {
        return $this->calendars->view($user, $calendar);
    }

    public function view(User $user, Event $event): bool
    {
        return $this->calendars->view($user, $event->calendar);
    }

    public function create(User $user, Calendar $calendar): bool
    {
        return $this->canManage($user, $calendar);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->canManage($user, $event->calendar);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->canManage($user, $event->calendar);
    }

    /**
     * Whether the viewer may read this event's title, description and
     * location, as opposed to a redacted stand-in.
     */
    public function viewDetails(User $user, Event $event): bool
    {
        return $this->redactor->canSeeDetails($event, $user);
    }

    private function canManage(User $user, Calendar $calendar): bool
    {
        if ($calendar->isPersonal()) {
            // Strictly the owner. Super Admin still passes, but through
            // Gate::before rather than through here.
            return $calendar->isOwnedBy($user);
        }

        return $this->calendars->update($user, $calendar);
    }
}
