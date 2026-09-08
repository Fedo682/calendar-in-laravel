import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Group {
    id: number;
    name: string;
    description: string | null;
    created_at: string;
}

interface Props {
    groups: Group[];
}

export default function GroupsIndex({ groups }: Props) {
    const { flash } = usePage().props as any;
    const [showToast, setShowToast] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            setShowToast(true);
            const timer = setTimeout(() => setShowToast(false), 3000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    return (
        <>
            <Head title="My Groups" />

            {/* Success Toast */}
            {showToast && (
                <div className="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg animate-pulse z-50">
                    ✓ {flash?.success}
                </div>
            )}

            <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4">
                <div className="max-w-6xl mx-auto">
                    {/* Header */}
                    <div className="mb-8 flex justify-between items-center">
                        <div>
                            <h1 className="text-4xl font-bold text-gray-900 mb-2">
                                My Groups
                            </h1>
                            <p className="text-gray-600">
                                Manage all your calendar groups in one place
                            </p>
                        </div>
                        <Link
                            href="/groups/create"
                            className="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-semibold transition"
                        >
                            + Create Group
                        </Link>
                    </div>

                    {/* Groups List */}
                    {groups.length > 0 ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {groups.map((group) => (
                                <div
                                    key={group.id}
                                    className="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition"
                                >
                                    <h3 className="text-xl font-bold text-gray-900 mb-2">
                                        {group.name}
                                    </h3>
                                    <p className="text-gray-600 mb-4 min-h-12">
                                        {group.description || 'No description'}
                                    </p>
                                    <div className="flex gap-3">
                                        <Link
                                            href={`/groups/${group.id}`}
                                            className="flex-1 px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-center font-medium transition"
                                        >
                                            View
                                        </Link>
                                        <button className="flex-1 px-4 py-2 border-2 border-indigo-600 text-indigo-600 rounded hover:bg-indigo-50 font-medium transition">
                                            Edit
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="bg-white rounded-lg shadow-lg p-12 text-center">
                            <div className="text-6xl mb-4">📭</div>
                            <h2 className="text-2xl font-bold text-gray-900 mb-2">
                                No Groups Yet
                            </h2>
                            <p className="text-gray-600 mb-6">
                                Create your first group to get started organizing your calendar
                            </p>
                            <Link
                                href="/groups/create"
                                className="inline-block px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-semibold transition"
                            >
                                Create Your First Group
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
