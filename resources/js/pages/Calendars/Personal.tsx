import DayView from '@/components/Calendar/DayView';
import EventDialog from '@/components/Calendar/EventDialog';
import { Button, Card } from '@/components/ui';
import MonthGrid from '@/components/Calendar/MonthGrid';
import type { RecurrenceScope } from '@/components/Calendar/RecurrenceScopeDialog';
import RecurrenceScopeDialog from '@/components/Calendar/RecurrenceScopeDialog';
import {
    useEventForm,
    withUtcOffsets,
} from '@/components/Calendar/useEventForm';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { nowInZone } from '@/lib/datetime';
import type { CalendarEvent, Occurrence } from '@/types/calendar';
import { usePageProps } from '@/types/shared';
import { Head } from '@inertiajs/react';
import { TZDate } from '@date-fns/tz';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
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
    const { viewer } = usePageProps<Props>();
    const timezone = viewer.timezone;
    const weekStartsOn = viewer.week_starts_on;

    const [month, setMonth] = useState<Date>(() => nowInZone(timezone));
    const [selectedDay, setSelectedDay] = useState<Date | null>(null);
    const [dialogMode, setDialogMode] = useState<DialogMode | null>(null);
    const [editingEvent, setEditingEvent] = useState<Occurrence | null>(null);
    const [scopePrompt, setScopePrompt] = useState<'save' | 'delete' | null>(
        null,
    );

    // Anything on a personal calendar is private unless its owner says
    // otherwise; the server applies the same default if this is omitted.
    const { form, openCreate, openEdit, reset } = useEventForm(timezone, {
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
        form.transform((data) => ({
            ...withUtcOffsets(data, timezone),
            scope,
        }));

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

        form.transform((data) => withUtcOffsets(data, timezone));

        form.post('/calendars/personal/events', {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
            onFinish: () => form.transform((data) => data),
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
                new TZDate(
                    current.getFullYear(),
                    current.getMonth() + delta,
                    1,
                    timezone,
                ),
        );

    return (
        <>
            <Head title={calendar.name} />

            <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <Card material="thin" className="mb-4">
                    <p className="text-content-secondary text-footnote">
                        Everything here is yours. Events default to private, so
                        other people see only that you are busy, never the
                        title, description or location.
                    </p>
                </Card>

                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Previous month"
                            onClick={() => shiftMonth(-1)}
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            onClick={() => setMonth(nowInZone(timezone))}
                        >
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
                        <h3 className="text-title3 text-content ms-2 font-semibold">
                            {month.toLocaleDateString(undefined, {
                                month: 'long',
                                year: 'numeric',
                            })}
                        </h3>
                    </div>

                    <Button
                        icon={Plus}
                        onClick={() => openCreateDialog(nowInZone(timezone))}
                    >
                        New event
                    </Button>
                </div>

                <MonthGrid
                    month={month}
                    events={occurrences}
                    timezone={timezone}
                    weekStartsOn={weekStartsOn}
                    onDayClick={(date) => setSelectedDay(date)}
                    onEventClick={openEditDialog}
                />

                {selectedDay && (
                    <div className="mt-6">
                        <DayView
                            date={selectedDay}
                            events={occurrences}
                            timezone={timezone}
                            onClose={() => setSelectedDay(null)}
                            onSlotClick={openCreateDialog}
                            onEventClick={openEditDialog}
                        />
                    </div>
                )}
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
            <h2 className="text-headline text-chrome-content">My Calendar</h2>
        }
    >
        {page}
    </AuthenticatedLayout>
);
