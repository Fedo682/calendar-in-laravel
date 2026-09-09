import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

interface GroupProp {
    id: number;
    name: string;
    description: string | null;
    created_at: string;
}

interface Calendar {
    id: number;
    name?: string;
    title?: string;
    [key: string]: unknown;
}

interface Member {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'member' | null;
}

interface Props {
    group: GroupProp;
    calendars: Calendar[];
    members: Member[];
    can_manage: boolean;
    is_group_admin: boolean;
    is_super_admin: boolean;
}

export default function GroupsShow({
    group,
    calendars,
    members,
    can_manage,
    is_super_admin,
}: Props) {
    const { flash } = usePage().props as any;
    const [showToast, setShowToast] = useState(false);
    const [showAddMember, setShowAddMember] = useState(false);
    const [showBulkAdd, setShowBulkAdd] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            setShowToast(true);
            const timer = setTimeout(() => setShowToast(false), 3000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    const { data, setData, post, processing, errors, reset } = useForm({
        user_id: '',
        role: 'member',
    });

    const submitAddMember: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('groups.members.store', group.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setShowAddMember(false);
            },
        });
    };

    const bulkForm = useForm({
        emails: '',
        role: 'member',
    });

    const submitBulkAdd: FormEventHandler = (e) => {
        e.preventDefault();
        bulkForm.post(route('groups.members.bulk', group.id), {
            preserveScroll: true,
            onSuccess: () => {
                bulkForm.reset();
                setShowBulkAdd(false);
            },
        });
    };

    const changeRole = (member: Member, role: 'admin' | 'member') => {
        router.put(
            route('groups.members.update', [group.id, member.id]),
            { role },
            { preserveScroll: true },
        );
    };

    const removeMember = (member: Member) => {
        if (!confirm(`Remove ${member.name} from this group?`)) {
            return;
        }
        router.delete(route('groups.members.destroy', [group.id, member.id]), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl leading-tight font-semibold text-gray-800">
                        {group.name}
                    </h2>
                    <Link
                        href={route('groups.index')}
                        className="text-sm font-medium text-gray-500 hover:text-gray-800"
                    >
                        Back to groups
                    </Link>
                </div>
            }
        >
            <Head title={group.name} />

            {showToast && (
                <div className="fixed top-4 right-4 z-50 rounded-md border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 shadow-lg">
                    {flash?.success}
                </div>
            )}

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <p className="mb-6 text-sm text-gray-500">
                        {group.description || 'No description'}
                    </p>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        {/* Calendars */}
                        <div className="lg:col-span-2">
                            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                                <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                                    <h3 className="text-sm font-semibold text-gray-900">
                                        Calendars
                                    </h3>
                                    <Link
                                        href={`/groups/${group.id}/calendars`}
                                        className="text-xs font-medium text-gray-500 hover:text-gray-800"
                                    >
                                        Manage
                                    </Link>
                                </div>
                                {calendars.length > 0 ? (
                                    <ul className="divide-y divide-gray-100">
                                        {calendars.map((calendar) => (
                                            <li key={calendar.id}>
                                                <Link
                                                    href={route(
                                                        'groups.calendars.events.index',
                                                        [group.id, calendar.id],
                                                    )}
                                                    className="block px-6 py-3 text-sm text-gray-700 transition hover:bg-gray-50"
                                                >
                                                    {calendar.name ||
                                                        calendar.title ||
                                                        `Calendar #${calendar.id}`}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="px-6 py-8 text-center text-sm text-gray-500">
                                        No calendars in this group yet.
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Members */}
                        <div>
                            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                                <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                                    <h3 className="text-sm font-semibold text-gray-900">
                                        Members
                                    </h3>
                                    {can_manage && (
                                        <div className="flex items-center gap-3">
                                            <button
                                                onClick={() =>
                                                    setShowBulkAdd(true)
                                                }
                                                className="text-xs font-medium text-gray-500 hover:text-gray-800"
                                            >
                                                Bulk add
                                            </button>
                                            <button
                                                onClick={() =>
                                                    setShowAddMember(true)
                                                }
                                                className="text-xs font-medium text-gray-500 hover:text-gray-800"
                                            >
                                                + Add
                                            </button>
                                        </div>
                                    )}
                                </div>

                                <ul className="divide-y divide-gray-100">
                                    {members.map((member) => (
                                        <li
                                            key={member.id}
                                            className="flex items-center justify-between gap-2 px-6 py-3"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-gray-900">
                                                    {member.name}
                                                </p>
                                                <p className="truncate text-xs text-gray-500">
                                                    {member.email}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                                        member.role === 'admin'
                                                            ? 'bg-amber-50 text-amber-700'
                                                            : 'bg-gray-100 text-gray-600'
                                                    }`}
                                                >
                                                    {member.role ?? 'member'}
                                                </span>
                                                {can_manage && (
                                                    <>
                                                        {is_super_admin && (
                                                            <button
                                                                title="Toggle admin role"
                                                                onClick={() =>
                                                                    changeRole(
                                                                        member,
                                                                        member.role ===
                                                                            'admin'
                                                                            ? 'member'
                                                                            : 'admin',
                                                                    )
                                                                }
                                                                className="text-xs font-medium text-gray-500 hover:text-gray-800"
                                                            >
                                                                {member.role ===
                                                                'admin'
                                                                    ? 'Demote'
                                                                    : 'Promote'}
                                                            </button>
                                                        )}
                                                        {(is_super_admin ||
                                                            member.role !==
                                                                'admin') && (
                                                            <button
                                                                title="Remove member"
                                                                onClick={() =>
                                                                    removeMember(
                                                                        member,
                                                                    )
                                                                }
                                                                className="text-xs font-medium text-red-500 hover:text-red-700"
                                                            >
                                                                Remove
                                                            </button>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                    {members.length === 0 && (
                                        <li className="px-6 py-8 text-center text-sm text-gray-500">
                                            No members yet.
                                        </li>
                                    )}
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Add Member Modal */}
            <Dialog
                open={showAddMember}
                onClose={() => setShowAddMember(false)}
                className="relative z-50"
            >
                <div className="fixed inset-0 bg-black/30" aria-hidden="true" />
                <div className="fixed inset-0 flex items-center justify-center p-4">
                    <DialogPanel className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                        <DialogTitle className="mb-4 text-base font-semibold text-gray-900">
                            Add Member
                        </DialogTitle>
                        <form onSubmit={submitAddMember} className="space-y-4">
                            <div>
                                <label
                                    htmlFor="user_id"
                                    className="mb-1 block text-sm font-medium text-gray-700"
                                >
                                    User ID
                                </label>
                                <input
                                    id="user_id"
                                    type="number"
                                    value={data.user_id}
                                    onChange={(e) =>
                                        setData('user_id', e.target.value)
                                    }
                                    className={`w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none ${
                                        errors.user_id
                                            ? 'border-red-500'
                                            : 'border-gray-300'
                                    }`}
                                />
                                {errors.user_id && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.user_id}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label
                                    htmlFor="role"
                                    className="mb-1 block text-sm font-medium text-gray-700"
                                >
                                    Role
                                </label>
                                <select
                                    id="role"
                                    value={data.role}
                                    onChange={(e) =>
                                        setData('role', e.target.value)
                                    }
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                >
                                    <option value="member">Member</option>
                                    {is_super_admin && (
                                        <option value="admin">Admin</option>
                                    )}
                                </select>
                                {errors.role && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.role}
                                    </p>
                                )}
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowAddMember(false)}
                                    className="flex-1 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:opacity-50"
                                >
                                    {processing ? 'Adding...' : 'Add'}
                                </button>
                            </div>
                        </form>
                    </DialogPanel>
                </div>
            </Dialog>

            {/* Bulk Add Members Modal */}
            <Dialog
                open={showBulkAdd}
                onClose={() => setShowBulkAdd(false)}
                className="relative z-50"
            >
                <div className="fixed inset-0 bg-black/30" aria-hidden="true" />
                <div className="fixed inset-0 flex items-center justify-center p-4">
                    <DialogPanel className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                        <DialogTitle className="mb-1 text-base font-semibold text-gray-900">
                            Bulk Add Members
                        </DialogTitle>
                        <p className="mb-4 text-sm text-gray-500">
                            Paste emails separated by commas, spaces, or one per
                            line. Each person gets an email in the background
                            once added.
                        </p>
                        <form onSubmit={submitBulkAdd} className="space-y-4">
                            <div>
                                <label
                                    htmlFor="emails"
                                    className="mb-1 block text-sm font-medium text-gray-700"
                                >
                                    Emails
                                </label>
                                <textarea
                                    id="emails"
                                    rows={5}
                                    value={bulkForm.data.emails}
                                    onChange={(e) =>
                                        bulkForm.setData(
                                            'emails',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={
                                        'jane@example.com\njohn@example.com'
                                    }
                                    className={`w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none ${
                                        bulkForm.errors.emails
                                            ? 'border-red-500'
                                            : 'border-gray-300'
                                    }`}
                                />
                                {bulkForm.errors.emails && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {bulkForm.errors.emails}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label
                                    htmlFor="bulk_role"
                                    className="mb-1 block text-sm font-medium text-gray-700"
                                >
                                    Role
                                </label>
                                <select
                                    id="bulk_role"
                                    value={bulkForm.data.role}
                                    onChange={(e) =>
                                        bulkForm.setData('role', e.target.value)
                                    }
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                >
                                    <option value="member">Member</option>
                                    {is_super_admin && (
                                        <option value="admin">Admin</option>
                                    )}
                                </select>
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowBulkAdd(false)}
                                    className="flex-1 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={bulkForm.processing}
                                    className="flex-1 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:opacity-50"
                                >
                                    {bulkForm.processing
                                        ? 'Adding...'
                                        : 'Add All'}
                                </button>
                            </div>
                        </form>
                    </DialogPanel>
                </div>
            </Dialog>
        </AuthenticatedLayout>
    );
}
