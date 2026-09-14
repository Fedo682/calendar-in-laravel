import DayView from '@/components/Calendar/DayView';
import EventDialog from '@/components/Calendar/EventDialog';
import MonthGrid from '@/components/Calendar/MonthGrid';
import type { RecurrenceScope } from '@/components/Calendar/RecurrenceScopeDialog';
import RecurrenceScopeDialog from '@/components/Calendar/RecurrenceScopeDialog';
import { useEventForm } from '@/components/Calendar/useEventForm';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { CalendarEvent, Occurrence } from '@/types/calendar';
import { Head } from '@inertiajs/react';
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

export default function PersonalCalendarPage({ calendar, occurrences }: Props) {
    const [month, setMonth] = useState<Date>(new Date());
    const [selectedDay, setSelectedDay] = useState<Date | null>(null);
    const [dialogMode, setDialogMode] = useState<DialogMode | null>(null);
    const [editingEvent, setEditingEvent] = useState<Occurrence | null>(null);
    const [scopePrompt, setScopePrompt] = useState<'save' | 'delete' | null>(
        null,
    );

    // Anything on a personal calendar is private unless its owner says
    // otherwise; the server applies the same default if this is omitted.
    const { form, openCreate, openEdit, reset } = useEventForm({
        visibility: 'private',
    });

    const closeDialog = () => {
        setDialogMode(null);
        setEditingEvent(null);
        reset();
    };

    const openCreateDialog = (date: Date) => {
        openCreate(date, 'private');
        setEditingEvent(null);
        setDialogMode('create');
    };

    const openEditDialog = (clicked: CalendarEvent) => {
        const full = occurrences.find((o) => o.id === clicked.id);

        if (!full) {
            return;
        }

        openEdit(full);
        setEditingEvent(full);
        setDialogMode('edit');
    };

    const needsScope =
        dialogMode === 'edit' && (editingEvent?.is_recurring ?? false);

    const saveWithScope = (scope: RecurrenceScope) => {
        if (!editingEvent) return;

        // transform() mutates the form rather than returning it, so the scope
        // is applied and then reset once the request has been sent.
        form.transform((data) => ({ ...data, scope }));

        form.put(`/calendars/personal/events/${editingEvent.event_id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setScopePrompt(null);
                closeDialog();
            },
            onFinish: () => form.transform((data) => data),
        });
    };

    const deleteWithScope = (scope: RecurrenceScope) => {
        if (!editingEvent) return;

        form.transform((data) => ({ ...data, scope }));

        form.delete(`/calendars/personal/events/${editingEvent.event_id}`, {
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

        if (dialogMode === 'edit' && editingEvent) {
            if (needsScope) {
                setScopePrompt('save');

                return;
            }

            saveWithScope('all');

            return;
        }

        form.post('/calendars/personal/events', {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        });
    };

    const destroy = () => {
        if (!editingEvent) return;

        if (needsScope) {
            setScopePrompt('delete');

            return;
        }

        if (!window.confirm('Delete this event? This cannot be undone.'))
            return;

        deleteWithScope('all');
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

            <EventDialog
                open={dialogMode !== null}
                mode={dialogMode ?? 'create'}
                form={form}
                onSubmit={submit}
                onDelete={dialogMode === 'edit' ? destroy : undefined}
                onClose={closeDialog}
            />

            <RecurrenceScopeDialog
                open={scopePrompt !== null}
                action={scopePrompt ?? 'save'}
                processing={form.processing}
                onCancel={() => setScopePrompt(null)}
                onConfirm={(scope) =>
                    scopePrompt === 'delete'
                        ? deleteWithScope(scope)
                        : saveWithScope(scope)
                }
            />
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
