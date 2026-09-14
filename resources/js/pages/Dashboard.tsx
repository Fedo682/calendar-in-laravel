import EventDialog from '@/components/Calendar/EventDialog';
import EventMessageDialog from '@/components/Calendar/EventMessageDialog';
import MonthGrid from '@/components/Calendar/MonthGrid';
import type { RecurrenceScope } from '@/components/Calendar/RecurrenceScopeDialog';
import RecurrenceScopeDialog from '@/components/Calendar/RecurrenceScopeDialog';
import {
    useEventForm,
    withUtcOffsets,
} from '@/components/Calendar/useEventForm';
import { Badge, Button, Card, EmptyState } from '@/components/ui';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { nowInZone, toViewerZone, useViewerZone } from '@/lib/datetime';
import type { GridEvent, Occurrence, WritableCalendar } from '@/types/calendar';
import { usePageProps } from '@/types/shared';
import { Head, router } from '@inertiajs/react';
import { TZDate } from '@date-fns/tz';
import { addDays, isSameDay } from 'date-fns';
import { CalendarDays, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

interface UpcomingEvent extends Occurrence {
    /**
     * Titles of the events this one overlaps, resolved server-side from the
     * same redacted data - so a clash with someone's private appointment
     * reads as "Busy" rather than naming it.
     */
    conflicts_with: string[];
}

interface TeamBusyGroup {
    group_id: number;
    group_name: string;
    busy_count: number;
    occurrences: Occurrence[];
}

interface DashboardProps {
    /** First of the month the grid is showing, as YYYY-MM-DD. */
    month: string;
    occurrences: Occurrence[];
    upcoming_events: UpcomingEvent[];
    window_days: number;
    writable_calendars: WritableCalendar[];
    team_busy: TeamBusyGroup[];
}

function formatDayHeading(date: Date, tz: string): string {
    const today = nowInZone(tz);
    const tomorrow = addDays(today, 1);

    if (isSameDay(date, today)) return 'Today';
    if (isSameDay(date, tomorrow)) return 'Tomorrow';

    return date.toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
    });
}

function formatTimeRange(event: Occurrence, tz: string): string {
    if (event.all_day) return 'All day';

    const opts: Intl.DateTimeFormatOptions = {
        hour: 'numeric',
        minute: '2-digit',
    };

    return `${toViewerZone(event.starts_at, tz).toLocaleTimeString(undefined, opts)} - ${toViewerZone(
        event.ends_at,
        tz,
    ).toLocaleTimeString(undefined, opts)}`;
}

/** Group the agenda by day, preserving the server's ordering. */
function groupByDay(
    events: UpcomingEvent[],
    tz: string,
): Array<{
    date: Date;
    events: UpcomingEvent[];
}> {
    const groups = new Map<string, { date: Date; events: UpcomingEvent[] }>();

    for (const event of events) {
        const date = toViewerZone(event.starts_at, tz);
        const key = date.toDateString();
        let group = groups.get(key);

        if (!group) {
            group = { date, events: [] };
            groups.set(key, group);
        }

        group.events.push(event);
    }

    return Array.from(groups.values());
}

/**
 * Where this event's write routes live.
 *
 * Group events are nested under their group; personal-calendar events have no
 * group_id at all and use the flat routes instead.
 */
function eventUrl(event: Occurrence): string {
    return event.group_id === null
        ? `/calendars/personal/events/${event.event_id}`
        : `/groups/${event.group_id}/calendars/${event.calendar_id}/events/${event.event_id}`;
}

export default function Dashboard() {
    const {
        auth,
        month,
        occurrences,
        upcoming_events: upcoming,
        window_days: windowDays,
        writable_calendars: writableCalendars,
        team_busy: teamBusy,
        viewer,
    } = usePageProps<DashboardProps>();

    const timezone = useViewerZone();
    const weekStartsOn = viewer.week_starts_on;
    // `month` is a bare "YYYY-MM-DD" calendar date, not an instant - built
    // from its own components so the numbers are interpreted as wall-clock
    // in the viewer's zone, rather than parsed as a naive string (which
    // would fall back to whatever zone the browser happens to be in).
    const [monthYear, monthNum, monthDay] = month.split('-').map(Number);
    const monthDate = new TZDate(monthYear, monthNum - 1, monthDay, timezone);
    const conflictCount = upcoming.filter(
        (e) => e.conflicts_with.length > 0,
    ).length;

    const [reportedIds, setReportedIds] = useState<number[]>([]);
    const [messagedIds, setMessagedIds] = useState<number[]>([]);
    const [messagingEvent, setMessagingEvent] = useState<UpcomingEvent | null>(
        null,
    );
    const [expandedTeams, setExpandedTeams] = useState<Set<number>>(new Set());
    const [dialogMode, setDialogMode] = useState<'create' | 'edit' | null>(
        null,
    );
    const [editing, setEditing] = useState<Occurrence | null>(null);
    const [scopePrompt, setScopePrompt] = useState<'save' | null>(null);
    const [targetCalendarId, setTargetCalendarId] = useState<number | null>(
        writableCalendars[0]?.id ?? null,
    );

    const {
        form,
        openCreate: startCreate,
        openEdit: startEdit,
        reset,
    } = useEventForm(timezone);

    /** Navigate the grid a whole month at a time. */
    const shiftMonth = (delta: number) => {
        const next = new TZDate(
            monthDate.getFullYear(),
            monthDate.getMonth() + delta,
            1,
            timezone,
        );
        const value = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`;

        router.get(
            '/dashboard',
            { month: value },
            { preserveState: true, preserveScroll: true },
        );
    };

    const goToThisMonth = () => {
        router.get('/dashboard', {}, { preserveScroll: true });
    };

    const closeDialog = () => {
        setDialogMode(null);
        setEditing(null);
        reset();
    };

    const openCreate = (date: Date) => {
        if (writableCalendars.length === 0) return;

        const target =
            writableCalendars.find((c) => c.id === targetCalendarId) ??
            writableCalendars[0];

        // A personal calendar defaults its events to private; the server
        // applies the same rule if this is omitted.
        startCreate(date, target.type === 'personal' ? 'private' : 'public');
        setTargetCalendarId(target.id);
        setEditing(null);
        setDialogMode('create');
    };

    const openEdit = (clicked: GridEvent) => {
        // The grid hands back the item it rendered; resolve it to the full
        // occurrence so the dialog has the fields the grid never needed.
        const event = occurrences.find((o) => o.key === clicked.key);

        if (!event || !event.can_edit || event.is_redacted) return;

        startEdit(event);
        setEditing(event);
        setDialogMode('edit');
    };

    /**
     * Whether editing this event needs to ask which occurrences to apply to.
     *
     * The dashboard's dialog previously had no recurrence fields at all - the
     * fields it collected were a hand copy that had drifted out of sync with
     * the other two pages. Sharing EventDialog gives it RecurrenceEditor "for
     * free", which means an edit here can now change a recurring series, so
     * it needs the same this/following/all confirmation Events/Index and the
     * personal calendar already ask - without it, saving would silently
     * apply to the whole series every time.
     */
    const needsScope =
        dialogMode === 'edit' && (editing?.is_recurring ?? false);

    const saveWithScope = (scope: RecurrenceScope) => {
        if (!editing) return;

        form.transform((data) => ({
            ...withUtcOffsets(data, timezone),
            scope,
        }));

        form.put(eventUrl(editing), {
            preserveScroll: true,
            onSuccess: () => {
                setScopePrompt(null);
                closeDialog();
            },
            onFinish: () => form.transform((data) => data),
        });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (dialogMode === 'edit' && editing) {
            if (needsScope) {
                setScopePrompt('save');

                return;
            }

            saveWithScope('all');

            return;
        }

        const target = writableCalendars.find((c) => c.id === targetCalendarId);

        if (!target) return;

        // The picker chooses which endpoint to post to rather than sending a
        // calendar id to a generic one, so the create authorises through the
        // route that owns that calendar.
        form.transform((data) => withUtcOffsets(data, timezone));

        form.post(target.create_url, {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
            onFinish: () => form.transform((data) => data),
        });
    };

    const toggleTeam = (groupId: number) => {
        setExpandedTeams((prev) => {
            const next = new Set(prev);

            if (next.has(groupId)) {
                next.delete(groupId);
            } else {
                next.add(groupId);
            }

            return next;
        });
    };

    const reportConflict = (event: UpcomingEvent) => {
        // Reporting notifies the group's admins, so it only applies to events
        // that belong to a group.
        if (event.group_id === null) return;

        router.post(
            `/groups/${event.group_id}/calendars/${event.calendar_id}/events/${event.event_id}/report-conflict`,
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    setReportedIds((ids) => [...ids, event.event_id]),
            },
        );
    };

    const days = groupByDay(upcoming, timezone);

    return (
        <>
            <Head title="Dashboard" />

            <div className="mx-auto max-w-[110rem] px-4 py-6 sm:px-6 lg:px-8">
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    {/* ---- the calendar itself ---- */}
                    <div className="min-w-0">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Previous month"
                                    onClick={() => shiftMonth(-1)}
                                >
                                    <ChevronLeft className="size-4" />
                                </Button>
                                <Button variant="ghost" onClick={goToThisMonth}>
                                    Today
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Next month"
                                    onClick={() => shiftMonth(1)}
                                >
                                    <ChevronRight className="size-4" />
                                </Button>
                                <h2 className="text-title3 text-content ms-2 font-semibold">
                                    {monthDate.toLocaleDateString(undefined, {
                                        month: 'long',
                                        year: 'numeric',
                                    })}
                                </h2>
                            </div>

                            {writableCalendars.length > 0 && (
                                <Button
                                    icon={Plus}
                                    onClick={() =>
                                        openCreate(nowInZone(timezone))
                                    }
                                >
                                    New event
                                </Button>
                            )}
                        </div>

                        <MonthGrid
                            month={monthDate}
                            events={occurrences}
                            timezone={timezone}
                            weekStartsOn={weekStartsOn}
                            onDayClick={openCreate}
                            onEventClick={openEdit}
                        />

                        <p className="text-content-tertiary text-caption1 mt-2">
                            Every calendar you can see, in one grid. Click a day
                            to add an event.
                        </p>
                    </div>

                    {/* ---- the agenda beside it ---- */}
                    <aside className="min-w-0 space-y-4">
                        <Card material="thin">
                            <h3 className="text-headline text-content">
                                Welcome back, {auth?.user?.name}
                            </h3>
                            <p className="text-content-secondary text-footnote mt-1">
                                {auth?.is_super_admin
                                    ? 'You can create groups and assign group admins.'
                                    : "Here's what's coming up."}
                            </p>
                        </Card>

                        {conflictCount > 0 && (
                            <Card material="thin" className="border-danger">
                                <p className="text-danger text-footnote">
                                    {conflictCount} upcoming{' '}
                                    {conflictCount === 1
                                        ? 'appointment overlaps'
                                        : 'appointments overlap'}{' '}
                                    with something else on your calendars.
                                </p>
                            </Card>
                        )}

                        <Card material="thin" padded={false}>
                            <h3 className="border-hairline text-headline text-content border-b px-4 py-3">
                                Next {windowDays} days
                            </h3>

                            {days.length === 0 ? (
                                <div className="px-4 py-8">
                                    <EmptyState
                                        icon={CalendarDays}
                                        title="Nothing scheduled"
                                        description={`No events in the next ${windowDays} days.`}
                                    />
                                </div>
                            ) : (
                                <div className="divide-hairline divide-y">
                                    {days.map(({ date, events }) => (
                                        <div
                                            key={date.toDateString()}
                                            className="px-4 py-3"
                                        >
                                            <h4 className="text-content-tertiary text-caption1 mb-2 font-semibold uppercase">
                                                {formatDayHeading(
                                                    date,
                                                    timezone,
                                                )}
                                            </h4>
                                            <ul className="space-y-2">
                                                {events.map((event) => (
                                                    <li key={event.key}>
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div className="min-w-0">
                                                                <div className="flex items-center gap-2">
                                                                    <span
                                                                        className="size-2 shrink-0 rounded-full"
                                                                        style={{
                                                                            backgroundColor:
                                                                                event.calendar_color ||
                                                                                'var(--event-top)',
                                                                        }}
                                                                    />
                                                                    <p className="text-content text-footnote truncate font-medium">
                                                                        {
                                                                            event.title
                                                                        }
                                                                    </p>
                                                                    {event
                                                                        .conflicts_with
                                                                        .length >
                                                                        0 && (
                                                                        <Badge tone="danger">
                                                                            Clash
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                                <p className="text-content-tertiary text-caption1 truncate">
                                                                    {formatTimeRange(
                                                                        event,
                                                                        timezone,
                                                                    )}
                                                                    {' · '}
                                                                    {event.group_name ??
                                                                        'Personal'}
                                                                </p>
                                                                {event
                                                                    .conflicts_with
                                                                    .length >
                                                                    0 && (
                                                                    <p className="text-danger text-caption1 mt-0.5">
                                                                        Overlaps:{' '}
                                                                        {event.conflicts_with.join(
                                                                            ', ',
                                                                        )}
                                                                    </p>
                                                                )}
                                                            </div>

                                                            <div className="flex shrink-0 items-center gap-2">
                                                                {event.can_edit &&
                                                                !event.is_redacted ? (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() =>
                                                                            openEdit(
                                                                                event,
                                                                            )
                                                                        }
                                                                    >
                                                                        Edit
                                                                    </Button>
                                                                ) : (
                                                                    <>
                                                                        {event.group_id !==
                                                                            null &&
                                                                            (messagedIds.includes(
                                                                                event.event_id,
                                                                            ) ? (
                                                                                <span className="text-content-tertiary text-caption1">
                                                                                    Messaged
                                                                                </span>
                                                                            ) : (
                                                                                <Button
                                                                                    variant="ghost"
                                                                                    size="sm"
                                                                                    onClick={() =>
                                                                                        setMessagingEvent(
                                                                                            event,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Message
                                                                                </Button>
                                                                            ))}
                                                                        {event
                                                                            .conflicts_with
                                                                            .length >
                                                                            0 &&
                                                                            event.group_id !==
                                                                                null &&
                                                                            (reportedIds.includes(
                                                                                event.event_id,
                                                                            ) ? (
                                                                                <span className="text-content-tertiary text-caption1">
                                                                                    Reported
                                                                                </span>
                                                                            ) : (
                                                                                <Button
                                                                                    variant="ghost"
                                                                                    size="sm"
                                                                                    onClick={() =>
                                                                                        reportConflict(
                                                                                            event,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Report
                                                                                </Button>
                                                                            ))}
                                                                    </>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Card>

                        {teamBusy.length > 0 && (
                            <Card material="thin" padded={false}>
                                <h3 className="border-hairline text-headline text-content border-b px-4 py-3">
                                    Team busy
                                </h3>
                                <div className="divide-hairline divide-y">
                                    {teamBusy.map((team) => {
                                        const expanded = expandedTeams.has(
                                            team.group_id,
                                        );

                                        return (
                                            <div key={team.group_id}>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        toggleTeam(
                                                            team.group_id,
                                                        )
                                                    }
                                                    className="hover:bg-surface-raised flex w-full items-center justify-between px-4 py-3 text-start"
                                                >
                                                    <span className="text-content text-footnote font-medium">
                                                        {team.group_name} -{' '}
                                                        {team.busy_count} busy
                                                    </span>
                                                    <ChevronRight
                                                        className={`text-content-tertiary size-4 shrink-0 transition-transform ${
                                                            expanded
                                                                ? 'rotate-90'
                                                                : ''
                                                        }`}
                                                    />
                                                </button>
                                                {expanded && (
                                                    <ul className="space-y-2 px-4 pb-3">
                                                        {team.occurrences
                                                            .length === 0 ? (
                                                            <li className="text-content-tertiary text-caption1">
                                                                Nothing
                                                                scheduled.
                                                            </li>
                                                        ) : (
                                                            team.occurrences.map(
                                                                (
                                                                    occurrence,
                                                                ) => (
                                                                    <li
                                                                        key={
                                                                            occurrence.key
                                                                        }
                                                                    >
                                                                        <p className="text-content text-footnote">
                                                                            Busy
                                                                            {occurrence.owner_name
                                                                                ? ` - ${occurrence.owner_name}`
                                                                                : ''}
                                                                        </p>
                                                                        <p className="text-content-tertiary text-caption1">
                                                                            {formatTimeRange(
                                                                                occurrence,
                                                                                timezone,
                                                                            )}
                                                                        </p>
                                                                    </li>
                                                                ),
                                                            )
                                                        )}
                                                    </ul>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </Card>
                        )}
                    </aside>
                </div>
            </div>

            <EventDialog
                open={dialogMode !== null}
                mode={dialogMode ?? 'create'}
                form={form}
                calendars={
                    dialogMode === 'create' ? writableCalendars : undefined
                }
                targetCalendarId={targetCalendarId}
                onTargetChange={(id) => {
                    setTargetCalendarId(id);

                    const next = writableCalendars.find((c) => c.id === id);

                    if (next) {
                        form.setData(
                            'visibility',
                            next.type === 'personal' ? 'private' : 'public',
                        );
                    }
                }}
                onSubmit={submit}
                onClose={closeDialog}
            />

            <RecurrenceScopeDialog
                open={scopePrompt !== null}
                action="save"
                processing={form.processing}
                onCancel={() => setScopePrompt(null)}
                onConfirm={(scope) => saveWithScope(scope)}
            />

            {messagingEvent && (
                <EventMessageDialog
                    open={messagingEvent !== null}
                    onClose={() => setMessagingEvent(null)}
                    onSent={() =>
                        setMessagedIds((ids) => [
                            ...ids,
                            messagingEvent.event_id,
                        ])
                    }
                    groupId={messagingEvent.group_id!}
                    calendarId={messagingEvent.calendar_id}
                    eventId={messagingEvent.event_id}
                    occurrenceStart={messagingEvent.starts_at}
                />
            )}
        </>
    );
}

Dashboard.layout = (page: ReactNode) => (
    <AuthenticatedLayout
        header={
            <h2 className="text-headline text-chrome-content">Dashboard</h2>
        }
    >
        {page}
    </AuthenticatedLayout>
);
