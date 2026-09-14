import DayView from '@/components/Calendar/DayView';
import MonthGrid from '@/components/Calendar/MonthGrid';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { CalendarEvent, Occurrence, Visibility } from '@/types/calendar';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

interface PersonalCalendar {
    id: number;
    name: string;
    description: string | null;
    color: string | null;
}

interface Props {
    calendar: PersonalCalendar;
    occurrences: Occurrence[];
    range: { from: string; to: string };
}

type DialogMode = 'create' | 'edit';

interface EventFormData {
    title: string;
    description: string;
    location: string;
    starts_at: string;
    ends_at: string;
    all_day: boolean;
    visibility: Visibility;
}

/** Format a Date as the value expected by <input type="datetime-local">. */
function toLocalInputValue(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function PersonalCalendarPage({ calendar, occurrences }: Props) {
    const [month, setMonth] = useState<Date>(new Date());
    const [selectedDay, setSelectedDay] = useState<Date | null>(null);
    const [dialogMode, setDialogMode] = useState<DialogMode | null>(null);
    const [editingEvent, setEditingEvent] = useState<Occurrence | null>(null);

    const form = useForm<EventFormData>({
        title: '',
        description: '',
        location: '',
        starts_at: '',
        ends_at: '',
        all_day: false,
        // Anything on a personal calendar is private unless its owner says
        // otherwise; the server applies the same default if this is omitted.
        visibility: 'private',
    });

    const closeDialog = () => {
        setDialogMode(null);
        setEditingEvent(null);
        form.reset();
        form.clearErrors();
    };

    const openCreateDialog = (date: Date) => {
        const start = new Date(date);

        // A month-grid click carries midnight; a day-view slot click already
        // carries the hour that was clicked.
        if (start.getHours() === 0 && start.getMinutes() === 0) {
            start.setHours(9, 0, 0, 0);
        }

        const end = new Date(start);
        end.setHours(start.getHours() + 1);

        form.setData({
            title: '',
            description: '',
            location: '',
            starts_at: toLocalInputValue(start),
            ends_at: toLocalInputValue(end),
            all_day: false,
            visibility: 'private',
        });
        setEditingEvent(null);
        setDialogMode('create');
    };

    const openEditDialog = (clicked: CalendarEvent) => {
        const full = occurrences.find((o) => o.id === clicked.id);

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
            visibility: full.visibility,
        });
        setEditingEvent(full);
        setDialogMode('edit');
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (dialogMode === 'edit' && editingEvent) {
            form.put(`/calendars/personal/events/${editingEvent.event_id}`, {
                preserveScroll: true,
                onSuccess: () => closeDialog(),
            });

            return;
        }

        form.post('/calendars/personal/events', {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        });
    };

    const destroy = () => {
        if (!editingEvent) return;
        if (!window.confirm('Delete this event? This cannot be undone.'))
            return;

        form.delete(`/calendars/personal/events/${editingEvent.event_id}`, {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        });
    };

    const shiftMonth = (delta: number) =>
        setMonth(
            (current) =>
                new Date(current.getFullYear(), current.getMonth() + delta, 1),
        );

    return (
        <>
            <Head title={calendar.name} />

            <div className="py-8">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4 rounded-lg border border-gray-200 bg-white px-6 py-4 shadow-sm">
                        <p className="text-sm text-gray-600">
                            Everything here is yours. Events default to private,
                            so other people see only that you are busy, never
                            the title, description or location.
                        </p>
                    </div>

                    <div className="mb-4 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => shiftMonth(-1)}
                                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Previous
                            </button>
                            <button
                                onClick={() => setMonth(new Date())}
                                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Today
                            </button>
                            <button
                                onClick={() => shiftMonth(1)}
                                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Next
                            </button>
                            <span className="ml-2 text-sm font-semibold text-gray-900">
                                {month.toLocaleDateString(undefined, {
                                    month: 'long',
                                    year: 'numeric',
                                })}
                            </span>
                        </div>

                        <button
                            onClick={() => openCreateDialog(new Date())}
                            className="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition hover:bg-gray-700"
                        >
                            New Event
                        </button>
                    </div>

                    <MonthGrid
                        month={month}
                        events={occurrences}
                        onDayClick={(date) => setSelectedDay(date)}
                        onEventClick={openEditDialog}
                    />

                    {selectedDay && (
                        <div className="mt-6">
                            <DayView
                                date={selectedDay}
                                events={occurrences}
                                onClose={() => setSelectedDay(null)}
                                onSlotClick={openCreateDialog}
                                onEventClick={openEditDialog}
                            />
                        </div>
                    )}
                </div>
            </div>

            <Dialog
                open={dialogMode !== null}
                onClose={closeDialog}
                className="relative z-50"
            >
                <div className="fixed inset-0 bg-black/30" aria-hidden="true" />
                <div className="fixed inset-0 flex items-center justify-center p-4">
                    <DialogPanel className="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                        <DialogTitle className="mb-4 text-xl font-bold text-gray-900">
                            {dialogMode === 'edit' ? 'Edit Event' : 'New Event'}
                        </DialogTitle>

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    Title
                                </label>
                                <input
                                    type="text"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    className={`w-full rounded-lg border px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none ${
                                        form.errors.title
                                            ? 'border-red-500'
                                            : 'border-gray-300'
                                    }`}
                                    required
                                />
                                {form.errors.title && (
                                    <p className="mt-1 text-sm text-red-500">
                                        {form.errors.title}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    Description
                                </label>
                                <textarea
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                    className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    Location
                                </label>
                                <input
                                    type="text"
                                    value={form.data.location}
                                    onChange={(e) =>
                                        form.setData('location', e.target.value)
                                    }
                                    className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="personal_all_day"
                                    checked={form.data.all_day}
                                    onChange={(e) =>
                                        form.setData(
                                            'all_day',
                                            e.target.checked,
                                        )
                                    }
                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <label
                                    htmlFor="personal_all_day"
                                    className="text-sm font-medium text-gray-700"
                                >
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
                                        onChange={(e) =>
                                            form.setData(
                                                'starts_at',
                                                e.target.value,
                                            )
                                        }
                                        className={`w-full rounded-lg border px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none ${
                                            form.errors.starts_at
                                                ? 'border-red-500'
                                                : 'border-gray-300'
                                        }`}
                                        required
                                    />
                                    {form.errors.starts_at && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {form.errors.starts_at}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">
                                        Ends at
                                    </label>
                                    <input
                                        type="datetime-local"
                                        value={form.data.ends_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'ends_at',
                                                e.target.value,
                                            )
                                        }
                                        className={`w-full rounded-lg border px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none ${
                                            form.errors.ends_at
                                                ? 'border-red-500'
                                                : 'border-gray-300'
                                        }`}
                                        required
                                    />
                                    {form.errors.ends_at && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {form.errors.ends_at}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label
                                    htmlFor="personal_visibility"
                                    className="mb-1 block text-sm font-medium text-gray-700"
                                >
                                    Visibility
                                </label>
                                <select
                                    id="personal_visibility"
                                    value={form.data.visibility}
                                    onChange={(e) =>
                                        form.setData(
                                            'visibility',
                                            e.target.value as Visibility,
                                        )
                                    }
                                    className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                >
                                    <option value="private">
                                        Private - others see only that you are
                                        busy
                                    </option>
                                    <option value="busy">
                                        Busy - details hidden from everyone
                                    </option>
                                    <option value="public">
                                        Public - anyone who can see this
                                        calendar sees the details
                                    </option>
                                </select>
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
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={closeDialog}
                                        className="rounded-lg border border-gray-300 px-4 py-2 font-medium text-gray-700 transition hover:bg-gray-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="rounded-lg bg-gray-800 px-4 py-2 font-medium text-white transition hover:bg-gray-700 disabled:opacity-50"
                                    >
                                        {form.processing ? 'Saving...' : 'Save'}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </DialogPanel>
                </div>
            </Dialog>
        </>
    );
}

PersonalCalendarPage.layout = (page: ReactNode) => (
    <AuthenticatedLayout
        header={
            <h2 className="text-xl leading-tight font-semibold text-gray-800">
                My Calendar
            </h2>
        }
    >
        {page}
    </AuthenticatedLayout>
);
