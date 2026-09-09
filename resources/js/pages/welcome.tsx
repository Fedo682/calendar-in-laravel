import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Welcome to Group Calendar" />

            <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
                {/* Navigation */}
                <nav className="bg-white shadow">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-2">
                            <span className="text-3xl">📅</span>
                            <h1 className="text-2xl font-bold text-indigo-600">
                                GroupSync Calendar
                            </h1>
                        </div>
                        <div className="flex gap-4">
                            <a
                                href="/login"
                                className="px-4 py-2 font-medium text-indigo-600 hover:text-indigo-800"
                            >
                                Login
                            </a>
                            <a
                                href="/register"
                                className="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700"
                            >
                                Sign Up
                            </a>
                        </div>
                    </div>
                </nav>

                {/* Hero Section */}
                <div className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                    <div className="mb-16 text-center">
                        <h2 className="mb-6 text-5xl font-bold text-gray-900">
                            Manage Group Calendars Effortlessly
                        </h2>
                        <p className="mx-auto mb-8 max-w-2xl text-xl text-gray-600">
                            Coordinate with multiple groups, sync events in
                            real-time, and never miss an important date again.
                        </p>
                        <div className="flex justify-center gap-4">
                            <a
                                href="/register"
                                className="rounded-lg bg-indigo-600 px-8 py-3 text-lg font-semibold text-white transition hover:bg-indigo-700"
                            >
                                Get Started
                            </a>
                            <a
                                href="#features"
                                className="rounded-lg border-2 border-indigo-600 px-8 py-3 text-lg font-semibold text-indigo-600 transition hover:bg-indigo-50"
                            >
                                Learn More
                            </a>
                        </div>
                    </div>

                    {/* Features Section */}
                    <div
                        id="features"
                        className="grid grid-cols-1 gap-8 py-16 md:grid-cols-2 lg:grid-cols-4"
                    >
                        {/* Feature 1 */}
                        <div className="rounded-lg bg-white p-8 shadow-lg transition hover:shadow-xl">
                            <div className="mb-4 text-center text-4xl">📅</div>
                            <h3 className="mb-2 text-center text-lg font-bold text-gray-900">
                                Multiple Calendars
                            </h3>
                            <p className="text-center text-gray-600">
                                Manage calendars for different groups all in one
                                place
                            </p>
                        </div>

                        {/* Feature 2 */}
                        <div className="rounded-lg bg-white p-8 shadow-lg transition hover:shadow-xl">
                            <div className="mb-4 text-center text-4xl">👥</div>
                            <h3 className="mb-2 text-center text-lg font-bold text-gray-900">
                                Group Management
                            </h3>
                            <p className="text-center text-gray-600">
                                Create, organize, and manage multiple groups
                                with ease
                            </p>
                        </div>

                        {/* Feature 3 */}
                        <div className="rounded-lg bg-white p-8 shadow-lg transition hover:shadow-xl">
                            <div className="mb-4 text-center text-4xl">🔗</div>
                            <h3 className="mb-2 text-center text-lg font-bold text-gray-900">
                                Instant Sharing
                            </h3>
                            <p className="text-center text-gray-600">
                                Share events and invite group members with one
                                click
                            </p>
                        </div>

                        {/* Feature 4 */}
                        <div className="rounded-lg bg-white p-8 shadow-lg transition hover:shadow-xl">
                            <div className="mb-4 text-center text-4xl">⚡</div>
                            <h3 className="mb-2 text-center text-lg font-bold text-gray-900">
                                Real-time Sync
                            </h3>
                            <p className="text-center text-gray-600">
                                All events updated instantly across all group
                                members
                            </p>
                        </div>
                    </div>

                    {/* CTA Section */}
                    <div className="mt-16 rounded-lg bg-indigo-600 p-12 text-center shadow-lg">
                        <h3 className="mb-4 text-3xl font-bold text-white">
                            Ready to simplify your scheduling?
                        </h3>
                        <p className="mb-8 text-lg text-indigo-100">
                            Join thousands of teams staying organized with
                            GroupSync Calendar
                        </p>
                        <a
                            href="/register"
                            className="inline-block rounded-lg bg-white px-8 py-3 text-lg font-semibold text-indigo-600 transition hover:bg-gray-100"
                        >
                            Start Free Today
                        </a>
                    </div>
                </div>

                {/* Footer */}
                <footer className="mt-16 border-t border-gray-200 bg-white">
                    <div className="mx-auto max-w-7xl px-4 py-8 text-center text-gray-600 sm:px-6 lg:px-8">
                        <p>
                            &copy; 2026 GroupSync Calendar. All rights reserved.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
