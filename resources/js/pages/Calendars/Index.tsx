import {
    Button,
    Card,
    ColorSwatchPicker,
    EmptyState,
    Field,
    Input,
    LinkButton,
    Modal,
    Textarea,
} from '@/components/ui';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { GroupSummary } from '@/types/calendar';
import { usePageProps } from '@/types/shared';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, Plus, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

type Group = GroupSummary;

interface Calendar {
    id: number;
    group_id: number;
    name: string;
    description: string | null;
    color: string | null;
}

interface Props {
    group: Group;
    calendars: Calendar[];
    can_manage: boolean;
}

interface CalendarFormData {
    name: string;
    description: string;
    color: string;
    [key: string]: string;
}

export default function CalendarsIndex({
    group,
    calendars,
    can_manage,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Calendar | null>(null);
    const [deleting, setDeleting] = useState<Calendar | null>(null);

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<CalendarFormData>({
            name: '',
            description: '',
            color: '',
        });

    function openCreateDialog() {
        setEditing(null);
        reset();
        clearErrors();
        setDialogOpen(true);
    }

    function openEditDialog(calendar: Calendar) {
        setEditing(calendar);
        setData({
            name: calendar.name,
            description: calendar.description ?? '',
            color: calendar.color ?? '',
        });
        clearErrors();
        setDialogOpen(true);
    }

    function closeDialog() {
        setDialogOpen(false);
        setEditing(null);
        reset();
        clearErrors();
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            put(`/groups/${group.id}/calendars/${editing.id}`, {
                onSuccess: () => closeDialog(),
                preserveScroll: true,
            });
        } else {
            post(`/groups/${group.id}/calendars`, {
                onSuccess: () => closeDialog(),
                preserveScroll: true,
            });
        }
    }

    function confirmDelete() {
        if (!deleting) return;

        router.delete(`/groups/${group.id}/calendars/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
        });
    }

    return (
        <>
            <Head title={`${group.name} - Calendars`} />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6 flex items-center justify-between">
                    <p className="text-content-secondary text-footnote">
                        Calendars belonging to this group.
                    </p>
                    {can_manage && (
                        <Button icon={Plus} onClick={openCreateDialog}>
                            New calendar
                        </Button>
                    )}
                </div>

                {calendars.length > 0 ? (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {calendars.map((calendar) => (
                            <Card key={calendar.id} material="thin">
                                <div className="mb-2 flex items-center gap-2">
                                    <span
                                        className="size-2.5 shrink-0 rounded-full"
                                        style={{
                                            backgroundColor:
                                                calendar.color ||
                                                'var(--event-top)',
                                        }}
                                    />
                                    <h3 className="text-headline text-content">
                                        {calendar.name}
                                    </h3>
                                </div>
                                <p className="text-content-secondary text-footnote mb-4 min-h-10">
                                    {calendar.description || 'No description'}
                                </p>
                                <div className="flex gap-2">
                                    <LinkButton
                                        href={`/groups/${group.id}/calendars/${calendar.id}`}
                                        className="flex-1"
                                    >
                                        View
                                    </LinkButton>
                                    {can_manage && (
                                        <>
                                            <Button
                                                variant="secondary"
                                                className="flex-1"
                                                onClick={() =>
                                                    openEditDialog(calendar)
                                                }
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="icon"
                                                aria-label={`Delete ${calendar.name}`}
                                                onClick={() =>
                                                    setDeleting(calendar)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={CalendarDays}
                        title="No calendars yet"
                        description={
                            can_manage
                                ? 'Create your first calendar to start scheduling events.'
                                : 'No calendars have been created for this group yet.'
                        }
                        action={
                            can_manage ? (
                                <Button icon={Plus} onClick={openCreateDialog}>
                                    Create your first calendar
                                </Button>
                            ) : undefined
                        }
                    />
                )}
            </div>

            {/* Create / edit */}
            <Modal
                open={dialogOpen}
                onClose={closeDialog}
                title={editing ? 'Edit calendar' : 'New calendar'}
                width="md"
                footer={
                    <>
                        <Button variant="secondary" onClick={closeDialog}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            form="calendar-form"
                            loading={processing}
                        >
                            {editing ? 'Save changes' : 'Create calendar'}
                        </Button>
                    </>
                }
            >
                <form
                    id="calendar-form"
                    onSubmit={handleSubmit}
                    className="space-y-4"
                >
                    <Field label="Name" error={errors.name}>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoFocus
                            invalid={Boolean(errors.name)}
                        />
                    </Field>

                    <Field label="Description" error={errors.description}>
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            rows={3}
                            invalid={Boolean(errors.description)}
                        />
                    </Field>

                    <Field label="Colour" error={errors.color}>
                        <ColorSwatchPicker
                            value={data.color || null}
                            onChange={(value) => setData('color', value)}
                            label="Calendar colour"
                        />
                    </Field>
                </form>
            </Modal>

            {/* Delete confirmation */}
            <Modal
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                title="Delete calendar"
                description={
                    deleting
                        ? `Delete calendar "${deleting.name}"? This cannot be undone.`
                        : undefined
                }
                width="sm"
                footer={
                    <>
                        <Button
                            variant="secondary"
                            onClick={() => setDeleting(null)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            Delete
                        </Button>
                    </>
                }
            />
        </>
    );
}

function CalendarsHeading() {
    const { group } = usePageProps<{ group: Group }>();

    return (
        <div className="flex items-center justify-between gap-4">
            <h2 className="text-headline text-chrome-content">
                {group.name} - Calendars
            </h2>
            <Link
                href={route('groups.show', group.id)}
                className="text-chrome-content/70 hover:text-chrome-content text-footnote font-medium"
            >
                Back to group
            </Link>
        </div>
    );
}

CalendarsIndex.layout = (page: ReactNode) => (
    <AuthenticatedLayout header={<CalendarsHeading />}>
        {page}
    </AuthenticatedLayout>
);
