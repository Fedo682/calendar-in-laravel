<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GroupMembershipController extends Controller
{
    public function index(Group $group): JsonResponse
    {
        Gate::authorize('view', $group);

        $members = $group->members()
            ->get()
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => Role::find($member->pivot->role_id)?->name,
            ]);

        return response()->json(['members' => $members]);
    }

    public function store(Request $request, Group $group): RedirectResponse
    {
        Gate::authorize('manageMembers', $group);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', Rule::in(['admin', 'member'])],
        ]);

        $this->guardAdminRoleAssignment($validated['role']);

        GroupUser::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $validated['user_id']],
            ['role_id' => Role::where('name', $validated['role'])->value('id')],
        );

        return back()->with('success', 'Member added successfully!');
    }

    public function update(Request $request, Group $group, User $user): RedirectResponse
    {
        $this->ensureMemberBelongsToGroup($group, $user);

        Gate::authorize('manageMembers', $group);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'member'])],
        ]);

        $this->guardAdminRoleAssignment($validated['role']);

        GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->update(['role_id' => Role::where('name', $validated['role'])->value('id')]);

        return back()->with('success', 'Member role updated successfully!');
    }

    public function destroy(Group $group, User $user): RedirectResponse
    {
        $membership = $this->ensureMemberBelongsToGroup($group, $user);

        Gate::authorize('manageMembers', $group);

        // Only a Super Admin may remove another group Admin.
        $currentRole = Role::find($membership->role_id)?->name;
        if ($currentRole === 'admin' && ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Only a Super Admin can remove a group Admin.');
        }

        $membership->delete();

        return back()->with('success', 'Member removed successfully!');
    }

    /**
     * Only a Super Admin may assign/change who holds the 'admin' role
     * in a group.
     */
    private function guardAdminRoleAssignment(string $role): void
    {
        if ($role === 'admin' && ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Only a Super Admin can assign the admin role.');
        }
    }

    /**
     * Explicitly verify that $user is actually a member of $group before
     * allowing any mutation - the {user} route parameter is not scoped
     * to the group by Laravel's implicit binding.
     */
    private function ensureMemberBelongsToGroup(Group $group, User $user): GroupUser
    {
        $membership = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            throw new NotFoundHttpException('User is not a member of this group.');
        }

        return $membership;
    }
}
