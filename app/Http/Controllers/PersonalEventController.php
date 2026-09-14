<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EditsRecurringEvents;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Support\Calendar\RecurrenceEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Events on the calendar a user owns.
 *
 * This is what finally gives the member role somewhere to write. Group
 * calendars stay admin-managed; a personal calendar is its owner's, and
 * anything created here defaults to private, so it shows to everyone else as
 * an opaque "Busy" block.
 *
 * Separate from EventController because these routes carry no {group} and no
 * {calendar} segment - the calendar is whichever one belongs to the caller.
 */
class PersonalEventController extends Controller
{
    use EditsRecurringEvents;

    public function __construct(private readonly RecurrenceEditor $recurrence) {}

    protected function recurrenceEditor(): RecurrenceEditor
    {
        return $this->recurrence;
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $calendar = $request->user()->personalCalendar();

        $this->authorize('create', [Event::class, $calendar]);

        $calendar->events()->create([
            ...$request->attributesFor($calendar),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('calendars.personal')
            ->with('success', 'Event created successfully.');
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        // The policy is what enforces that this is the caller's own personal
        // event: it only grants write on a personal calendar to its owner.
        $this->authorize('update', $event);

        $this->applyUpdate(
            $event,
            $request->attributesFor($event->calendar),
            $request->scope(),
            $request->occurrenceStart(),
        );

        return redirect()
            ->route('calendars.personal')
            ->with('success', 'Event updated successfully.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $this->applyDelete($event, $this->scopeFrom($request), $this->occurrenceStartFrom($request));

        return redirect()
            ->route('calendars.personal')
            ->with('success', 'Event deleted successfully.');
    }
}
