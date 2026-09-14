import { usePageProps } from '@/types/shared';
import { TZDate } from '@date-fns/tz';
import {
    addDays,
    endOfMonth,
    endOfWeek,
    format,
    isSameDay,
    startOfMonth,
    startOfWeek,
} from 'date-fns';

/** 0 = Sunday ... 6 = Saturday, matching the `viewer.week_starts_on` prop. */
export type WeekStartsOn = 0 | 1 | 2 | 3 | 4 | 5 | 6;

/** Anything the server or the calendar components hand us for an instant. */
export type DateInput = Date | string | number;

/**
 * Reinterpret an instant in the viewer's IANA timezone.
 *
 * The returned `TZDate` is a real `Date`, so every date-fns function and the
 * `getHours()`/`getDate()` accessors read out in `tz` rather than in whatever
 * timezone the browser happens to be set to.
 */
export function toViewerZone(iso: string, tz: string): TZDate {
    return new TZDate(iso, tz);
}

/**
 * The current instant, reinterpreted in the viewer's zone - for "is this
 * today" and "where does the now-line go" checks, which must not use the
 * browser's own zone once it can differ from the viewer's chosen one.
 */
export function nowInZone(tz: string): TZDate {
    return new TZDate(Date.now(), tz);
}

/**
 * The viewer's IANA timezone, from the props every page already receives.
 *
 * Presentational calendar components (`MonthGrid`, `DayView`) take the zone
 * as a prop instead of calling this themselves, so they stay testable
 * without an Inertia page context - this is what the page components call.
 */
export function useViewerZone(): string {
    const { viewer } = usePageProps();

    return viewer.timezone;
}

/**
 * A naive `"YYYY-MM-DDTHH:mm"` value - exactly what a
 * `<input type="datetime-local">` produces and binds to, which has no
 * concept of timezone at all - reinterpreted as wall-clock time in `tz` and
 * rendered as a real, offset-bearing instant.
 *
 * This is what makes the write path viewer-zone-correct: the backend's
 * `datetime` cast parses a bare "2026-09-20T09:00" as 9am in the app's own
 * configured timezone (UTC), not the viewer's. An offset-bearing string
 * (`2026-09-20T09:00:00.000+13:00`) is unambiguous regardless of the app's
 * default, so this must be applied right before a create/update request is
 * sent - never to the value the input itself displays, which must stay
 * naive or the browser won't parse it back.
 */
export function localInputValueToUtcIso(value: string, tz: string): string {
    const [datePart, timePart] = value.split('T');
    const [year, month, day] = datePart.split('-').map(Number);
    const [hour, minute] = timePart.split(':').map(Number);

    return new TZDate(year, month - 1, day, hour, minute, 0, tz).toISOString();
}

function asDate(value: DateInput, tz?: string): Date {
    const date = value instanceof Date ? value : new Date(value);

    return tz === undefined ? date : new TZDate(date, tz);
}

/**
 * Clock time only, e.g. `9:30 AM` or `09:30`.
 *
 * @param tz - Render in this timezone instead of the browser's.
 */
export function formatTime(
    value: DateInput,
    { tz, hour12 = true }: { tz?: string; hour12?: boolean } = {},
): string {
    return format(asDate(value, tz), hour12 ? 'h:mm a' : 'HH:mm');
}

/**
 * Day heading, e.g. `Today`, `Tomorrow`, or `Monday, Mar 4`.
 *
 * @param relativeTo - The instant "today" is measured from. Defaults to now,
 *   and is injectable so callers (and tests) are not at the mercy of the clock.
 */
export function formatDayLabel(
    value: DateInput,
    {
        tz,
        relativeTo = new Date(),
    }: { tz?: string; relativeTo?: DateInput } = {},
): string {
    const date = asDate(value, tz);
    const today = asDate(relativeTo, tz);

    if (isSameDay(date, today)) {
        return 'Today';
    }

    if (isSameDay(date, addDays(today, 1))) {
        return 'Tomorrow';
    }

    return format(date, 'EEEE, MMM d');
}

/**
 * The inclusive first/last day of the 6x7 grid that displays `month`: the
 * month padded out to whole weeks under the viewer's week-start preference.
 */
export function monthGridRange(
    month: DateInput,
    weekStartsOn: WeekStartsOn = 0,
    { tz }: { tz?: string } = {},
): { start: Date; end: Date } {
    const date = asDate(month, tz);

    return {
        start: startOfWeek(startOfMonth(date), { weekStartsOn }),
        end: endOfWeek(endOfMonth(date), { weekStartsOn }),
    };
}

/**
 * Half-open overlap test: two intervals that merely touch (one ends exactly
 * when the other starts) do not conflict. Matches the server-side conflict
 * query in `DashboardController`.
 */
export function overlaps(
    aStart: DateInput,
    aEnd: DateInput,
    bStart: DateInput,
    bEnd: DateInput,
): boolean {
    return (
        asDate(aStart).getTime() < asDate(bEnd).getTime() &&
        asDate(aEnd).getTime() > asDate(bStart).getTime()
    );
}
