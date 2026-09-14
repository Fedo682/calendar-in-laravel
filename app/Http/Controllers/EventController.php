<?php

namespace App\Http\Controllers;

use App\Enums\EventMessageType;
use App\Http\Controllers\Concerns\EditsRecurringEvents;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Mail\ConflictReportedMail;
use App\Mail\EventMessageMail;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\EventMessage;
use App\Models\Group;
use App\Models\User;
use App\Support\Calendar\ConflictDetector;
use App\Support\Calendar\GroupAdminLookup;
use App\Support\Calendar\Occurrence;
use App\Support\Calendar\OccurrenceQuery;
use App\Support\Calendar\RecurrenceEditor;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    use EditsRecurringEvents;

    /** How much of the calendar to load when the client names no range. */
    private const DEFAULT_WINDOW_DAYS = 45;

    public function __construct(
        private readonly OccurrenceQuery $occurrences,
        private readonly ConflictDetector $conflicts,
        private readonly RecurrenceEditor $recurrence,
        private readonly GroupAdminLookup $groupAdminLookup,
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

        $this->applyUpdate($event, $request->attributesFor($calendar), $request->scope(), $request->occurrenceStart());

        return redirect()
            ->route('groups.calendars.events.index', [$group, $calendar])
            ->with('success', 'Event updated successfully.');
    }

    public function destroy(Request $request, Group $group, Calendar $calendar, Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $this->applyDelete($event, $this->scopeFrom($request), $this->occurrenceStartFrom($request));

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

        // "Report a conflict on event X" stops being a well-formed request
        // once X repeats - a weekly standup clashes with something on one
        // Tuesday and with nothing on the next - so the caller names the
        // instance. Absent for a one-off, where there is only one answer.
        $subject = $this->occurrences->forEvent($event, $user, $this->occurrenceStartFrom($request));

        // Null here covers both "you cannot see this" and "that instance is
        // not part of this series", which is deliberate: distinguishing them
        // would tell an outsider which timestamps a calendar they cannot read
        // happens to contain.
        if ($subject === null) {
            abort(404);
        }

        if ($this->alreadyMessaged($event, $user, $subject->startsAt)) {
            return back()->with('error', 'You already sent the admins a message about this event.');
        }

        // A window of exactly the subject's own span: anything overlapping it
        // must itself fall inside that range.
        $candidates = $this->occurrences->forUser($user, $subject->startsAt, $subject->endsAt);

        $conflicting = $this->conflicts->conflictsFor($subject, $candidates);

        if ($conflicting->isEmpty()) {
            return back()->with('success', 'No conflict found for this event anymore.');
        }

        $titles = $conflicting->map(fn (Occurrence $o) => $o->event->title)->values();

        EventMessage::create([
            'event_id' => $event->id,
            'sender_id' => $user->id,
            'type' => EventMessageType::Conflict,
            'occurrence_start' => $subject->startsAt,
            'body' => 'Reported a scheduling conflict.',
            'conflicting_titles' => $titles->all(),
        ]);

        foreach ($this->groupAdminLookup->forGroup($group) as $admin) {
            Mail::to($admin->email)->send(new ConflictReportedMail($user, $event, $titles));
        }

        return back()->with('success', 'Conflict reported to the group admin(s).');
    }

    /**
     * A member tells the group's admins about a problem event - a
     * scheduling clash they'd rather describe in their own words, or
     * anything else. Shares the same dedup row as reportConflict(): once
     * either action has been used for this sender+event+occurrence, the
     * other is blocked too.
     */
    public function sendMessage(Request $request, Group $group, Calendar $calendar, Event $event): RedirectResponse
    {
        $this->authorize('view', $event);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $subject = $this->occurrences->forEvent($event, $user, $this->occurrenceStartFrom($request));

        if ($subject === null) {
            abort(404);
        }

        if ($this->alreadyMessaged($event, $user, $subject->startsAt)) {
            return back()->with('error', 'You already sent the admins a message about this event.');
        }

        EventMessage::create([
            'event_id' => $event->id,
            'sender_id' => $user->id,
            'type' => EventMessageType::General,
            'occurrence_start' => $subject->startsAt,
            'body' => $data['body'],
        ]);

        foreach ($this->groupAdminLookup->forGroup($group) as $admin) {
            Mail::to($admin->email)->send(new EventMessageMail($user, $event, $data['body']));
        }

        return back()->with('success', 'Message sent to the group admin(s).');
    }

    private function alreadyMessaged(Event $event, User $user, CarbonImmutable $occurrenceStart): bool
    {
        return EventMessage::where('event_id', $event->id)
            ->where('sender_id', $user->id)
            ->where('occurrence_start', $occurrenceStart)
            ->exists();
    }

    protected function recurrenceEditor(): RecurrenceEditor
    {
        return $this->recurrence;
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

        // The range arrives in a query string a user can edit, and expansion
        // refuses a window wider than this rather than expand an open-ended
        // series across it. Clamping here keeps that refusal from surfacing
        // as a 500 on a URL somebody typed, matching how a malformed date in
        // the same query string is already handled.
        $maxDays = (int) config('calendar.recurrence.max_window_days');

        if ($from->copy()->addDays($maxDays)->lessThan($to)) {
            $to = $from->copy()->addDays($maxDays);
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
}
