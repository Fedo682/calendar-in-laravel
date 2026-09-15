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

        $agg = Event::query()
            ->whereIn('calendar_id', $ids)
            ->selectRaw('MAX(updated_at) as latest, COUNT(*) as total')
            ->first();

        return sha1(implode('|', [
            $agg?->latest,
            $agg?->total,
            $ids->sort()->implode(','),
        ]));
    }
}
