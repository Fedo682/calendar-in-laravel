<?php

namespace App\Support\Calendar;

use App\Enums\RoleName;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who to notify about a group's event: its admins.
 *
 * Extracted from EventController so both the existing "report conflict"
 * action and the new "message admin" action look this up the same way,
 * rather than one drifting from the other.
 */
final class GroupAdminLookup
{
    /**
     * @return Collection<int, User>
     */
    public function forGroup(Group $group): Collection
    {
        $adminRoleId = Role::where('name', RoleName::Admin->value)->value('id');

        return GroupUser::where('group_id', $group->id)
            ->where('role_id', $adminRoleId)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->values();
    }
}
