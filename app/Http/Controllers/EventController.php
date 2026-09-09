<?php

namespace App\Http\Controllers;

use App\Mail\ConflictReportedMail;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * Display the month-view calendar (list of events) for a given calendar.
     */
    public function index(Request $request, Group $group, Calendar $calendar): Response
    {
        $this->authorize('viewAny', [Event::class, $calendar]);

        $user = $request->user();

        return Inertia::render('Events/Index', [
            'group' => $group,
            'calendar' => $calendar,
            'events' => $calendar->events()->orderBy('starts_at')->get(),
            'can_manage' => $user->roleInGroup($group) === 'admin' || $user->isSuperAdmin(),
        ]);
    }

    /**
     * Store a newly created event on the calendar.
     */
    public function store(Request $request, Group $group, Calendar $calendar): RedirectResponse
    {
        $this->authorize('create', [Event::class, $calendar]);

        $validated = $this->validateEvent($request);

        $calendar->events()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('groups.calendars.events.index', [$group, $calendar])
            ->with('success', 'Event created successfully.');
    }

    /**
     * Display a single event.
     *
     * There is no dedicated detail page for events - the month grid on the
     * index page is the primary UX (with a dialog for viewing/editing), so
     * this simply redirects back to the index.
     */
    public function show(Group $group, Calendar $calendar, Event $event): RedirectResponse
    {
        $this->authorize('view', $event);

        return redirect()->route('groups.calendars.events.index', [$group, $calendar]);
    }

    /**
     * Update the specified event.
     */
    public function update(Request $request, Group $group, Calendar $calendar, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $this->validateEvent($request);

        $event->update($validated);

        return redirect()
            ->route('groups.calendars.events.index', [$group, $calendar])
            ->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified event.
     */
    public function destroy(Group $group, Calendar $calendar, Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return redirect()
            ->route('groups.calendars.events.index', [$group, $calendar])
            ->with('success', 'Event deleted successfully.');
    }

    /**
     * A member reports that this event clashes with something else on
     * their visible calendars. Recomputes the conflict server-side
     * (never trusts the client's list) and emails the event's group
     * admins so they can resolve it.
     */
    public function reportConflict(Request $request, Group $group, Calendar $calendar, Event $event): RedirectResponse
    {
        $this->authorize('view', $event);

        $user = $request->user();

        $conflictingTitles = Event::whereIn('calendar_id', $user->accessibleCalendarIds())
            ->where('id', '!=', $event->id)
            ->where('starts_at', '<', $event->ends_at)
            ->where('ends_at', '>', $event->starts_at)
            ->pluck('title');

        if ($conflictingTitles->isEmpty()) {
            return back()->with('success', 'No conflict found for this event anymore.');
        }

        $adminRoleId = Role::where('name', 'admin')->value('id');
        $admins = GroupUser::where('group_id', $group->id)
            ->where('role_id', $adminRoleId)
            ->with('user')
            ->get()
            ->pluck('user');

        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new ConflictReportedMail($user, $event, $conflictingTitles));
        }

        return back()->with('success', 'Conflict reported to the group admin(s).');
    }

    /**
     * Validate the request payload shared by store and update.
     *
     * @return array<string, mixed>
     */
    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['boolean'],
        ]);
    }
}
