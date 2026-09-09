import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { auth } = usePage().props;
    const isSuperAdmin = Boolean(auth?.is_super_admin);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Welcome back, {auth?.user?.name}
                        </h3>
                        <p className="mt-1 text-sm text-gray-500">
                            {isSuperAdmin
                                ? "You're a Super Admin — you can create groups and assign group admins."
                                : "Here's a quick way into your groups and calendars."}
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <Link
                            href="/groups"
                            className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition hover:border-gray-300 hover:shadow-md"
                        >
                            <h3 className="mb-1 text-base font-semibold text-gray-900">
                                Groups
                            </h3>
                            <p className="text-sm text-gray-500">
                                {isSuperAdmin
                                    ? 'Manage every group in the system'
                                    : 'View the groups you belong to'}
                            </p>
                        </Link>

                        <Link
                            href="/calendars"
                            className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition hover:border-gray-300 hover:shadow-md"
                        >
                            <h3 className="mb-1 text-base font-semibold text-gray-900">
                                Calendars
                            </h3>
                            <p className="text-sm text-gray-500">
                                Browse every calendar you have access to
                            </p>
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
