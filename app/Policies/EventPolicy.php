<?php

namespace App\Policies;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Any member (admin or member) of the calendar's group may view.
     */
    public function viewAny(User $user, Calendar $calendar): bool
    {
        return $user->roleInGroup($calendar->group) !== null;
    }

    /**
     * Any member (admin or member) of the event's calendar's group may view.
     */
    public function view(User $user, Event $event): bool
    {
        return $user->roleInGroup($event->calendar->group) !== null;
    }

    /**
     * Only group admins may create events.
     */
    public function create(User $user, Calendar $calendar): bool
    {
        return $user->roleInGroup($calendar->group) === 'admin';
    }

    /**
     * Only group admins may update events.
     */
    public function update(User $user, Event $event): bool
    {
        return $user->roleInGroup($event->calendar->group) === 'admin';
    }

    /**
     * Only group admins may delete events.
     */
    public function delete(User $user, Event $event): bool
    {
        return $user->roleInGroup($event->calendar->group) === 'admin';
    }
}
