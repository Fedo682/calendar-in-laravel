import { Badge, Card, EmptyState, LinkButton } from '@/components/ui';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { CalendarSummary } from '@/types/calendar';
import { Head, Link } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
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

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <p className="text-content-secondary text-footnote mb-6">
                    Your own calendar, and every calendar across the groups you
                    belong to.
                </p>

                {calendars.length > 0 ? (
                    <Card material="thin" padded={false}>
                        <ul className="divide-hairline divide-y">
                            {calendars.map((calendar) => (
                                <li key={calendar.id}>
                                    <Link
                                        href={calendarHref(calendar)}
                                        className="hover:bg-surface-raised flex items-center justify-between gap-4 px-6 py-4 transition"
                                    >
                                        <div className="flex min-w-0 items-center gap-3">
                                            <span
                                                className="size-2.5 shrink-0 rounded-full"
                                                style={{
                                                    backgroundColor:
                                                        calendar.color ||
                                                        'var(--event-top)',
                                                }}
                                            />
                                            <div className="min-w-0">
                                                <p className="text-content text-footnote truncate font-medium">
                                                    {calendar.name}
                                                </p>
                                                <p className="text-content-tertiary text-caption1 truncate">
                                                    {calendar.description ||
                                                        'No description'}
                                                </p>
                                            </div>
                                        </div>
                                        <Badge>
                                            {calendar.group?.name ?? 'Personal'}
                                        </Badge>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                ) : (
                    <EmptyState
                        icon={CalendarDays}
                        title="No calendars yet"
                        description="Calendars created within your groups will show up here."
                        action={
                            <LinkButton href="/groups">
                                View your groups
                            </LinkButton>
                        }
                    />
                )}
            </div>
        </>
    );
}

CalendarsOverview.layout = (page: ReactNode) => (
    <AuthenticatedLayout
        header={
            <h2 className="text-headline text-chrome-content">Calendars</h2>
        }
    >
        {page}
    </AuthenticatedLayout>
);
