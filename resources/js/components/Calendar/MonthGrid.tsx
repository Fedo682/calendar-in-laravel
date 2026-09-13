import type { CalendarEvent } from '@/types/calendar';

interface MonthGridProps {
    /** Any date within the month to display. */
    month: Date;
    events: CalendarEvent[];
    onDayClick?: (date: Date) => void;
    onEventClick?: (event: CalendarEvent) => void;
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
        <div className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div className="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-xs font-semibold text-gray-500">
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
                            className={`min-h-24 border-r border-b border-gray-100 p-1 text-left align-top last:border-r-0 focus:ring-2 focus:ring-indigo-500 focus:outline-none focus:ring-inset ${
                                inMonth
                                    ? 'bg-white'
                                    : 'bg-gray-50 text-gray-400'
                            }`}
                        >
                            <span
                                className={`inline-flex h-6 w-6 items-center justify-center rounded-full text-xs ${
                                    isSameDay(date, today)
                                        ? 'bg-indigo-600 font-semibold text-white'
                                        : ''
                                }`}
                            >
                                {date.getDate()}
                            </span>
                            <div className="mt-1 space-y-0.5">
                                {dayEvents.slice(0, 3).map((event) => (
                                    <div
                                        key={event.id}
                                        role="button"
                                        tabIndex={0}
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            onEventClick?.(event);
                                        }}
                                        className="truncate rounded bg-indigo-100 px-1 py-0.5 text-xs text-indigo-800 hover:bg-indigo-200"
                                    >
                                        {event.title}
                                    </div>
                                ))}
                                {dayEvents.length > 3 && (
                                    <div className="px-1 text-xs text-gray-400">
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
