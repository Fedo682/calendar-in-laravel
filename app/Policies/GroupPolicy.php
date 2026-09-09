<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    /**
     * Everyone is allowed to hit the index; the controller decides what
     * subset of groups (all vs. "my groups") gets returned.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Super Admins bypass via Gate::before. A regular user may view a
     * group only if they are a member of it (any role).
     */
    public function view(User $user, Group $group): bool
    {
        return $user->roleInGroup($group) !== null;
    }

    /**
     * Group creation is Super-Admin-only; Gate::before is what actually
     * lets a Super Admin through, so this always returns false here.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Super-Admin-only (via Gate::before).
     */
    public function update(User $user, Group $group): bool
    {
        return false;
    }

    /**
     * Super-Admin-only (via Gate::before).
     */
    public function delete(User $user, Group $group): bool
    {
        return false;
    }

    /**
     * Manage this group's membership (add/remove members, change roles
     * other than assigning 'admin'). Super Admins bypass via Gate::before.
     * A regular user may manage membership only if they hold the 'admin'
     * role in this group.
     */
    public function manageMembers(User $user, Group $group): bool
    {
        return $user->roleInGroup($group) === 'admin';
    }

    public function restore(User $user, Group $group): bool
    {
        return false;
    }

    public function forceDelete(User $user, Group $group): bool
    {
        return false;
    }
}
