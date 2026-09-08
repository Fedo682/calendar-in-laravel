import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Welcome Card */}
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6 text-gray-900">
                                <h3 className="text-lg font-semibold mb-2">Welcome! 👋</h3>
                                <p>You're logged in and ready to manage your calendar groups.</p>
                            </div>
                        </div>

                        {/* Groups Card */}
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg hover:shadow-lg transition">
                            <div className="p-6">
                                <h3 className="text-lg font-semibold text-gray-900 mb-2">📅 My Groups</h3>
                                <p className="text-gray-600 mb-4">View and manage all your calendar groups</p>
                                <Link
                                    href="/groups"
                                    className="inline-block px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium transition"
                                >
                                    Go to Groups →
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
