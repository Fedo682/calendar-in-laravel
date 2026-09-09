import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

interface Group {
    id: number;
    name: string;
    description: string | null;
}

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

export default function CalendarsIndex({ group, calendars, can_manage }: Props) {
    const { flash } = usePage().props as any;
    const [showToast, setShowToast] = useState(false);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Calendar | null>(null);

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<CalendarFormData>({
            name: '',
            description: '',
            color: '',
        });

    useEffect(() => {
        if (flash?.success) {
            setShowToast(true);
            const timer = setTimeout(() => setShowToast(false), 3000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

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

    function handleDelete(calendar: Calendar) {
        if (confirm(`Delete calendar "${calendar.name}"? This cannot be undone.`)) {
            router.delete(`/groups/${group.id}/calendars/${calendar.id}`, {
                preserveScroll: true,
            });
        }
    }

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {group.name} — Calendars
                    </h2>
                    <Link
                        href={route('groups.show', group.id)}
                        className="text-sm font-medium text-gray-500 hover:text-gray-800"
                    >
                        Back to group
                    </Link>
                </div>
            }
        >
            <Head title={`${group.name} - Calendars`} />

            {showToast && (
                <div className="fixed right-4 top-4 z-50 rounded-md border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 shadow-lg">
                    {flash?.success}
                </div>
            )}

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex items-center justify-between">
                        <p className="text-sm text-gray-500">
                            Calendars belonging to this group.
                        </p>
                        {can_manage && (
                            <button
                                onClick={openCreateDialog}
                                className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                            >
                                New Calendar
                            </button>
                        )}
                    </div>

                    {calendars.length > 0 ? (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {calendars.map((calendar) => (
                                <div
                                    key={calendar.id}
                                    className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition hover:border-gray-300 hover:shadow-md"
                                >
                                    <div className="mb-2 flex items-center gap-2">
                                        <span
                                            className="h-2.5 w-2.5 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor: calendar.color || '#6366f1',
                                            }}
                                        />
                                        <h3 className="text-base font-semibold text-gray-900">
                                            {calendar.name}
                                        </h3>
                                    </div>
                                    <p className="mb-4 min-h-10 text-sm text-gray-500">
                                        {calendar.description || 'No description'}
                                    </p>
                                    <div className="flex gap-2">
                                        <a
                                            href={`/groups/${group.id}/calendars/${calendar.id}`}
                                            className="flex-1 rounded-md bg-gray-800 px-3 py-1.5 text-center text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                                        >
                                            View
                                        </a>
                                        {can_manage && (
                                            <>
                                                <button
                                                    onClick={() => openEditDialog(calendar)}
                                                    className="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-gray-600 transition hover:bg-gray-50"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(calendar)}
                                                    className="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-500 transition hover:bg-red-50"
                                                >
                                                    Delete
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                            <h3 className="text-sm font-semibold text-gray-900">
                                No calendars yet
                            </h3>
                            <p className="mt-1 text-sm text-gray-500">
                                {can_manage
                                    ? 'Create your first calendar to start scheduling events.'
                                    : 'No calendars have been created for this group yet.'}
                            </p>
                            {can_manage && (
                                <button
                                    onClick={openCreateDialog}
                                    className="mt-4 inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                                >
                                    Create Your First Calendar
                                </button>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {/* Create / Edit Dialog */}
            <Dialog open={dialogOpen} onClose={closeDialog} className="relative z-50">
                <div className="fixed inset-0 bg-black/30" aria-hidden="true" />
                <div className="fixed inset-0 flex items-center justify-center p-4">
                    <DialogPanel className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                        <DialogTitle className="mb-4 text-base font-semibold text-gray-900">
                            {editing ? 'Edit Calendar' : 'New Calendar'}
                        </DialogTitle>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label htmlFor="name" className="mb-1 block text-sm font-medium text-gray-700">
                                    Name
                                </label>
                                <input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    autoFocus
                                />
                                {errors.name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                                )}
                            </div>

                            <div>
                                <label htmlFor="description" className="mb-1 block text-sm font-medium text-gray-700">
                                    Description
                                </label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={3}
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                {errors.description && (
                                    <p className="mt-1 text-sm text-red-600">{errors.description}</p>
                                )}
                            </div>

                            <div>
                                <label htmlFor="color" className="mb-1 block text-sm font-medium text-gray-700">
                                    Color
                                </label>
                                <div className="flex items-center gap-3">
                                    <input
                                        id="color"
                                        type="color"
                                        value={data.color || '#4f46e5'}
                                        onChange={(e) => setData('color', e.target.value)}
                                        className="h-9 w-12 rounded-md border-gray-300"
                                    />
                                    <input
                                        type="text"
                                        value={data.color}
                                        onChange={(e) => setData('color', e.target.value)}
                                        placeholder="#4f46e5"
                                        className="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                {errors.color && (
                                    <p className="mt-1 text-sm text-red-600">{errors.color}</p>
                                )}
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:opacity-50"
                                >
                                    {editing ? 'Save Changes' : 'Create Calendar'}
                                </button>
                                <button
                                    type="button"
                                    onClick={closeDialog}
                                    className="flex-1 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </DialogPanel>
                </div>
            </Dialog>
        </AuthenticatedLayout>
    );
}
