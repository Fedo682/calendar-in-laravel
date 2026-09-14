<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Mail\ConflictReportedMail;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Calendar\ConflictDetector;
use App\Support\Calendar\Occurrence;
use App\Support\Calendar\OccurrenceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /** How much of the calendar to load when the client names no range. */
    private const DEFAULT_WINDOW_DAYS = 45;

    public function __construct(
        private readonly OccurrenceQuery $occurrences,
        private readonly ConflictDetector $conflicts,
    ) {}

    /**
     * The calendar view for one calendar.
     */
    public function index(Request $request, Group $group, Calendar $calendar): Response
    {
        $this->authorize('viewAny', [Event::class, $calendar]);

        $user = $request->user();
        [$from, $to] = $this->window($request);

        return Inertia::render('Events/Index', [
            'group' => $group,
            'calendar' => $calendar,
            'occurrences' => $this->occurrences
                ->forCalendar($calendar, $from, $to, $user)
                ->map(fn (Occurrence $occurrence) => $occurrence->toArray())
                ->values(),
            'range' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'can_manage' => $user->can('create', [Event::class, $calendar]),
        ]);
    }

    public function store(StoreEventRequest $request, Group $group, Calendar $calendar): RedirectResponse
    {
        $this->authorize('create', [Event::class, $calendar]);

        $calendar->events()->create([
            ...$request->attributesFor($calendar),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('groups.calendars.events.index', [$group, $calendar])
            ->with('success', 'Event created successfully.');
    }

    /**
     * There is no dedicated detail page - the grid plus its dialog is the
     * whole UX - so this just returns to the index.
     */
    public function show(Group $group, Calendar $calendar, Event $event): RedirectResponse
    {
        $this->authorize('view', $event);

        return redirect()->route('groups.calendars.events.index', [$group, $calendar]);
    }

    public function update(UpdateEventRequest $request, Group $group, Calendar $calendar, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->update($request->attributesFor($calendar));

        return redirect()
            ->route('groups.calendars.events.index', [$group, $calendar])
            ->with('success', 'Event updated successfully.');
    }

    public function destroy(Group $group, Calendar $calendar, Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return redirect()
            ->route('groups.calendars.events.index', [$group, $calendar])
            ->with('success', 'Event deleted successfully.');
    }

    /**
     * A member reports that this event clashes with something else they can
     * see, and the group's admins get told.
     *
     * The clash is always recomputed here rather than trusted from the client,
     * and it is computed over redacted occurrences - so an event colliding
     * with someone's private appointment is reported as "Busy" instead of
     * emailing its real title to every admin in the group.
     */
    public function reportConflict(Request $request, Group $group, Calendar $calendar, Event $event): RedirectResponse
    {
        $this->authorize('view', $event);

        $user = $request->user();

        $subject = $this->occurrences->forEvent($event, $user);

        if ($subject === null) {
            abort(404);
        }

        // A window of exactly the subject's own span: anything overlapping it
        // must itself fall inside that range.
        $candidates = $this->occurrences->forUser($user, $subject->startsAt, $subject->endsAt);

        $conflicting = $this->conflicts->conflictsFor($subject, $candidates);

        if ($conflicting->isEmpty()) {
            return back()->with('success', 'No conflict found for this event anymore.');
        }

        $titles = $conflicting->map(fn (Occurrence $o) => $o->event->title)->values();

        foreach ($this->groupAdmins($group) as $admin) {
            Mail::to($admin->email)->send(new ConflictReportedMail($user, $event, $titles));
        }

        return back()->with('success', 'Conflict reported to the group admin(s).');
    }

    /**
     * The range to load, from ?from= and ?to=, falling back to a window
     * around today. Bounded either way - the calendar's whole history is
     * never loaded at once.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(Request $request): array
    {
        $from = $this->parseDate($request->query('from')) ?? now()->subDays(self::DEFAULT_WINDOW_DAYS);
        $to = $this->parseDate($request->query('to')) ?? now()->addDays(self::DEFAULT_WINDOW_DAYS);

        if ($to->lessThanOrEqualTo($from)) {
            $to = $from->copy()->addDays(self::DEFAULT_WINDOW_DAYS);
        }

        return [$from, $to];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            // A malformed range falls back to the default window rather than
            // 500ing on a query string a user can edit.
            return null;
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function groupAdmins(Group $group): Collection
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
