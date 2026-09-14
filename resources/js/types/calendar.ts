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
    description: string | null;
    color: string | null;
    /**
     * Null for a personal calendar, which belongs to one user rather than to
     * a group. Anything rendering a calendar has to handle that - a personal
     * calendar has no group to name and no group-nested route to link to.
     */
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

/**
 * What the month grid and day view actually need.
 *
 * `id` is not unique once recurrence exists - one stored row yields many
 * occurrences sharing it - so anything rendering a list of these must key on
 * `key`, which Occurrence guarantees is distinct per instance.
 */
export interface GridEvent extends CalendarEvent {
    key: string;
    /**
     * The owning calendar's colour, which is what the event badge's gradient
     * is derived from. Null when the calendar has none, in which case the
     * badge falls back to the theme's default event colour.
     */
    calendar_color?: string | null;
    /** Redacted events render as a flat, colourless busy block. */
    is_redacted?: boolean;
}

/**
 * One dated instance of an event, already redacted for the current viewer.
 *
 * This is the only event shape the server sends to a page. Where `is_redacted`
 * is true the title is a stand-in and description/location are null, because
 * the viewer is not entitled to them - there is no second, fuller copy to
 * reach for.
 *
 * `id` satisfies CalendarEvent for the grid components and carries the event
 * row id; `key` is what React should key on, since one recurring event will
 * later produce many occurrences sharing that id.
 */
export interface Occurrence extends CalendarEvent {
    key: string;
    event_id: number;
    calendar_id: number;
    /** Null for events on a personal calendar, which belongs to no group. */
    group_id: number | null;
    description: string | null;
    location: string | null;
    visibility: Visibility;
    is_redacted: boolean;
    can_edit: boolean;
    recurrence_id: string | null;
    /** The series' rule, or null on a one-off. */
    recurrence_rule: string | null;
    /** IANA zone the rule is anchored to; set whenever recurrence_rule is. */
    recurrence_timezone: string | null;
    is_recurring: boolean;
    calendar_name: string;
    calendar_color: string | null;
    /** Null for events on a personal calendar. */
    group_name: string | null;
}

/**
 * One calendar the viewer may create an event on, as sent by
 * WritableCalendars on the server.
 */
export interface WritableCalendar {
    id: number;
    name: string;
    color: string | null;
    type: string;
    group_id: number | null;
    group_name: string | null;
    /** Which existing endpoint a create posts to. */
    create_url: string;
}
