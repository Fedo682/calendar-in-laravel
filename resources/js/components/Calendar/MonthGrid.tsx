import EventPill from './EventPill';
import type { GridEvent } from '@/types/calendar';

interface MonthGridProps {
    /** Any date within the month to display. */
    month: Date;
    events: GridEvent[];
    onDayClick?: (date: Date) => void;
    onEventClick?: (event: GridEvent) => void;
}

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function startOfCalendar(month: Date): Date {
    const firstOfMonth = new Date(month.getFullYear(), month.getMonth(), 1);
    const start = new Date(firstOfMonth);
    start.setDate(start.getDate() - firstOfMonth.getDay());
    return start;
}

function isSameDay(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

/**
 * Presentational 6x7 month grid. No data fetching — callers pass the events
 * for the visible range and handle navigation/create/edit via the callbacks.
 */
export default function MonthGrid({
    month,
    events,
    onDayClick,
    onEventClick,
}: MonthGridProps) {
    const gridStart = startOfCalendar(month);
    const days = Array.from({ length: 42 }, (_, i) => {
        const date = new Date(gridStart);
        date.setDate(date.getDate() + i);
        return date;
    });
    const today = new Date();

    const eventsOnDay = (date: Date) =>
        events.filter((event) => isSameDay(new Date(event.starts_at), date));

    return (
        <div className="border-hairline bg-surface rounded-card shadow-raised overflow-hidden border">
            <div className="border-hairline text-content-secondary text-caption1 grid grid-cols-7 border-b font-semibold">
                {WEEKDAY_LABELS.map((label) => (
                    <div key={label} className="px-2 py-2 text-center">
                        {label}
                    </div>
                ))}
            </div>
            <div className="grid grid-cols-7">
                {days.map((date) => {
                    const inMonth = date.getMonth() === month.getMonth();
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
