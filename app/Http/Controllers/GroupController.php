<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            $groups = Group::withCount('members')->latest()->get();
        } else {
            $groups = $user->memberGroups()->withCount('members')->latest()->get();
        }

        return Inertia::render('Groups/Index', [
            'groups' => $groups,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Group::class);

        return Inertia::render('Groups/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Group::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['created_by'] = auth()->id();

        Group::create($validated);

        return redirect()->route('groups.index')->with('success', 'Group created successfully!');
    }

    public function show(Group $group): Response
    {
        Gate::authorize('view', $group);

        $user = auth()->user();

        $calendars = $group->calendars()->get();

        $members = $group->members()
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => Role::find($member->pivot->role_id)?->name,
                ];
            });

        $role = $user->roleInGroup($group);

        return Inertia::render('Groups/Show', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'created_at' => $group->created_at,
            ],
            'calendars' => $calendars,
            'members' => $members,
            'can_manage' => $role === 'admin' || $user->isSuperAdmin(),
            'is_group_admin' => $role === 'admin',
            'is_super_admin' => $user->isSuperAdmin(),
        ]);
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        Gate::authorize('update', $group);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $group->update($validated);

        return redirect()->route('groups.show', $group)->with('success', 'Group updated successfully!');
    }

    public function destroy(Group $group): RedirectResponse
    {
        Gate::authorize('delete', $group);

        $group->delete();

        return redirect()->route('groups.index')->with('success', 'Group deleted successfully!');
    }
}
