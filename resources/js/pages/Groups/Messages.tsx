import { Badge, Button, Card, EmptyState } from '@/components/ui';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { usePageProps } from '@/types/shared';
import { Head, Link, router } from '@inertiajs/react';
import { Inbox, MessageSquare, TriangleAlert, Users } from 'lucide-react';
import type { ReactNode } from 'react';

interface GroupSummary {
    id: number;
    name: string;
}

interface MessageRow {
    id: number;
    sender_name: string;
    sender_email: string;
    body: string;
    conflicting_titles: string[] | null;
    occurrence_start: string;
    created_at: string;
    resolved: boolean;
    event_title: string;
    calendar_name: string;
    event_url: string;
}

interface Props {
    group: GroupSummary;
    messages: MessageRow[];
    reports: MessageRow[];
}

function formatWhen(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function toggleResolved(groupId: number, row: MessageRow) {
    router.patch(
        `/groups/${groupId}/messages/${row.id}`,
        { resolved: !row.resolved },
        { preserveScroll: true },
    );
}

function MessageList({
    groupId,
    rows,
    emptyLabel,
}: {
    groupId: number;
    rows: MessageRow[];
    emptyLabel: string;
}) {
    if (rows.length === 0) {
        return (
            <div className="px-4 py-8">
                <EmptyState icon={Inbox} title={emptyLabel} />
            </div>
        );
    }

    return (
        <ul className="divide-hairline divide-y">
            {rows.map((row) => (
                <li key={row.id} className="px-4 py-3">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="text-content text-footnote font-medium">
                                    {row.sender_name}
                                </p>
                                <span className="text-content-tertiary text-caption1">
                                    {row.sender_email}
                                </span>
                                <Badge
                                    tone={row.resolved ? 'success' : 'warning'}
                                >
                                    {row.resolved ? 'Resolved' : 'Unresolved'}
                                </Badge>
                            </div>

                            <p className="text-content-secondary text-caption1 mt-0.5">
                                <Link
                                    href={row.event_url}
                                    className="text-accent hover:underline"
                                >
                                    {row.event_title}
                                </Link>
                                {' · '}
                                {row.calendar_name}
                                {' · '}
                                {formatWhen(row.occurrence_start)}
                            </p>

                            {row.conflicting_titles &&
                            row.conflicting_titles.length > 0 ? (
                                <p className="text-danger text-footnote mt-2">
                                    Overlaps:{' '}
                                    {row.conflicting_titles.join(', ')}
                                </p>
                            ) : null}

                            {row.body && (
                                <p className="text-content text-footnote mt-2 whitespace-pre-line">
                                    {row.body}
                                </p>
                            )}

                            <p className="text-content-tertiary text-caption2 mt-1">
                                Sent {formatWhen(row.created_at)}
                            </p>
                        </div>

                        <Button
                            variant="ghost"
                            size="sm"
                            className="shrink-0"
                            onClick={() => toggleResolved(groupId, row)}
                        >
                            {row.resolved ? 'Reopen' : 'Mark resolved'}
                        </Button>
                    </div>
                </li>
            ))}
        </ul>
    );
}

export default function GroupMessages({ group, messages, reports }: Props) {
    return (
        <>
            <Head title={`Messages - ${group.name}`} />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <Card material="thin" padded={false}>
                    <div className="border-hairline flex items-center gap-2 border-b px-6 py-4">
                        <MessageSquare className="text-content-secondary size-4" />
                        <h3 className="text-headline text-content">Messages</h3>
                    </div>
                    <MessageList
                        groupId={group.id}
                        rows={messages}
                        emptyLabel="No messages from members yet."
                    />
                </Card>

                <Card material="thin" padded={false}>
                    <div className="border-hairline flex items-center gap-2 border-b px-6 py-4">
                        <TriangleAlert className="text-content-secondary size-4" />
                        <h3 className="text-headline text-content">Reports</h3>
                    </div>
                    <MessageList
                        groupId={group.id}
                        rows={reports}
                        emptyLabel="No conflicts reported."
                    />
                </Card>
            </div>
        </>
    );
}

function MessagesHeading() {
    const { group } = usePageProps<Props>();

    return (
        <div className="flex items-center justify-between gap-4">
            <h2 className="text-headline text-chrome-content flex items-center gap-2">
                <Users className="size-4 opacity-70" />
                {group.name} - Messages
            </h2>
            <Link
                href={`/groups/${group.id}`}
                className="text-chrome-content/70 hover:text-chrome-content text-footnote font-medium"
            >
                Back to group
            </Link>
        </div>
    );
}

GroupMessages.layout = (page: ReactNode) => (
    <AuthenticatedLayout header={<MessagesHeading />}>
        {page}
    </AuthenticatedLayout>
);
