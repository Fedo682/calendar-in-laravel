<?php

namespace App\Http\Controllers;

use App\Enums\EventMessageType;
use App\Models\EventMessage;
use App\Models\Group;
use App\Support\Calendar\EventRedactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin-facing inbox for what group members have sent about this
 * group's events: free-text messages and scheduling-conflict reports.
 *
 * Both share one table (`event_messages`) and one dedup rule - see
 * EventController::reportConflict()/sendMessage() - so this is simply that
 * table's read (and resolve) side, split into two lists by `type` for
 * display.
 */
class GroupMessageController extends Controller
{
    public function __construct(
        private readonly EventRedactor $redactor,
    ) {}

    public function index(Group $group): Response
    {
        Gate::authorize('viewMessages', $group);

        $viewer = auth()->user();

        $rows = EventMessage::query()
            ->whereHas('event.calendar', fn ($query) => $query->where('group_id', $group->id))
            ->with(['event.calendar', 'sender'])
            ->latest()
            ->get();

        [$reports, $messages] = $rows->partition(
            fn (EventMessage $message) => $message->type === EventMessageType::Conflict,
        );

        $shape = function (EventMessage $message) use ($group, $viewer) {
            $event = $message->event;
            $redacted = $this->redactor->redact($event, $viewer);

            return [
                'id' => $message->id,
                'sender_name' => $message->sender->name,
                'sender_email' => $message->sender->email,
                'body' => $message->body,
                'conflicting_titles' => $message->conflicting_titles,
                'occurrence_start' => $message->occurrence_start->toIso8601String(),
                'created_at' => $message->created_at->toIso8601String(),
                'resolved' => $message->isResolved(),
                'event_title' => $redacted->title,
                'calendar_name' => $redacted->calendarName,
                'event_url' => route('groups.calendars.events.index', [$group, $event->calendar_id]),
            ];
        };

        return Inertia::render('Groups/Messages', [
            'group' => ['id' => $group->id, 'name' => $group->name],
            'messages' => $messages->map($shape)->values(),
            'reports' => $reports->map($shape)->values(),
        ]);
    }

    public function update(Request $request, Group $group, EventMessage $message): RedirectResponse
    {
        Gate::authorize('viewMessages', $group);

        // A message id is not scoped to a group by itself - confirm it
        // actually belongs to this one rather than trusting the URL, the
        // same way EventController verifies {event} against {calendar}.
        if ($message->event->calendar->group_id !== $group->id) {
            abort(404);
        }

        $data = $request->validate(['resolved' => ['required', 'boolean']]);

        $message->update(['resolved_at' => $data['resolved'] ? now() : null]);

        return back()->with('success', $data['resolved'] ? 'Marked resolved.' : 'Marked unresolved.');
    }
}
