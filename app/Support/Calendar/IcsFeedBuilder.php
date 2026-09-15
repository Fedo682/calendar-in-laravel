<?php

namespace App\Support\Calendar;

use App\Models\User;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;

/**
 * Turns this viewer's redacted, unexpanded events into a serialized
 * VCALENDAR. A recurring series stays one VEVENT carrying its RRULE - an
 * infinite weekly standup is one line, not hundreds of expanded copies.
 */
final class IcsFeedBuilder
{
    public function __construct(
        private readonly OccurrenceQuery $occurrences,
    ) {}

    public function build(User $user): string
    {
        $from = now()->subMonths((int) config('calendar.ics.past_months'));
        $to = now()->addMonths((int) config('calendar.ics.future_months'));

        $redacted = $this->occurrences->mastersFor($user, $from, $to);

        // PRODID is one of VCalendar's own constructor defaults (alongside
        // VERSION and CALSCALE) - passed as the constructor's own $children
        // array, it overrides that default instead of duplicating it the
        // way a later $calendar->add('PRODID', ...) call would (add() only
        // ever appends a new property, never replaces an existing one).
        $calendar = new VCalendar(['PRODID' => '-//GroupSync Calendar//ICS Feed//EN']);
        $calendar->add('X-WR-CALNAME', $user->name.' - GroupSync Calendar');
        $calendar->add('X-PUBLISHED-TTL', (string) config('calendar.ics.refresh_interval'));

        foreach ($redacted as $event) {
            $this->addVEvent($calendar, $event);
        }

        return $calendar->serialize();
    }

    private function addVEvent(VCalendar $calendar, RedactedEvent $event): void
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        // Component::add() is untyped - it returns whatever Node it was
        // handed or built, which PHPStan can only ever see as the generic
        // base Node type. Constructing the VEvent directly (against this
        // VCalendar as its root, so it still serializes correctly) keeps a
        // concrete, statically-known VEvent instead.
        $vevent = new VEvent($calendar, 'VEVENT', [
            'UID' => $event->uid($host),
            'SUMMARY' => $event->title,
            'DTSTAMP' => new \DateTime('now', new \DateTimeZone('UTC')),
        ]);
        $calendar->add($vevent);

        $this->setDates($vevent, $event);

        if ($event->description !== null) {
            $vevent->add('DESCRIPTION', $event->description);
        }
        if ($event->location !== null) {
            $vevent->add('LOCATION', $event->location);
        }

        if ($event->isRecurring()) {
            $vevent->add('RRULE', $event->recurrenceRule);

            foreach ($event->recurrenceExdates as $exdate) {
                $vevent->add('EXDATE', $exdate);
            }
        }

        if ($event->recurrenceInstanceId !== null) {
            $vevent->add('RECURRENCE-ID', $event->recurrenceInstanceId->format('Ymd\THis\Z'));
        }

        [$class, $transp] = $event->isRedacted
            ? ['CONFIDENTIAL', 'OPAQUE']
            : ['PUBLIC', 'OPAQUE'];
        $vevent->add('CLASS', $class);
        $vevent->add('TRANSP', $transp);
    }

    private function setDates(VEvent $vevent, RedactedEvent $event): void
    {
        if ($event->allDay) {
            $vevent->add('DTSTART', $event->startsAt->format('Ymd'), ['VALUE' => 'DATE']);
            // Exclusive: the day after the event's own last day, not its
            // last day itself - the single most common ICS off-by-one.
            $vevent->add('DTEND', $event->endsAt->copy()->addDay()->format('Ymd'), ['VALUE' => 'DATE']);

            return;
        }

        // A recurring master is TZID-anchored so its wall-clock time
        // survives a DST transition; a one-off or an override has no
        // repetition to protect and goes out as an unambiguous UTC instant.
        if ($event->isRecurring() && $event->recurrenceParentId === null) {
            $vevent->add('DTSTART', $event->startsAt->setTimezone($event->recurrenceTimezone)->format('Ymd\THis'), [
                'TZID' => $event->recurrenceTimezone,
            ]);
            $vevent->add('DTEND', $event->endsAt->setTimezone($event->recurrenceTimezone)->format('Ymd\THis'), [
                'TZID' => $event->recurrenceTimezone,
            ]);

            return;
        }

        $vevent->add('DTSTART', $event->startsAt->format('Ymd\THis\Z'));
        $vevent->add('DTEND', $event->endsAt->format('Ymd\THis\Z'));
    }
}
