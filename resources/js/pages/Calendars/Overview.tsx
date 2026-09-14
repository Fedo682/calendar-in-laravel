import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { CalendarSummary } from '@/types/calendar';
import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface Props {
    calendars: CalendarSummary[];
}

/**
 * Where a calendar lives.
 *
 * A personal calendar belongs to one user and has no group, so there is no
 * group-nested route to send them to - it has its own flat one.
 */
function calendarHref(calendar: CalendarSummary): string {
    return calendar.group === null
        ? '/calendars/personal'
        : `/groups/${calendar.group.id}/calendars/${calendar.id}`;
}

export default function CalendarsOverview({ calendars }: Props) {
    return (
        <>
            <Head title="Calendars" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <p className="mb-6 text-sm text-gray-500">
                        Your own calendar, and every calendar across the groups
                        you belong to.
                    </p>

                    {calendars.length > 0 ? (
                        <div className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <ul className="divide-y divide-gray-200">
                                {calendars.map((calendar) => (
                                    <li key={calendar.id}>
                                        <Link
                                            href={calendarHref(calendar)}
                                            className="flex items-center justify-between gap-4 px-6 py-4 transition hover:bg-gray-50"
                                        >
                                            <div className="flex min-w-0 items-center gap-3">
                                                <span
                                                    className="h-2.5 w-2.5 shrink-0 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            calendar.color ||
                                                            '#6366f1',
                                                    }}
                                                />
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-gray-900">
                                                        {calendar.name}
                                                    </p>
                                                    <p className="truncate text-xs text-gray-500">
                                                        {calendar.description ||
                                                            'No description'}
                                                    </p>
                                                </div>
                                            </div>
                                            <span className="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                                                {calendar.group?.name ??
                                                    'Personal'}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                            <h3 className="text-sm font-semibold text-gray-900">
                                No calendars yet
                            </h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Calendars created within your groups will show
                                up here.
                            </p>
                            <Link
                                href="/groups"
                                className="mt-4 inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition hover:bg-gray-700"
                            >
                                View your groups
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

CalendarsOverview.layout = (page: ReactNode) => (
    <AuthenticatedLayout
        header={
            <h2 className="text-xl leading-tight font-semibold text-gray-800">
                Calendars
            </h2>
        }
    >
        {page}
    </AuthenticatedLayout>
);
