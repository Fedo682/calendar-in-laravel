import MonthGrid from '@/components/Calendar/MonthGrid';
import {
    Badge,
    Button,
    Card,
    Checkbox,
    EmptyState,
    Field,
    Input,
    Modal,
    Select,
    Textarea,
} from '@/components/ui';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { GridEvent, Occurrence, Visibility } from '@/types/calendar';
import { usePageProps } from '@/types/shared';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

/** One calendar the viewer may create an event on. */
interface WritableCalendar {
    id: number;
    name: string;
    color: string | null;
    type: string;
    group_id: number | null;
    group_name: string | null;
    /** Which existing endpoint a create posts to. */
    create_url: string;
}

interface UpcomingEvent extends Occurrence {
    /**
     * Titles of the events this one overlaps, resolved server-side from the
     * same redacted data - so a clash with someone's private appointment
     * reads as "Busy" rather than naming it.
     */
    conflicts_with: string[];
}

interface DashboardProps {
    /** First of the month the grid is showing, as YYYY-MM-DD. */
    month: string;
    occurrences: Occurrence[];
    upcoming_events: UpcomingEvent[];
    window_days: number;
    writable_calendars: WritableCalendar[];
}

interface EventForm {
    title: string;
    description: string;
    location: string;
    all_day: boolean;
    starts_at: string;
    ends_at: string;
    visibility: Visibility;
}

/** Format a Date as the value expected by <input type="datetime-local">. */
function toLocalInputValue(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function formatDayHeading(date: Date): string {
    const today = new Date();
    const tomorrow = new Date();
    tomorrow.setDate(today.getDate() + 1);

    const sameDay = (a: Date, b: Date) =>
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate();

    if (sameDay(date, today)) return 'Today';
    if (sameDay(date, tomorrow)) return 'Tomorrow';

    return date.toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
    });
}

function formatTimeRange(event: Occurrence): string {
    if (event.all_day) return 'All day';

    const opts: Intl.DateTimeFormatOptions = {
        hour: 'numeric',
        minute: '2-digit',
    };

    return `${new Date(event.starts_at).toLocaleTimeString(undefined, opts)} - ${new Date(
        event.ends_at,
    ).toLocaleTimeString(undefined, opts)}`;
}

/** Group the agenda by day, preserving the server's ordering. */
function groupByDay(events: UpcomingEvent[]): Array<{
    date: Date;
    events: UpcomingEvent[];
}> {
    const groups = new Map<string, { date: Date; events: UpcomingEvent[] }>();

    for (const event of events) {
        const date = new Date(event.starts_at);
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
    } = usePageProps<DashboardProps>();

    const monthDate = new Date(`${month}T00:00:00`);
    const conflictCount = upcoming.filter(
        (e) => e.conflicts_with.length > 0,
    ).length;

    const [reportedIds, setReportedIds] = useState<number[]>([]);
    const [editing, setEditing] = useState<Occurrence | null>(null);
    const [creating, setCreating] = useState(false);
    const [targetCalendarId, setTargetCalendarId] = useState<number | null>(
        writableCalendars[0]?.id ?? null,
    );

    const form = useForm<EventForm>({
        title: '',
        description: '',
        location: '',
        all_day: false,
        starts_at: '',
        ends_at: '',
        visibility: 'public',
    });

    /** Navigate the grid a whole month at a time. */
    const shiftMonth = (delta: number) => {
        const next = new Date(
            monthDate.getFullYear(),
            monthDate.getMonth() + delta,
            1,
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

    const openCreate = (date: Date) => {
        if (writableCalendars.length === 0) return;

        const start = new Date(date);

        // A month-grid click carries midnight; default that to a working hour
        // rather than scheduling something for 00:00.
        if (start.getHours() === 0 && start.getMinutes() === 0) {
            start.setHours(9, 0, 0, 0);
        }

        const end = new Date(start);
        end.setHours(start.getHours() + 1);

        const target =
            writableCalendars.find((c) => c.id === targetCalendarId) ??
            writableCalendars[0];

        form.setData({
            title: '',
            description: '',
            location: '',
            all_day: false,
            starts_at: toLocalInputValue(start),
            ends_at: toLocalInputValue(end),
            // A personal calendar defaults its events to private; the server
            // applies the same rule if this is omitted.
            visibility: target.type === 'personal' ? 'private' : 'public',
        });
        setTargetCalendarId(target.id);
        form.clearErrors();
        setCreating(true);
    };

    const submitCreate = (e: FormEvent) => {
        e.preventDefault();

        const target = writableCalendars.find((c) => c.id === targetCalendarId);

        if (!target) return;

        // The picker chooses which endpoint to post to rather than sending a
        // calendar id to a generic one, so the create authorises through the
        // route that owns that calendar.
        form.post(target.create_url, {
            preserveScroll: true,
            onSuccess: () => setCreating(false),
        });
    };

    const openEdit = (clicked: GridEvent) => {
        // The grid hands back the item it rendered; resolve it to the full
        // occurrence so the dialog has the fields the grid never needed.
        const event = occurrences.find((o) => o.key === clicked.key);

        if (!event || !event.can_edit || event.is_redacted) return;

        setEditing(event);
        form.setData({
            title: event.title,
            description: event.description ?? '',
            location: event.location ?? '',
            all_day: event.all_day,
            starts_at: toLocalInputValue(new Date(event.starts_at)),
            ends_at: toLocalInputValue(new Date(event.ends_at)),
            visibility: event.visibility,
        });
        form.clearErrors();
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();

        if (!editing) return;

        form.put(eventUrl(editing), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
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

    const days = groupByDay(upcoming);
    const selectedCalendar = writableCalendars.find(
        (c) => c.id === targetCalendarId,
    );

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
                                    onClick={() => openCreate(new Date())}
                                >
                                    New event
                                </Button>
                            )}
                        </div>

                        <MonthGrid
                            month={monthDate}
                            events={occurrences}
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
                                                {formatDayHeading(date)}
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

                                                            <div className="shrink-0">
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
                                                                    event
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
                                                                    ))
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
                    </aside>
                </div>
            </div>

            {/* ---- create ---- */}
            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title="New event"
                width="lg"
            >
                <form onSubmit={submitCreate} className="space-y-4">
                    <Field
                        label="Calendar"
                        hint={
                            selectedCalendar?.type === 'personal'
                                ? 'Events here are private by default - others see only that you are busy.'
                                : undefined
                        }
                    >
                        <Select
                            value={targetCalendarId ?? ''}
                            onChange={(e) => {
                                const id = Number(e.target.value);
                                setTargetCalendarId(id);

                                const next = writableCalendars.find(
                                    (c) => c.id === id,
                                );

                                if (next) {
                                    form.setData(
                                        'visibility',
                                        next.type === 'personal'
                                            ? 'private'
                                            : 'public',
                                    );
                                }
                            }}
                        >
                            {writableCalendars.map((calendar) => (
                                <option key={calendar.id} value={calendar.id}>
                                    {calendar.group_name
                                        ? `${calendar.group_name} - ${calendar.name}`
                                        : calendar.name}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <EventFields form={form} />

                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            variant="secondary"
                            type="button"
                            onClick={() => setCreating(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            Create
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* ---- edit ---- */}
            <Modal
                open={editing !== null}
                onClose={() => setEditing(null)}
                title="Edit event"
                width="lg"
            >
                <form onSubmit={submitEdit} className="space-y-4">
                    <EventFields form={form} />

                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            variant="secondary"
                            type="button"
                            onClick={() => setEditing(null)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            Save
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

/** The fields both dialogs share. */
function EventFields({
    form,
}: {
    form: ReturnType<typeof useForm<EventForm>>;
}) {
    return (
        <>
            <Field label="Title" error={form.errors.title} required>
                <Input
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                    invalid={Boolean(form.errors.title)}
                    required
                />
            </Field>

            <Field label="Description">
                <Textarea
                    rows={2}
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                />
            </Field>

            <Field label="Location">
                <Input
                    value={form.data.location}
                    onChange={(e) => form.setData('location', e.target.value)}
                />
            </Field>

            <Checkbox
                checked={form.data.all_day}
                onChange={(e) => form.setData('all_day', e.target.checked)}
                label="All day"
            />

            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Starts at" error={form.errors.starts_at} required>
                    <Input
                        type="datetime-local"
                        value={form.data.starts_at}
                        onChange={(e) =>
                            form.setData('starts_at', e.target.value)
                        }
                        invalid={Boolean(form.errors.starts_at)}
                        required
                    />
                </Field>
                <Field label="Ends at" error={form.errors.ends_at} required>
                    <Input
                        type="datetime-local"
                        value={form.data.ends_at}
                        onChange={(e) =>
                            form.setData('ends_at', e.target.value)
                        }
                        invalid={Boolean(form.errors.ends_at)}
                        required
                    />
                </Field>
            </div>

            <Field label="Visibility" error={form.errors.visibility}>
                <Select
                    value={form.data.visibility}
                    onChange={(e) =>
                        form.setData('visibility', e.target.value as Visibility)
                    }
                >
                    <option value="public">
                        Public - everyone on this calendar sees the details
                    </option>
                    <option value="private">
                        Private - others see only that you are busy
                    </option>
                    <option value="busy">
                        Busy - details hidden from everyone
                    </option>
                </Select>
            </Field>
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
