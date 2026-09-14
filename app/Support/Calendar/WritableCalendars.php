<?php

namespace App\Support\Calendar;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The calendars a user may create events on.
 *
 * Needed because "new event" is no longer a question with one answer. A
 * member can write to their own calendar and nowhere else; an admin can write
 * to their groups' calendars and their own; a Super Admin can write to every
 * group calendar and their own, but - deliberately - not to anybody else's
 * personal one.
 *
 * Rather than re-derive those rules, this asks EventPolicy the same question
 * the controller will ask when the request actually arrives. That keeps the
 * picker from ever offering a calendar the subsequent POST would refuse.
 */
final class WritableCalendars
{
    /**
     * @return Collection<int, Calendar>
     */
    public function for(User $user): Collection
    {
        // firstOrCreate, so a user who has never opened their own calendar is
        // still offered it here rather than having to visit it first.
        $personal = $user->personalCalendar();

        return Calendar::query()
            ->with('group')
            ->whereIn('id', $user->accessibleCalendarIds())
            ->orderByRaw('CASE WHEN owner_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('name')
            ->get()
            ->filter(fn (Calendar $calendar) => $user->can('create', [Event::class, $calendar]))
            ->values()
            ->whenEmpty(fn () => collect([$personal]));
    }

    /**
     * The shape the frontend's calendar picker consumes.
     *
     * @return list<array{id: int, name: string, color: string|null, type: string, group_id: int|null, group_name: string|null, create_url: string}>
     */
    public function options(User $user): array
    {
        // array_values around the whole thing: for() ends in whenEmpty(),
        // whose union return type loses the guarantee that the keys are
        // sequential, and a JSON array rather than an object is exactly what
        // the picker needs.
        return array_values($this->for($user)
            ->map(fn (Calendar $calendar) => [
                'id' => $calendar->id,
                'name' => $calendar->name,
                'color' => $calendar->color,
                'type' => $calendar->type,
                'group_id' => $calendar->group_id,
                'group_name' => $calendar->group?->name,
                // The picker chooses which existing endpoint to post to rather
                // than introducing one that takes a calendar id - so every
                // create still authorises through the route that owns it.
                'create_url' => $calendar->isPersonal()
                    ? route('calendars.personal.events.store')
                    : route('groups.calendars.events.store', [$calendar->group_id, $calendar->id]),
            ])
            ->all());
    }
}
