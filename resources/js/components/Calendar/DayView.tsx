import type { CalendarEvent, GridEvent } from '@/types/calendar';

interface DayViewProps {
    date: Date;
    /** All events for the calendar - this component filters to `date` itself. */
    events: GridEvent[];
    onClose: () => void;
    onSlotClick?: (dateTime: Date) => void;
    onEventClick?: (event: CalendarEvent) => void;
}

interface PositionedEvent {
    event: GridEvent;
    column: number;
    columnCount: number;
}

const ROW_HEIGHT = 48; // px per hour
const HOURS = Array.from({ length: 24 }, (_, i) => i);

function isSameDay(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

function hoursSinceMidnight(date: Date): number {
    return date.getHours() + date.getMinutes() / 60;
}

/**
 * Groups events into time-connected clusters, then greedily assigns each
 * event in a cluster to the first column whose previous event has already
 * ended. Events that don't overlap anything get columnCount=1 (full width);
 * events that do overlap end up side by side, sharing the row width evenly
 * with only the events they actually conflict with.
 */
function layoutDayEvents(events: GridEvent[]): PositionedEvent[] {
    const sorted = [...events].sort(
        (a, b) =>
            new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime(),
    );

    const positioned: PositionedEvent[] = [];
    let cluster: GridEvent[] = [];
    let clusterEnd = -Infinity;

    const flushCluster = () => {
        if (cluster.length === 0) return;

        const columns: GridEvent[][] = [];
        for (const event of cluster) {
            const start = new Date(event.starts_at).getTime();
            let placedInColumn = columns.find(
                (col) =>
                    new Date(col[col.length - 1].ends_at).getTime() <= start,
            );
            if (!placedInColumn) {
                placedInColumn = [];
                columns.push(placedInColumn);
            }
            placedInColumn.push(event);
        }

        const columnCount = columns.length;
        columns.forEach((col, column) => {
            col.forEach((event) =>
                positioned.push({ event, column, columnCount }),
            );
        });

        cluster = [];
    };

    for (const event of sorted) {
        const start = new Date(event.starts_at).getTime();
        const end = new Date(event.ends_at).getTime();

        if (cluster.length > 0 && start >= clusterEnd) {
            flushCluster();
            clusterEnd = -Infinity;
        }

        cluster.push(event);
        clusterEnd = Math.max(clusterEnd, end);
    }
    flushCluster();

    return positioned;
}

export default function DayView({
    date,
    events,
    onClose,
    onSlotClick,
    onEventClick,
}: DayViewProps) {
    const dayEvents = events.filter((event) =>
        isSameDay(new Date(event.starts_at), date),
    );
    const allDayEvents = dayEvents.filter((event) => event.all_day);
    const timedEvents = dayEvents.filter((event) => !event.all_day);
    const positioned = layoutDayEvents(timedEvents);
    const today = new Date();
    const isToday = isSameDay(date, today);
    const nowOffset = hoursSinceMidnight(today) * ROW_HEIGHT;

    return (
        <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <h3 className="text-sm font-semibold text-gray-900">
                    {date.toLocaleDateString(undefined, {
                        weekday: 'long',
                        month: 'long',
                        day: 'numeric',
                    })}
                </h3>
                <button
                    type="button"
                    onClick={onClose}
                    className="text-xs font-medium text-gray-500 hover:text-gray-800"
                >
                    Back to month
                </button>
            </div>

            {allDayEvents.length > 0 && (
                <div className="flex flex-wrap gap-2 border-b border-gray-100 px-4 py-2">
                    {allDayEvents.map((event) => (
                        <button
                            key={event.key}
                            type="button"
                            onClick={() => onEventClick?.(event)}
                            className="rounded bg-indigo-100 px-2 py-1 text-xs font-medium text-indigo-800 hover:bg-indigo-200"
                        >
                            {event.title} · All day
                        </button>
                    ))}
                </div>
            )}

            <div className="relative max-h-[32rem] overflow-y-auto">
                <div className="relative" style={{ height: 24 * ROW_HEIGHT }}>
                    {HOURS.map((hour) => (
                        <button
                            key={hour}
                            type="button"
                            onClick={() => {
                                const slot = new Date(date);
                                slot.setHours(hour, 0, 0, 0);
                                onSlotClick?.(slot);
                            }}
                            className="absolute right-0 left-0 flex w-full items-start border-t border-gray-100 text-left hover:bg-gray-50"
                            style={{
                                top: hour * ROW_HEIGHT,
                                height: ROW_HEIGHT,
                            }}
                        >
                            <span className="w-14 shrink-0 -translate-y-2 pl-2 text-[11px] text-gray-400">
                                {hour === 0
                                    ? '12 AM'
                                    : hour < 12
                                      ? `${hour} AM`
                                      : hour === 12
                                        ? '12 PM'
                                        : `${hour - 12} PM`}
                            </span>
                        </button>
                    ))}

                    {isToday && (
                        <div
                            className="pointer-events-none absolute right-2 left-14 border-t-2 border-red-400"
                            style={{ top: nowOffset }}
                        >
                            <span className="absolute -top-1 -left-1 h-2 w-2 rounded-full bg-red-400" />
                        </div>
                    )}

                    {positioned.map(({ event, column, columnCount }) => {
                        const start = new Date(event.starts_at);
                        const end = new Date(event.ends_at);
                        const top = hoursSinceMidnight(start) * ROW_HEIGHT;
                        const height = Math.max(
                            20,
                            (hoursSinceMidnight(end) -
                                hoursSinceMidnight(start)) *
                                ROW_HEIGHT,
                        );
                        const widthPct = 100 / columnCount;
                        const conflicted = columnCount > 1;

                        return (
                            <button
                                key={event.key}
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onEventClick?.(event);
                                }}
                                className={`absolute overflow-hidden rounded px-1.5 py-0.5 text-left text-xs shadow-sm ${
                                    conflicted
                                        ? 'border border-red-300 bg-red-100 text-red-800 hover:bg-red-200'
                                        : 'border border-indigo-200 bg-indigo-100 text-indigo-800 hover:bg-indigo-200'
                                }`}
                                style={{
                                    top,
                                    height,
                                    left: `calc(3.75rem + ${column * widthPct}%)`,
                                    width: `calc(${widthPct}% - ${column === columnCount - 1 ? '0.5rem' : '2px'})`,
                                }}
                            >
                                <span className="block truncate font-medium">
                                    {event.title}
                                </span>
                                {height > 32 && (
                                    <span className="block truncate text-[10px] opacity-80">
                                        {start.toLocaleTimeString(undefined, {
                                            hour: 'numeric',
                                            minute: '2-digit',
                                        })}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
