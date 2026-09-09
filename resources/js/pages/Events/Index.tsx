import { FormEvent, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import MonthGrid, { CalendarEvent } from '@/Components/Calendar/MonthGrid';

interface Group {
    id: number;
    name: string;
}

interface Calendar {
    id: number;
    name: string;
}

interface EventRecord extends CalendarEvent {
    description: string | null;
    location: string | null;
}

interface Props {
    group: Group;
    calendar: Calendar;
    events: EventRecord[];
    can_manage: boolean;
}

type DialogMode = 'create' | 'edit';

interface EventFormData {
    title: string;
    description: string;
    location: string;
    starts_at: string;
    ends_at: string;
    all_day: boolean;
}

/** Format a Date as the value expected by <input type="datetime-local">. */
function toLocalInputValue(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function eventsIndexUrl(group: Group, calendar: Calendar): string {
    return `/groups/${group.id}/calendars/${calendar.id}/events`;
}

function eventUrl(group: Group, calendar: Calendar, eventId: number): string {
    return `${eventsIndexUrl(group, calendar)}/${eventId}`;
}

export default function EventsIndex({ group, calendar, events, can_manage }: Props) {
    const [month, setMonth] = useState<Date>(new Date());
    const [dialogMode, setDialogMode] = useState<DialogMode | null>(null);
    const [editingEvent, setEditingEvent] = useState<EventRecord | null>(null);

    const form = useForm<EventFormData>({
        title: '',
        description: '',
        location: '',
        starts_at: '',
        ends_at: '',
        all_day: false,
    });

    const closeDialog = () => {
        setDialogMode(null);
        setEditingEvent(null);
        form.reset();
        form.clearErrors();
    };

    const openCreateDialog = (date: Date) => {
        if (!can_manage) {
            return;
        }

        const start = new Date(date);
        start.setHours(9, 0, 0, 0);
        const end = new Date(date);
        end.setHours(10, 0, 0, 0);

        form.setData({
            title: '',
            description: '',
            location: '',
            starts_at: toLocalInputValue(start),
            ends_at: toLocalInputValue(end),
            all_day: false,
        });
        setEditingEvent(null);
        setDialogMode('create');
    };

    const openEditDialog = (clicked: CalendarEvent) => {
        if (!can_manage) {
            return;
        }

        const full = events.find((event) => event.id === clicked.id);

        if (!full) {
            return;
        }

        form.setData({
            title: full.title,
            description: full.description ?? '',
            location: full.location ?? '',
            starts_at: toLocalInputValue(new Date(full.starts_at)),
            ends_at: toLocalInputValue(new Date(full.ends_at)),
            all_day: full.all_day,
        });
        setEditingEvent(full);
        setDialogMode('edit');
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (dialogMode === 'edit' && editingEvent) {
            form.put(eventUrl(group, calendar, editingEvent.id), {
                preserveScroll: true,
                onSuccess: () => closeDialog(),
            });
        } else {
            form.post(eventsIndexUrl(group, calendar), {
                preserveScroll: true,
                onSuccess: () => closeDialog(),
            });
        }
    };

    const destroy = () => {
        if (!editingEvent) {
            return;
        }

        if (!window.confirm('Delete this event? This cannot be undone.')) {
            return;
        }

        form.delete(eventUrl(group, calendar, editingEvent.id), {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        });
    };

    const goToPrevMonth = () => setMonth((m) => new Date(m.getFullYear(), m.getMonth() - 1, 1));
    const goToNextMonth = () => setMonth((m) => new Date(m.getFullYear(), m.getMonth() + 1, 1));
    const goToToday = () => setMonth(new Date());

    const monthLabel = month.toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {calendar.name}
                        </h2>
                        <p className="text-sm text-gray-500">{group.name}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        {!can_manage && (
                            <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                                View only
                            </span>
                        )}
                        <Link
                            href={`/groups/${group.id}/calendars`}
                            className="text-sm font-medium text-gray-500 hover:text-gray-800"
                        >
                            Back to calendars
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`${calendar.name} - ${group.name}`} />

            <div className="py-8">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={goToPrevMonth}
                                    className="rounded-md border border-gray-300 px-3 py-1.5 text-sm transition hover:bg-gray-50"
                                >
                                    ← Prev
                                </button>
                                <button
                                    type="button"
                                    onClick={goToToday}
                                    className="rounded-md border border-gray-300 px-3 py-1.5 text-sm transition hover:bg-gray-50"
                                >
                                    Today
                                </button>
                                <button
                                    type="button"
                                    onClick={goToNextMonth}
                                    className="rounded-md border border-gray-300 px-3 py-1.5 text-sm transition hover:bg-gray-50"
                                >
                                    Next →
                                </button>
                            </div>
                            <h3 className="text-base font-semibold text-gray-900">{monthLabel}</h3>
                        </div>

                        <MonthGrid
                            month={month}
                            events={events}
                            onDayClick={can_manage ? openCreateDialog : undefined}
                            onEventClick={can_manage ? openEditDialog : undefined}
                        />
                    </div>
                </div>
            </div>

            <Dialog open={dialogMode !== null} onClose={closeDialog} className="relative z-50">
                <div className="fixed inset-0 bg-black/30" aria-hidden="true" />
                <div className="fixed inset-0 flex items-center justify-center p-4">
                    <DialogPanel className="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                        <DialogTitle className="mb-4 text-xl font-bold text-gray-900">
                            {dialogMode === 'edit' ? 'Edit Event' : 'New Event'}
                        </DialogTitle>

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Title</label>
                                <input
                                    type="text"
                                    value={form.data.title}
                                    onChange={(e) => form.setData('title', e.target.value)}
                                    className={`w-full rounded-lg border px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                        form.errors.title ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                    required
                                />
                                {form.errors.title && (
                                    <p className="mt-1 text-sm text-red-500">{form.errors.title}</p>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    Description
                                </label>
                                <textarea
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    rows={3}
                                    className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                {form.errors.description && (
                                    <p className="mt-1 text-sm text-red-500">{form.errors.description}</p>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    Location
                                </label>
                                <input
                                    type="text"
                                    value={form.data.location}
                                    onChange={(e) => form.setData('location', e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                {form.errors.location && (
                                    <p className="mt-1 text-sm text-red-500">{form.errors.location}</p>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    id="all_day"
                                    type="checkbox"
                                    checked={form.data.all_day}
                                    onChange={(e) => form.setData('all_day', e.target.checked)}
                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <label htmlFor="all_day" className="text-sm font-medium text-gray-700">
                                    All day
                                </label>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">
                                        Starts at
                                    </label>
                                    <input
                                        type="datetime-local"
                                        value={form.data.starts_at}
                                        onChange={(e) => form.setData('starts_at', e.target.value)}
                                        className={`w-full rounded-lg border px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                            form.errors.starts_at ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                        required
                                    />
                                    {form.errors.starts_at && (
                                        <p className="mt-1 text-sm text-red-500">{form.errors.starts_at}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">
                                        Ends at
                                    </label>
                                    <input
                                        type="datetime-local"
                                        value={form.data.ends_at}
                                        onChange={(e) => form.setData('ends_at', e.target.value)}
                                        className={`w-full rounded-lg border px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                            form.errors.ends_at ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                        required
                                    />
                                    {form.errors.ends_at && (
                                        <p className="mt-1 text-sm text-red-500">{form.errors.ends_at}</p>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center justify-between pt-2">
                                <div>
                                    {dialogMode === 'edit' && (
                                        <button
                                            type="button"
                                            onClick={destroy}
                                            disabled={form.processing}
                                            className="rounded-lg border border-red-300 px-4 py-2 font-medium text-red-600 transition hover:bg-red-50 disabled:opacity-50"
                                        >
                                            Delete
                                        </button>
                                    )}
                                </div>
                                <div className="flex gap-3">
                                    <button
                                        type="button"
                                        onClick={closeDialog}
                                        className="rounded-lg border border-gray-300 px-4 py-2 font-medium transition hover:bg-gray-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="rounded-lg bg-gray-800 px-4 py-2 font-semibold text-white transition hover:bg-gray-700 disabled:opacity-50"
                                    >
                                        {dialogMode === 'edit' ? 'Save Changes' : 'Create Event'}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </DialogPanel>
                </div>
            </Dialog>
        </AuthenticatedLayout>
    );
}
