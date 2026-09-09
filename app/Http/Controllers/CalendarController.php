<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Display every calendar the user can access, across all their groups
     * (all calendars in the system for a Super Admin). This is the direct
     * "My Calendars" entry point, independent of any single group.
     */
    public function all(Request $request): Response
    {
        $user = $request->user();

        $calendars = Calendar::with('group')
            ->whereIn('id', $user->accessibleCalendarIds())
            ->orderBy('name')
            ->get();

        return Inertia::render('Calendars/Overview', [
            'calendars' => $calendars,
        ]);
    }

    /**
     * Display a listing of the group's calendars.
     */
    public function index(Request $request, Group $group): Response
    {
        $this->authorize('viewAny', [Calendar::class, $group]);

        return Inertia::render('Calendars/Index', [
            'group' => $group,
            'calendars' => $group->calendars,
            'can_manage' => $request->user()->roleInGroup($group) === 'admin'
                || $request->user()->isSuperAdmin(),
        ]);
    }

    /**
     * Store a newly created calendar in the group.
     */
    public function store(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('create', [Calendar::class, $group]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ]);

        $group->calendars()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('groups.calendars.index', $group)
            ->with('success', 'Calendar created successfully!');
    }

    /**
     * Display the specified calendar.
     *
     * There is no dedicated "show" page for a calendar - viewing a
     * calendar's events is owned by the Events experience. Once
     * authorized, redirect straight into that.
     */
    public function show(Group $group, Calendar $calendar): RedirectResponse
    {
        $this->authorize('view', $calendar);

        return redirect()->route('groups.calendars.events.index', [$group, $calendar]);
    }

    /**
     * Update the specified calendar.
     */
    public function update(Request $request, Group $group, Calendar $calendar): RedirectResponse
    {
        $this->authorize('update', $calendar);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ]);

        $calendar->update($validated);

        return redirect()->route('groups.calendars.index', $group)
            ->with('success', 'Calendar updated successfully!');
    }

    /**
     * Remove the specified calendar.
     */
    public function destroy(Group $group, Calendar $calendar): RedirectResponse
    {
        $this->authorize('delete', $calendar);

        $calendar->delete();

        return redirect()->route('groups.calendars.index', $group)
            ->with('success', 'Calendar deleted successfully!');
    }
}
