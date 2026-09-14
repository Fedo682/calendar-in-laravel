<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Calendar\ConflictDetector;
use App\Support\Calendar\Occurrence;
use App\Support\Calendar\OccurrenceQuery;
use App\Support\Calendar\WritableCalendars;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OccurrenceQuery $occurrences,
        private readonly ConflictDetector $conflicts,
        private readonly WritableCalendars $writable,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $windowDays = (int) config('calendar.conflicts.window_days');

        $month = $this->month($request);
        [$gridFrom, $gridTo] = $this->gridRange($month);

        $scheduleIds = $user->scheduleCalendarIds();

        // Two windows, two queries. The grid covers the month on screen; the
        // agenda beside it covers the next few days, which is a different
        // question and almost never the same set of events.
        $monthOccurrences = $this->occurrences->forCalendars($scheduleIds, $gridFrom, $gridTo, $user);

        $upcoming = $this->occurrences->forCalendars($scheduleIds, now(), now()->addDays($windowDays), $user);

        // Conflicts are checked over a narrower set than the grid displays:
        // personalCalendarIds() has no Super Admin escalation, so two
        // unrelated groups' events no longer read as "conflicting" just
        // because a platform role can see both of them.
        $conflictCandidates = $this->occurrences->forCalendars(
            $user->personalCalendarIds(),
            now(),
            now()->addDays($windowDays),
            $user,
        );
        $conflicts = $this->conflicts->detect($conflictCandidates);

        return Inertia::render('Dashboard', [
            'month' => $month->toDateString(),
            'occurrences' => $monthOccurrences
                ->map(fn (Occurrence $occurrence) => $occurrence->toArray())
                ->values(),
            'upcoming_events' => $upcoming->map(function (Occurrence $occurrence) use ($conflicts) {
                $clashes = $conflicts[$occurrence->key()] ?? [];

                return [
                    ...$occurrence->toArray(),
                    // Titles come off the redacted occurrence, so a clash with
                    // someone's private appointment reads as "Busy" rather
                    // than naming it.
                    'conflicts_with' => array_map(
                        fn (Occurrence $other) => $other->event->title,
                        $clashes,
                    ),
                ];
            })->values(),
            'window_days' => $windowDays,
            'writable_calendars' => $this->writable->options($user),
            'team_busy' => $this->teamBusyPayload($user, $windowDays),
        ]);
    }

    /**
     * @return list<array{group_id: int, group_name: string, busy_count: int, occurrences: list<array<string, mixed>>}>
     */
    private function teamBusyPayload(User $user, int $windowDays): array
    {
        return $user->teamBusyByGroup()->map(function (array $entry) use ($user, $windowDays) {
            $occurrences = $entry['calendar_ids']->isEmpty()
                ? collect()
                : $this->occurrences->forCalendars(
                    $entry['calendar_ids'],
                    now(),
                    now()->addDays($windowDays),
                    $user,
                );

            return [
                'group_id' => $entry['group']->id,
                'group_name' => $entry['group']->name,
                'busy_count' => $occurrences->count(),
                'occurrences' => $occurrences->map(fn (Occurrence $o) => $o->toArray())->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * The month the grid is showing, from ?month=YYYY-MM.
     */
    private function month(Request $request): CarbonImmutable
    {
        $value = $request->query('month');

        if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            try {
                return CarbonImmutable::parse($value.'-01')->startOfMonth();
            } catch (\Exception) {
                // A hand-edited query string falls back to this month rather
                // than 500ing.
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * The range the month grid actually renders.
     *
     * Six weeks from the Sunday on or before the 1st, matching MonthGrid's
     * 42 cells - querying only the month itself would leave the leading and
     * trailing days of the grid empty.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function gridRange(CarbonImmutable $month): array
    {
        $start = $month->subDays($month->dayOfWeek)->startOfDay();

        return [$start, $start->addDays(42)];
    }
}
