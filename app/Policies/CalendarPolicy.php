<?php

namespace App\Policies;

use App\Models\Calendar;
use App\Models\Group;
use App\Models\User;

class CalendarPolicy
{
    /**
     * Any member (admin or member) of the group can list its calendars.
     */
    public function viewAny(User $user, Group $group): bool
    {
        return $user->roleInGroup($group) !== null;
    }

    /**
     * Any member (admin or member) of the calendar's group can view it.
     */
    public function view(User $user, Calendar $calendar): bool
    {
        return $user->roleInGroup($calendar->group) !== null;
    }

    /**
     * Only a group admin can create calendars within the group.
     */
    public function create(User $user, Group $group): bool
    {
        return $user->roleInGroup($group) === 'admin';
    }

    /**
     * Only a group admin can update calendars within the group.
     */
    public function update(User $user, Calendar $calendar): bool
    {
        return $user->roleInGroup($calendar->group) === 'admin';
    }

    /**
     * Only a group admin can delete calendars within the group.
     */
    public function delete(User $user, Calendar $calendar): bool
    {
        return $user->roleInGroup($calendar->group) === 'admin';
    }
}
