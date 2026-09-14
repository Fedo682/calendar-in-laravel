import DayView from '@/components/Calendar/DayView';
import EventDialog from '@/components/Calendar/EventDialog';
import { Badge, Button } from '@/components/ui';
import MonthGrid from '@/components/Calendar/MonthGrid';
import type { RecurrenceScope } from '@/components/Calendar/RecurrenceScopeDialog';
import RecurrenceScopeDialog from '@/components/Calendar/RecurrenceScopeDialog';
import { useEventForm } from '@/components/Calendar/useEventForm';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { CalendarEvent, Occurrence } from '@/types/calendar';
import { usePageProps } from '@/types/shared';
import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

interface Group {
    id: number;
    name: string;
}

interface Calendar {
    id: number;
    name: string;
}

interface Props {
    group: Group;
    calendar: Calendar;
    /** Already redacted for the viewer - there is no fuller copy to reach for. */
    occurrences: Occurrence[];
    range: { from: string; to: string };
    can_manage: boolean;
}

type DialogMode = 'create' | 'edit';

function eventsIndexUrl(group: Group, calendar: Calendar): string {
    return `/groups/${group.id}/calendars/${calendar.id}/events`;
}

function eventUrl(group: Group, calendar: Calendar, eventId: number): string {
    return `${eventsIndexUrl(group, calendar)}/${eventId}`;
}

export default function EventsIndex({
    group,
    calendar,
    occurrences,
    can_manage,
}: Props) {
    const [month, setMonth] = useState<Date>(new Date());
    const [selectedDay, setSelectedDay] = useState<Date | null>(null);
    const [dialogMode, setDialogMode] = useState<DialogMode | null>(null);
    const [editingEvent, setEditingEvent] = useState<Occurrence | null>(null);
    const [scopePrompt, setScopePrompt] = useState<'save' | 'delete' | null>(
        null,
    );

    const { form, openCreate, openEdit, reset } = useEventForm();

    const closeDialog = () => {
        setDialogMode(null);
        setEditingEvent(null);
        reset();
    };

    const openCreateDialog = (date: Date) => {
        if (!can_manage) {
            return;
        }

        openCreate(date);
        setEditingEvent(null);
        setDialogMode('create');
    };

    const openEditDialog = (clicked: CalendarEvent) => {
        if (!can_manage) {
            return;
        }

        const full = occurrences.find((o) => o.id === clicked.id);

        if (!full) {
            return;
        }

        // A redacted occurrence carries a stand-in title and null body, so
        // opening it for edit would offer to save that over the real thing.
        if (full.is_redacted) {
            return;
        }

        openEdit(full);
        setEditingEvent(full);
        setDialogMode('edit');
    };

    /**
     * Editing or deleting a series has to ask which occurrences it applies
     * to before it can send anything, so both paths route through the scope
     * dialog when one is open on a recurring event.
     */
    const needsScope =
        dialogMode === 'edit' && (editingEvent?.is_recurring ?? false);

    const saveWithScope = (scope: RecurrenceScope) => {
        if (!editingEvent) return;

        // transform() mutates the form rather than returning it, so the scope
        // is applied and then reset once the request has been sent.
        form.transform((data) => ({ ...data, scope }));

        form.put(eventUrl(group, calendar, editingEvent.event_id), {
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

        form.delete(eventUrl(group, calendar, editingEvent.event_id), {
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

        form.post(eventsIndexUrl(group, calendar), {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        });
    };

    const destroy = () => {
        if (!editingEvent) {
            return;
        }

        if (needsScope) {
            setScopePrompt('delete');

            return;
        }

        if (!window.confirm('Delete this event? This cannot be undone.')) {
            return;
        }

        deleteWithScope('all');
    };

    const goToPrevMonth = () =>
        setMonth((m) => new Date(m.getFullYear(), m.getMonth() - 1, 1));
    const goToNextMonth = () =>
        setMonth((m) => new Date(m.getFullYear(), m.getMonth() + 1, 1));
    const goToToday = () => setMonth(new Date());

    const monthLabel = month.toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });

    return (
        <>
            <Head title={`${calendar.name} - ${group.name}`} />

            <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                {selectedDay ? (
                    <DayView
                        date={selectedDay}
                        events={occurrences}
                        onClose={() => setSelectedDay(null)}
                        onSlotClick={can_manage ? openCreateDialog : undefined}
                        onEventClick={openEditDialog}
                    />
                ) : (
                    <>
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Previous month"
                                    onClick={goToPrevMonth}
                                >
                                    <ChevronLeft className="size-4" />
                                </Button>
                                <Button variant="ghost" onClick={goToToday}>
                                    Today
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Next month"
                                    onClick={goToNextMonth}
                                >
                                    <ChevronRight className="size-4" />
                                </Button>
                                <h3 className="text-title3 text-content ms-2 font-semibold">
                                    {monthLabel}
                                </h3>
                            </div>
                        </div>

                        <MonthGrid
                            month={month}
                            events={occurrences}
                            onDayClick={(date) => setSelectedDay(date)}
                            onEventClick={openEditDialog}
                        />
                    </>
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

function EventsHeading() {
    const { group, calendar, can_manage } = usePageProps<Props>();

    return (
        <div className="flex items-center justify-between gap-4">
            <div>
                <h2 className="text-headline text-chrome-content">
                    {calendar.name}
                </h2>
                <p className="text-chrome-content/70 text-footnote">
                    {group.name}
                </p>
            </div>
            <div className="flex items-center gap-3">
                {!can_manage && <Badge>View only</Badge>}
                <Link
                    href={`/groups/${group.id}/calendars`}
                    className="text-chrome-content/70 hover:text-chrome-content text-footnote font-medium"
                >
                    Back to calendars
                </Link>
            </div>
        </div>
    );
}

EventsIndex.layout = (page: ReactNode) => (
    <AuthenticatedLayout header={<EventsHeading />}>{page}</AuthenticatedLayout>
);
