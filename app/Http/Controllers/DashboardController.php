<?php

namespace App\Http\Controllers;

use App\Support\Calendar\ConflictDetector;
use App\Support\Calendar\Occurrence;
use App\Support\Calendar\OccurrenceQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OccurrenceQuery $occurrences,
        private readonly ConflictDetector $conflicts,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $windowDays = (int) config('calendar.conflicts.window_days');

        $occurrences = $this->occurrences->forUser(
            $user,
            now(),
            now()->addDays($windowDays),
        );

        // Everything here is already visible to this user, across however many
        // groups that spans, so any overlap is a real "cannot be in two places
        // at once" clash - whether it is one calendar double-booked or two
        // different groups colliding.
        $conflicts = $this->conflicts->detect($occurrences);

        $upcoming = $occurrences->map(function (Occurrence $occurrence) use ($conflicts) {
            $clashes = $conflicts[$occurrence->key()] ?? [];

            return [
                ...$occurrence->toArray(),
                // Titles come off the redacted occurrence, so a clash with
                // someone's private appointment reports as "Busy" rather than
                // naming it.
                'conflicts_with' => array_map(
                    fn (Occurrence $other) => $other->event->title,
                    $clashes,
                ),
            ];
        })->values();

        return Inertia::render('Dashboard', [
            'upcoming_events' => $upcoming,
            'window_days' => $windowDays,
        ]);
    }
}
