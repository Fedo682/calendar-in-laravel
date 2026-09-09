<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Days ahead (inclusive of today) the "upcoming" agenda covers.
     */
    private const WINDOW_DAYS = 7;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $calendarIds = $user->accessibleCalendarIds();

        $windowStart = now();
        $windowEnd = now()->addDays(self::WINDOW_DAYS);

        $events = Event::with(['calendar.group'])
            ->whereIn('calendar_id', $calendarIds)
            ->whereBetween('starts_at', [$windowStart, $windowEnd])
            ->orderBy('starts_at')
            ->get();

        // Every event here is one this user can see, across however many
        // calendars/groups that spans - so any overlap in this set is a
        // real "can't be in two places at once" conflict for them,
        // whether it's the same calendar double-booked or two different
        // groups' calendars colliding.
        $upcoming = $events->map(function (Event $event) use ($events, $user) {
            $conflicts = $events->filter(fn (Event $other) => $other->id !== $event->id
                && $event->starts_at->lt($other->ends_at)
                && $event->ends_at->gt($other->starts_at)
            )->values();

            $group = $event->calendar->group;

            return [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'all_day' => $event->all_day,
                'group_id' => $group->id,
                'group_name' => $group->name,
                'calendar_id' => $event->calendar_id,
                'calendar_name' => $event->calendar->name,
                'calendar_color' => $event->calendar->color,
                'can_manage' => $user->roleInGroup($group) === 'admin' || $user->isSuperAdmin(),
                'conflicts_with' => $conflicts->pluck('title')->values(),
            ];
        })->values();

        return Inertia::render('Dashboard', [
            'upcoming_events' => $upcoming,
            'window_days' => self::WINDOW_DAYS,
        ]);
    }
}
