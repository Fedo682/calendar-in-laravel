<?php

namespace App\Support\Calendar;

use App\Models\Event;
use App\Models\User;

final class IcsFeedCache
{
    public function fingerprint(User $user): string
    {
        $ids = $user->accessibleCalendarIds();

        if ($ids->isEmpty()) {
            return sha1('empty');
        }

        // Two indexed aggregates, not a hand-rolled selectRaw() - a raw
        // aliased column is a dynamic property PHPStan (rightly) can't type
        // on an Event instance, and Eloquent's own aggregate methods have
        // known return types without needing one.
        $latest = Event::query()->whereIn('calendar_id', $ids)->max('updated_at');
        $total = Event::query()->whereIn('calendar_id', $ids)->count();

        return sha1(implode('|', [
            $latest,
            $total,
            $ids->sort()->implode(','),
        ]));
    }
}
