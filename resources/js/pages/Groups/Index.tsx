import { EmptyState, LinkButton } from '@/components/ui';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { GroupSummary } from '@/types/calendar';
import { usePageProps } from '@/types/shared';
import { Head, Link } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
import type { ReactNode } from 'react';

interface Group extends GroupSummary {
    created_at: string;
    members_count?: number;
}

interface Props {
    groups: Group[];
}

export default function GroupsIndex({ groups }: Props) {
    const { auth } = usePageProps();
    const isSuperAdmin = Boolean(auth?.is_super_admin);

    return (
        <>
            <Head title={isSuperAdmin ? 'All Groups' : 'My Groups'} />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6 flex items-center justify-between">
                    <p className="text-content-secondary text-footnote">
                        {isSuperAdmin
                            ? 'Every group on the platform.'
                            : 'Groups you belong to.'}
                    </p>
                    {isSuperAdmin && (
                        <LinkButton href={route('groups.create')} icon={Plus}>
                            New group
                        </LinkButton>
                    )}
                </div>

                {groups.length > 0 ? (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {groups.map((group) => (
                            <Link
                                key={group.id}
                                href={route('groups.show', group.id)}
                                className="material-thin rounded-card ease-hig duration-base hover:border-accent/40 hover:bg-surface-raised block p-5 transition hover:-translate-y-0.5"
                            >
                                <h3 className="text-headline text-content mb-1">
                                    {group.name}
                                </h3>
                                <p className="text-content-secondary text-footnote mb-4 min-h-10">
                                    {group.description || 'No description'}
                                </p>
                                {typeof group.members_count === 'number' && (
                                    <p className="text-content-tertiary text-caption1 font-medium">
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
                    <EmptyState
                        icon={Users}
                        title="No groups yet"
                        description={
                            isSuperAdmin
                                ? 'Create your first group to get started.'
                                : 'You are not a member of any groups yet. Ask a group admin to add you.'
                        }
                        action={
                            isSuperAdmin ? (
                                <LinkButton
                                    href={route('groups.create')}
                                    icon={Plus}
                                >
                                    Create your first group
                                </LinkButton>
                            ) : undefined
                        }
                    />
                )}
            </div>
        </>
    );
}

function GroupsHeading() {
    const { auth } = usePageProps();

    return (
        <h2 className="text-headline text-chrome-content">
            {auth?.is_super_admin ? 'All Groups' : 'My Groups'}
        </h2>
    );
}

GroupsIndex.layout = (page: ReactNode) => (
    <AuthenticatedLayout header={<GroupsHeading />}>{page}</AuthenticatedLayout>
);
