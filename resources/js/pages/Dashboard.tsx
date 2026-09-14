import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { Occurrence } from '@/types/calendar';
import { usePageProps } from '@/types/shared';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

/**
 * One row of `DashboardController@index`'s `upcoming_events` payload.
 *
 * Every field is already redacted for the signed-in viewer; where
 * `is_redacted` is true the title is a stand-in and there is no fuller copy
 * to fall back on.
 */
interface UpcomingEvent extends Occurrence {
    /**
     * Titles of the events this one overlaps, resolved server-side from the
     * same redacted data - so a clash with someone's private appointment
     * reads as "Busy" rather than naming it.
     */
    conflicts_with: string[];
}

interface DashboardProps {
    upcoming_events: UpcomingEvent[];
    window_days: number;
}

interface EditEventForm {
    title: string;
    description: string;
    location: string;
    all_day: boolean;
    starts_at: string;
    ends_at: string;
}

interface DayGroup {
    date: Date;
    events: UpcomingEvent[];
}

function formatDayHeading(date: Date): string {
    const today = new Date();
    const tomorrow = new Date();
    tomorrow.setDate(today.getDate() + 1);

    const isSameDay = (a: Date, b: Date) =>
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate();

    if (isSameDay(date, today)) return 'Today';
    if (isSameDay(date, tomorrow)) return 'Tomorrow';

    return date.toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
    });
}

function formatTimeRange(event: UpcomingEvent): string {
    if (event.all_day) return 'All day';

    const opts: Intl.DateTimeFormatOptions = {
        hour: 'numeric',
        minute: '2-digit',
    };
    const start = new Date(event.starts_at).toLocaleTimeString(undefined, opts);
    const end = new Date(event.ends_at).toLocaleTimeString(undefined, opts);
    return `${start} – ${end}`;
}

function groupByDay(events: UpcomingEvent[]): DayGroup[] {
    const groups = new Map<string, DayGroup>();

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

/** Format a Date as the value expected by <input type="datetime-local">. */
function toLocalInputValue(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function Dashboard() {
    const {
        auth,
        upcoming_events: upcomingEvents,
        window_days: windowDays,
    } = usePageProps<DashboardProps>();
    const isSuperAdmin = Boolean(auth?.is_super_admin);
    const conflictCount = upcomingEvents.filter(
        (e) => e.conflicts_with.length > 0,
    ).length;
    const days = groupByDay(upcomingEvents);

    const [reportedIds, setReportedIds] = useState<number[]>([]);
    const [editingEvent, setEditingEvent] = useState<UpcomingEvent | null>(
        null,
    );

    const editForm = useForm<EditEventForm>({
        title: '',
        description: '',
        location: '',
        all_day: false,
        starts_at: '',
        ends_at: '',
    });

    /**
     * Where this event's write routes live.
     *
     * Group events are nested under their group; personal-calendar events have
     * no group_id at all and use the flat routes instead.
     */
    function eventUrl(event: UpcomingEvent): string {
        return event.group_id === null
            ? `/calendars/personal/events/${event.event_id}`
            : `/groups/${event.group_id}/calendars/${event.calendar_id}/events/${event.event_id}`;
    }

    function reportConflict(event: UpcomingEvent) {
        // Reporting a clash notifies the group's admins, so it only applies to
        // events that belong to a group.
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
    }

    function openEditTime(event: UpcomingEvent) {
        setEditingEvent(event);
        editForm.setData({
            title: event.title,
            description: event.description ?? '',
            location: event.location ?? '',
            all_day: event.all_day,
            starts_at: toLocalInputValue(new Date(event.starts_at)),
            ends_at: toLocalInputValue(new Date(event.ends_at)),
        });
        editForm.clearErrors();
    }

    function closeEditTime() {
        setEditingEvent(null);
        editForm.reset();
        editForm.clearErrors();
    }

    function submitEditTime(e: FormEvent) {
        e.preventDefault();
        if (!editingEvent) return;

        editForm.put(eventUrl(editingEvent), {
            preserveScroll: true,
            onSuccess: () => closeEditTime(),
        });
    }

    return (
        <>
            <Head title="Dashboard" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Welcome back, {auth?.user?.name}
                        </h3>
                        <p className="mt-1 text-sm text-gray-500">
                            {isSuperAdmin
                                ? "You're a Super Admin — you can create groups and assign group admins."
                                : "Here's what's coming up across your groups."}
                        </p>
                    </div>

                    {conflictCount > 0 && (
                        <div className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {conflictCount} upcoming{' '}
                            {conflictCount === 1
                                ? 'appointment'
                                : 'appointments'}{' '}
                            {conflictCount === 1 ? 'overlaps' : 'overlap'} with
                            another event on one of your calendars — see the
                            flagged items below.
                        </div>
                    )}

                    <div className="mb-6 rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <h3 className="text-sm font-semibold text-gray-900">
                                Next {windowDays} Days
                            </h3>
                            <p className="text-xs text-gray-500">
                                Every appointment across every calendar you can
                                see.
                            </p>
                        </div>

                        {days.length > 0 ? (
                            <div className="divide-y divide-gray-100">
                                {days.map(({ date, events }) => (
                                    <div
                                        key={date.toDateString()}
                                        className="px-6 py-4"
                                    >
                                        <h4 className="mb-3 text-xs font-semibold tracking-wide text-gray-400 uppercase">
                                            {formatDayHeading(date)}
                                        </h4>
                                        <ul className="space-y-3">
                                            {events.map((event) => (
                                                <li
                                                    key={event.key}
                                                    className={`rounded-md border px-4 py-3 ${
                                                        event.conflicts_with
                                                            .length > 0
                                                            ? 'border-red-200 bg-red-50'
                                                            : 'border-gray-100 bg-gray-50'
                                                    }`}
                                                >
                                                    <div className="flex items-start justify-between gap-4">
                                                        <div className="min-w-0">
                                                            <div className="flex items-center gap-2">
                                                                <span
                                                                    className="h-2 w-2 shrink-0 rounded-full"
                                                                    style={{
                                                                        backgroundColor:
                                                                            event.calendar_color ||
                                                                            '#6366f1',
                                                                    }}
                                                                />
                                                                <p className="truncate text-sm font-medium text-gray-900">
                                                                    {
                                                                        event.title
                                                                    }
                                                                </p>
                                                                {event
                                                                    .conflicts_with
                                                                    .length >
                                                                    0 && (
                                                                    <span className="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">
                                                                        Conflict
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <p className="mt-0.5 truncate text-xs text-gray-500">
                                                                {event.group_name
                                                                    ? `${event.group_name} · ${event.calendar_name}`
                                                                    : event.calendar_name}
                                                                {event.is_redacted &&
                                                                    ' · private'}
                                                            </p>
                                                            {event
                                                                .conflicts_with
                                                                .length > 0 && (
                                                                <p className="mt-1 text-xs text-red-600">
                                                                    Overlaps
                                                                    with:{' '}
                                                                    {event.conflicts_with.join(
                                                                        ', ',
                                                                    )}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div className="flex shrink-0 flex-col items-end gap-2">
                                                            <span className="text-xs font-medium text-gray-500">
                                                                {formatTimeRange(
                                                                    event,
                                                                )}
                                                            </span>
                                                            {event.can_edit &&
                                                            !event.is_redacted ? (
                                                                <button
                                                                    onClick={() =>
                                                                        openEditTime(
                                                                            event,
                                                                        )
                                                                    }
                                                                    className="text-xs font-medium text-indigo-600 hover:text-indigo-800"
                                                                >
                                                                    Edit time
                                                                </button>
                                                            ) : (
                                                                event
                                                                    .conflicts_with
                                                                    .length >
                                                                    0 &&
                                                                (reportedIds.includes(
                                                                    event.event_id,
                                                                ) ? (
                                                                    <span className="text-xs font-medium text-gray-400">
                                                                        Reported
                                                                    </span>
                                                                ) : (
                                                                    <button
                                                                        onClick={() =>
                                                                            reportConflict(
                                                                                event,
                                                                            )
                                                                        }
                                                                        className="text-xs font-medium text-red-600 hover:text-red-800"
                                                                    >
                                                                        Report
                                                                        conflict
                                                                    </button>
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
                        ) : (
                            <p className="px-6 py-10 text-center text-sm text-gray-500">
                                Nothing scheduled in the next {windowDays} days.
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <Link
                            href="/groups"
                            className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition hover:border-gray-300 hover:shadow-md"
                        >
                            <h3 className="mb-1 text-base font-semibold text-gray-900">
                                Groups
                            </h3>
                            <p className="text-sm text-gray-500">
                                {isSuperAdmin
                                    ? 'Manage every group in the system'
                                    : 'View the groups you belong to'}
                            </p>
                        </Link>

                        <Link
                            href="/calendars"
                            className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition hover:border-gray-300 hover:shadow-md"
                        >
                            <h3 className="mb-1 text-base font-semibold text-gray-900">
                                Calendars
                            </h3>
                            <p className="text-sm text-gray-500">
                                Browse every calendar you have access to
                            </p>
                        </Link>
                    </div>
                </div>
            </div>

            {/* Edit Time Dialog */}
            <Dialog
                open={editingEvent !== null}
                onClose={closeEditTime}
                className="relative z-50"
            >
                <div className="fixed inset-0 bg-black/30" aria-hidden="true" />
                <div className="fixed inset-0 flex items-center justify-center p-4">
                    <DialogPanel className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                        <DialogTitle className="mb-1 text-base font-semibold text-gray-900">
                            Edit Time
                        </DialogTitle>
                        <p className="mb-4 text-sm text-gray-500">
                            {editingEvent?.title}
                        </p>

                        <form onSubmit={submitEditTime} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">
                                        Starts at
                                    </label>
                                    <input
                                        type="datetime-local"
                                        value={editForm.data.starts_at}
                                        onChange={(e) =>
                                            editForm.setData(
                                                'starts_at',
                                                e.target.value,
                                            )
                                        }
                                        className={`w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none ${
                                            editForm.errors.starts_at
                                                ? 'border-red-500'
                                                : 'border-gray-300'
                                        }`}
                                        required
                                    />
                                    {editForm.errors.starts_at && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {editForm.errors.starts_at}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">
                                        Ends at
                                    </label>
                                    <input
                                        type="datetime-local"
                                        value={editForm.data.ends_at}
                                        onChange={(e) =>
                                            editForm.setData(
                                                'ends_at',
                                                e.target.value,
                                            )
                                        }
                                        className={`w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none ${
                                            editForm.errors.ends_at
                                                ? 'border-red-500'
                                                : 'border-gray-300'
                                        }`}
                                        required
                                    />
                                    {editForm.errors.ends_at && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {editForm.errors.ends_at}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={closeEditTime}
                                    className="flex-1 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={editForm.processing}
                                    className="flex-1 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:opacity-50"
                                >
                                    {editForm.processing ? 'Saving...' : 'Save'}
                                </button>
                            </div>
                        </form>
                    </DialogPanel>
                </div>
            </Dialog>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => (
    <AuthenticatedLayout
        header={
            <h2 className="text-xl leading-tight font-semibold text-gray-800">
                Dashboard
            </h2>
        }
    >
        {page}
    </AuthenticatedLayout>
);
