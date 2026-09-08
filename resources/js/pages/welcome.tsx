import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Welcome to Group Calendar" />

            <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
                {/* Navigation */}
                <nav className="bg-white shadow">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                        <div className="flex items-center gap-2">
                            <span className="text-3xl">📅</span>
                            <h1 className="text-2xl font-bold text-indigo-600">GroupSync Calendar</h1>
                        </div>
                        <div className="flex gap-4">
                            <a href="/login" className="px-4 py-2 text-indigo-600 hover:text-indigo-800 font-medium">
                                Login
                            </a>
                            <a href="/register" className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                                Sign Up
                            </a>
                        </div>
                    </div>
                </nav>

                {/* Hero Section */}
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                    <div className="text-center mb-16">
                        <h2 className="text-5xl font-bold text-gray-900 mb-6">
                            Manage Group Calendars Effortlessly
                        </h2>
                        <p className="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                            Coordinate with multiple groups, sync events in real-time, and never miss an important date again.
                        </p>
                        <div className="flex gap-4 justify-center">
                            <a href="/register" className="px-8 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-semibold text-lg transition">
                                Get Started
                            </a>
                            <a href="#features" className="px-8 py-3 border-2 border-indigo-600 text-indigo-600 rounded-lg hover:bg-indigo-50 font-semibold text-lg transition">
                                Learn More
                            </a>
                        </div>
                    </div>

                    {/* Features Section */}
                    <div id="features" className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 py-16">
                        {/* Feature 1 */}
                        <div className="bg-white rounded-lg shadow-lg p-8 hover:shadow-xl transition">
                            <div className="text-4xl text-center mb-4">📅</div>
                            <h3 className="text-lg font-bold text-gray-900 text-center mb-2">
                                Multiple Calendars
                            </h3>
                            <p className="text-gray-600 text-center">
                                Manage calendars for different groups all in one place
                            </p>
                        </div>

                        {/* Feature 2 */}
                        <div className="bg-white rounded-lg shadow-lg p-8 hover:shadow-xl transition">
                            <div className="text-4xl text-center mb-4">👥</div>
                            <h3 className="text-lg font-bold text-gray-900 text-center mb-2">
                                Group Management
                            </h3>
                            <p className="text-gray-600 text-center">
                                Create, organize, and manage multiple groups with ease
                            </p>
                        </div>

                        {/* Feature 3 */}
                        <div className="bg-white rounded-lg shadow-lg p-8 hover:shadow-xl transition">
                            <div className="text-4xl text-center mb-4">🔗</div>
                            <h3 className="text-lg font-bold text-gray-900 text-center mb-2">
                                Instant Sharing
                            </h3>
                            <p className="text-gray-600 text-center">
                                Share events and invite group members with one click
                            </p>
                        </div>

                        {/* Feature 4 */}
                        <div className="bg-white rounded-lg shadow-lg p-8 hover:shadow-xl transition">
                            <div className="text-4xl text-center mb-4">⚡</div>
                            <h3 className="text-lg font-bold text-gray-900 text-center mb-2">
                                Real-time Sync
                            </h3>
                            <p className="text-gray-600 text-center">
                                All events updated instantly across all group members
                            </p>
                        </div>
                    </div>

                    {/* CTA Section */}
                    <div className="bg-indigo-600 rounded-lg shadow-lg p-12 text-center mt-16">
                        <h3 className="text-3xl font-bold text-white mb-4">
                            Ready to simplify your scheduling?
                        </h3>
                        <p className="text-indigo-100 mb-8 text-lg">
                            Join thousands of teams staying organized with GroupSync Calendar
                        </p>
                        <a href="/register" className="inline-block px-8 py-3 bg-white text-indigo-600 rounded-lg hover:bg-gray-100 font-semibold text-lg transition">
                            Start Free Today
                        </a>
                    </div>
                </div>

                {/* Footer */}
                <footer className="bg-white border-t border-gray-200 mt-16">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-center text-gray-600">
                        <p>&copy; 2026 GroupSync Calendar. All rights reserved.</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
