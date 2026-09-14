import EventPill from './EventPill';
import type { GridEvent } from '@/types/calendar';
import {
    monthGridRange,
    nowInZone,
    toViewerZone,
    type WeekStartsOn,
} from '@/lib/datetime';
import { TZDate } from '@date-fns/tz';
import { addDays, isSameDay } from 'date-fns';

interface MonthGridProps {
    /** Any date within the month to display. */
    month: Date;
    events: GridEvent[];
    /** The viewer's IANA zone - every day/hour boundary is drawn in this zone. */
    timezone: string;
    weekStartsOn: WeekStartsOn;
    onDayClick?: (date: Date) => void;
    onEventClick?: (event: GridEvent) => void;
}

const BASE_WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** Rotated so the first label matches the viewer's week-start preference. */
function weekdayLabels(weekStartsOn: WeekStartsOn): string[] {
    return [
        ...BASE_WEEKDAY_LABELS.slice(weekStartsOn),
        ...BASE_WEEKDAY_LABELS.slice(0, weekStartsOn),
    ];
}

/**
 * Presentational 6x7 month grid. No data fetching — callers pass the events
 * for the visible range and handle navigation/create/edit via the callbacks.
 */
export default function MonthGrid({
    month,
    events,
    timezone,
    weekStartsOn,
    onDayClick,
    onEventClick,
}: MonthGridProps) {
    const { start: gridStart } = monthGridRange(month, weekStartsOn, {
        tz: timezone,
    });
    const days = Array.from({ length: 42 }, (_, i) => addDays(gridStart, i));
    const today = nowInZone(timezone);
    // Reinterpreted in the same zone as `days`, so "is this cell in the
    // displayed month" compares two dates that agree on what day it is.
    const monthInZone = new TZDate(month, timezone);

    const eventsOnDay = (date: Date) =>
        events.filter((event) =>
            isSameDay(toViewerZone(event.starts_at, timezone), date),
        );

    return (
        <div className="border-hairline bg-surface rounded-card shadow-raised overflow-hidden border">
            <div className="border-hairline text-content-secondary text-caption1 grid grid-cols-7 border-b font-semibold">
                {weekdayLabels(weekStartsOn).map((label) => (
                    <div key={label} className="px-2 py-2 text-center">
                        {label}
                    </div>
                ))}
            </div>
            <div className="grid grid-cols-7">
                {days.map((date) => {
                    const inMonth = date.getMonth() === monthInZone.getMonth();
                    const dayEvents = eventsOnDay(date);

                    return (
                        <button
                            key={date.toISOString()}
                            type="button"
                            onClick={() => onDayClick?.(date)}
                            className={`border-hairline focus:ring-accent min-h-24 border-r border-b p-1 text-left align-top last:border-r-0 focus:ring-2 focus:outline-none focus:ring-inset ${
                                inMonth
                                    ? 'bg-surface'
                                    : 'bg-canvas text-content-tertiary'
                            }`}
                        >
                            <span
                                className={`text-caption1 inline-flex h-6 w-6 items-center justify-center rounded-full ${
                                    isSameDay(date, today)
                                        ? 'bg-today text-today-content font-semibold'
                                        : ''
                                }`}
                            >
                                {date.getDate()}
                            </span>
                            <div className="mt-1 space-y-0.5">
                                {dayEvents.slice(0, 3).map((event) => (
                                    <EventPill
                                        key={event.key}
                                        event={event}
                                        onClick={onEventClick}
                                    />
                                ))}
                                {dayEvents.length > 3 && (
                                    <div className="text-content-tertiary text-caption1 px-1">
                                        +{dayEvents.length - 3} more
                                    </div>
                                )}
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
