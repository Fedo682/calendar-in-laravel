import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Group {
    id: number;
    name: string;
    description: string | null;
    created_at: string;
    members_count?: number;
}

interface Props {
    groups: Group[];
}

export default function GroupsIndex({ groups }: Props) {
    const { flash, auth } = usePage().props as any;
    const isSuperAdmin = Boolean(auth?.is_super_admin);
    const [showToast, setShowToast] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            setShowToast(true);
            const timer = setTimeout(() => setShowToast(false), 3000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl leading-tight font-semibold text-gray-800">
                    {isSuperAdmin ? 'All Groups' : 'My Groups'}
                </h2>
            }
        >
            <Head title={isSuperAdmin ? 'All Groups' : 'My Groups'} />

            {showToast && (
                <div className="fixed top-4 right-4 z-50 rounded-md border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 shadow-lg">
                    {flash?.success}
                </div>
            )}

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex items-center justify-between">
                        <p className="text-sm text-gray-500">
                            {isSuperAdmin
                                ? 'Every group on the platform.'
                                : 'Groups you belong to.'}
                        </p>
                        {isSuperAdmin && (
                            <Link
                                href={route('groups.create')}
                                className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition hover:bg-gray-700"
                            >
                                New Group
                            </Link>
                        )}
                    </div>

                    {groups.length > 0 ? (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {groups.map((group) => (
                                <Link
                                    key={group.id}
                                    href={route('groups.show', group.id)}
                                    className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition hover:border-gray-300 hover:shadow-md"
                                >
                                    <h3 className="mb-1 text-base font-semibold text-gray-900">
                                        {group.name}
                                    </h3>
                                    <p className="mb-4 min-h-10 text-sm text-gray-500">
                                        {group.description || 'No description'}
                                    </p>
                                    {typeof group.members_count ===
                                        'number' && (
                                        <p className="text-xs font-medium text-gray-400">
                                            {group.members_count}{' '}
                                            {group.members_count === 1
                                                ? 'member'
                                                : 'members'}
                                        </p>
                                    )}
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                            <h3 className="text-sm font-semibold text-gray-900">
                                No groups yet
                            </h3>
                            <p className="mt-1 text-sm text-gray-500">
                                {isSuperAdmin
                                    ? 'Create your first group to get started.'
                                    : 'You are not a member of any groups yet. Ask a group admin to add you.'}
                            </p>
                            {isSuperAdmin && (
                                <Link
                                    href={route('groups.create')}
                                    className="mt-4 inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition hover:bg-gray-700"
                                >
                                    Create Your First Group
                                </Link>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
