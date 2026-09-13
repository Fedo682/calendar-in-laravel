/**
 * Canonical calendar-domain shapes.
 *
 * These were previously redeclared, with drifting field sets, inside five
 * separate page components. Anything server-rendered into an Inertia page
 * should widen one of these rather than start a sixth copy.
 */

/** How much of an event a non-member is allowed to see. */
export type Visibility = 'public' | 'private' | 'busy';

export interface GroupSummary {
    id: number;
    name: string;
    description: string | null;
}

export interface CalendarSummary {
    id: number;
    name: string;
    color: string | null;
    group: GroupSummary | null;
}

/**
 * The minimum an event needs for the month grid and day view to render it.
 * `starts_at`/`ends_at` are ISO-8601 strings as serialised by Laravel.
 */
export interface CalendarEvent {
    id: number;
    title: string;
    starts_at: string;
    ends_at: string;
    all_day: boolean;
}
