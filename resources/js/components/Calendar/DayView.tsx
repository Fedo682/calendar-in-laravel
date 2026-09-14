import EventPill from '@/components/Calendar/EventPill';
import type { GridEvent } from '@/types/calendar';
import { useEffect, useState } from 'react';

interface DayViewProps {
    date: Date;
    /** All events for the calendar - this component filters to `date` itself. */
    events: GridEvent[];
    onClose: () => void;
    onSlotClick?: (dateTime: Date) => void;
    onEventClick?: (event: GridEvent) => void;
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

/** 12 AM, 1 AM, ... 11 PM. */
function hourLabel(hour: number): string {
    if (hour === 0) return '12 AM';
    if (hour < 12) return `${hour} AM`;
    if (hour === 12) return '12 PM';

    return `${hour - 12} PM`;
}

export default function DayView({
    date,
    events,
    onClose,
    onSlotClick,
    onEventClick,
}: DayViewProps) {
    // Ticks every minute rather than freezing at the offset computed on the
    // render that opened the view - otherwise the line stops representing
    // "now" the moment it's drawn.
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const id = setInterval(() => setNow(new Date()), 60_000);

        return () => clearInterval(id);
    }, []);

    const dayEvents = events.filter((event) =>
        isSameDay(new Date(event.starts_at), date),
    );
    const allDayEvents = dayEvents.filter((event) => event.all_day);
    const timedEvents = dayEvents.filter((event) => !event.all_day);
    const positioned = layoutDayEvents(timedEvents);
    const isToday = isSameDay(date, now);
    const nowOffset = hoursSinceMidnight(now) * ROW_HEIGHT;

    return (
        <div className="border-hairline bg-surface rounded-card shadow-raised border">
            <div className="border-hairline flex items-center justify-between border-b px-4 py-3">
                <h3 className="text-headline text-content">
                    {date.toLocaleDateString(undefined, {
                        weekday: 'long',
                        month: 'long',
                        day: 'numeric',
                    })}
                </h3>
                <button
                    type="button"
                    onClick={onClose}
                    className="text-content-secondary hover:text-content text-caption1 font-medium"
                >
                    Back to month
                </button>
            </div>

            {allDayEvents.length > 0 && (
                <div className="border-hairline flex flex-wrap gap-2 border-b px-4 py-2">
                    {allDayEvents.map((event) => (
                        <EventPill
                            key={event.key}
                            event={event}
                            onClick={onEventClick}
                            subtitle="All day"
                            className="w-auto"
                        />
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
                            className="border-hairline hover:bg-surface-raised absolute right-0 left-0 flex w-full items-start border-t text-left"
                            style={{
                                top: hour * ROW_HEIGHT,
                                height: ROW_HEIGHT,
                            }}
                        >
                            <span className="text-content-tertiary text-caption2 w-14 shrink-0 -translate-y-2 pl-2">
                                {hourLabel(hour)}
                            </span>
                        </button>
                    ))}

                    {isToday && (
                        <div
                            className="border-today pointer-events-none absolute right-2 left-14 border-t-2"
                            style={{ top: nowOffset }}
                        >
                            <span className="bg-today absolute -top-1 -left-1 h-2 w-2 rounded-full" />
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
                            <div
                                key={event.key}
                                className="absolute"
                                style={{
                                    top,
                                    height,
                                    left: `calc(3.75rem + ${column * widthPct}%)`,
                                    width: `calc(${widthPct}% - ${column === columnCount - 1 ? '0.5rem' : '2px'})`,
                                }}
                            >
                                <EventPill
                                    event={event}
                                    onClick={onEventClick}
                                    subtitle={
                                        height > 32
                                            ? start.toLocaleTimeString(
                                                  undefined,
                                                  {
                                                      hour: 'numeric',
                                                      minute: '2-digit',
                                                  },
                                              )
                                            : undefined
                                    }
                                    // A conflicted block keeps its calendar's
                                    // colour - that is what tells two
                                    // different calendars apart - and gets a
                                    // ring on top, the same signal the agenda
                                    // list uses for a clash.
                                    className={
                                        conflicted
                                            ? 'ring-danger h-full ring-2'
                                            : 'h-full'
                                    }
                                />
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
