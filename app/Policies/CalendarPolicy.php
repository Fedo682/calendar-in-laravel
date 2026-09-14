<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Calendar;
use App\Models\Group;
use App\Models\User;

/**
 * A calendar is reachable either through a group the user belongs to, or by
 * being the user's own.
 *
 * The class-level abilities take a Group because they are only ever reached
 * through the group-nested routes. Personal calendars have no group, so they
 * use createPersonal() and the instance abilities below, which fall back to
 * ownership when group_id is null.
 */
class CalendarPolicy
{
    public function viewAny(User $user, Group $group): bool
    {
        return $user->roleInGroup($group) !== null;
    }

    public function view(User $user, Calendar $calendar): bool
    {
        return $this->isOwner($user, $calendar) || $this->isMember($user, $calendar);
    }

    public function create(User $user, Group $group): bool
    {
        return $this->isGroupAdmin($user, $group);
    }

    /**
     * Everyone may have a calendar of their own; the unique index on owner_id
     * is what stops them having two.
     */
    public function createPersonal(User $user): bool
    {
        return true;
    }

    public function update(User $user, Calendar $calendar): bool
    {
        return $this->isOwner($user, $calendar) || $this->isAdminOf($user, $calendar);
    }

    public function delete(User $user, Calendar $calendar): bool
    {
        // A personal calendar is not deletable: the app assumes every user has
        // exactly one, and removing it would take its events with it.
        if ($calendar->isPersonal()) {
            return false;
        }

        return $this->isAdminOf($user, $calendar);
    }

    private function isOwner(User $user, Calendar $calendar): bool
    {
        return $calendar->isOwnedBy($user);
    }

    private function isMember(User $user, Calendar $calendar): bool
    {
        $group = $calendar->group;

        return $group !== null && $user->roleInGroup($group) !== null;
    }

    private function isAdminOf(User $user, Calendar $calendar): bool
    {
        $group = $calendar->group;

        return $group !== null && $this->isGroupAdmin($user, $group);
    }

    private function isGroupAdmin(User $user, Group $group): bool
    {
        return $user->roleInGroup($group) === RoleName::Admin->value;
    }
}
