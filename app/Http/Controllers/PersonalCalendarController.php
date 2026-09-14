<?php

namespace App\Http\Controllers;

use App\Support\Calendar\Occurrence;
use App\Support\Calendar\OccurrenceQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The calendar a user owns outright.
 *
 * Separate from CalendarController because that one is nested under a group
 * and every one of its routes carries a {group} segment. A personal calendar
 * has no group, so it gets its own flat route.
 */
class PersonalCalendarController extends Controller
{
    /** How much of the calendar to load when the client names no range. */
    private const DEFAULT_WINDOW_DAYS = 45;

    public function __construct(private readonly OccurrenceQuery $occurrences) {}

    public function show(Request $request): Response
    {
        $user = $request->user();

        // Created on first visit rather than at registration, so users who
        // predate this feature get one without a backfill having to have
        // caught them.
        $calendar = $user->personalCalendar();

        $this->authorize('view', $calendar);

        $from = now()->subDays(self::DEFAULT_WINDOW_DAYS);
        $to = now()->addDays(self::DEFAULT_WINDOW_DAYS);

        return Inertia::render('Calendars/Personal', [
            'calendar' => $calendar,
            'occurrences' => $this->occurrences
                ->forCalendar($calendar, $from, $to, $user)
                ->map(fn (Occurrence $occurrence) => $occurrence->toArray())
                ->values(),
            'range' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
        ]);
    }
}
