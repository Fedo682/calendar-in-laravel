import {
    Avatar,
    Badge,
    Button,
    Card,
    Field,
    Input,
    Modal,
    Select,
    Textarea,
} from '@/components/ui';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import type { GroupSummary } from '@/types/calendar';
import { usePageProps } from '@/types/shared';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
import type { FormEventHandler, ReactNode } from 'react';
import { useState } from 'react';

interface GroupProp extends GroupSummary {
    created_at: string;
}

/**
 * The controller sends full Calendar models here - there is no `title`
 * column, so that was always dead code from an earlier shape.
 */
interface Calendar {
    id: number;
    name: string;
    color: string | null;
}

interface Member {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'member' | null;
}

interface Props {
    group: GroupProp;
    calendars: Calendar[];
    members: Member[];
    can_manage: boolean;
    is_group_admin: boolean;
    is_super_admin: boolean;
}

export default function GroupsShow({
    group,
    calendars,
    members,
    can_manage,
    is_super_admin,
}: Props) {
    const [showAddMember, setShowAddMember] = useState(false);
    const [showBulkAdd, setShowBulkAdd] = useState(false);
    const [removing, setRemoving] = useState<Member | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        user_id: '',
        role: 'member',
    });

    const submitAddMember: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('groups.members.store', group.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setShowAddMember(false);
            },
        });
    };

    const bulkForm = useForm({
        emails: '',
        role: 'member',
    });

    const submitBulkAdd: FormEventHandler = (e) => {
        e.preventDefault();
        bulkForm.post(route('groups.members.bulk', group.id), {
            preserveScroll: true,
            onSuccess: () => {
                bulkForm.reset();
                setShowBulkAdd(false);
            },
        });
    };

    const changeRole = (member: Member, role: 'admin' | 'member') => {
        router.put(
            route('groups.members.update', [group.id, member.id]),
            { role },
            { preserveScroll: true },
        );
    };

    const confirmRemoveMember = () => {
        if (!removing) return;

        router.delete(
            route('groups.members.destroy', [group.id, removing.id]),
            {
                preserveScroll: true,
                onSuccess: () => setRemoving(null),
            },
        );
    };

    return (
        <>
            <Head title={group.name} />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <p className="text-content-secondary text-footnote mb-6">
                    {group.description || 'No description'}
                </p>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Calendars */}
                    <div className="lg:col-span-2">
                        <Card material="thin" padded={false}>
                            <div className="border-hairline flex items-center justify-between border-b px-6 py-4">
                                <h3 className="text-headline text-content">
                                    Calendars
                                </h3>
                                <Link
                                    href={`/groups/${group.id}/calendars`}
                                    className="text-content-secondary hover:text-content text-caption1 font-medium"
                                >
                                    Manage
                                </Link>
                            </div>
                            {calendars.length > 0 ? (
                                <ul className="divide-hairline divide-y">
                                    {calendars.map((calendar) => (
                                        <li key={calendar.id}>
                                            <Link
                                                href={route(
                                                    'groups.calendars.events.index',
                                                    [group.id, calendar.id],
                                                )}
                                                className="hover:bg-surface-raised text-footnote flex items-center gap-3 px-6 py-3 transition"
                                            >
                                                <span
                                                    className="size-2.5 shrink-0 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            calendar.color ||
                                                            'var(--event-top)',
                                                    }}
                                                />
                                                <span className="text-content truncate">
                                                    {calendar.name}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-content-secondary text-footnote px-6 py-8 text-center">
                                    No calendars in this group yet.
                                </p>
                            )}
                        </Card>
                    </div>

                    {/* Members */}
                    <div>
                        <Card material="thin" padded={false}>
                            <div className="border-hairline flex items-center justify-between border-b px-6 py-4">
                                <h3 className="text-headline text-content">
                                    Members
                                </h3>
                                {can_manage && (
                                    <div className="flex items-center gap-3">
                                        <button
                                            onClick={() => setShowBulkAdd(true)}
                                            className="text-content-secondary hover:text-content text-caption1 font-medium"
                                        >
                                            Bulk add
                                        </button>
                                        <button
                                            onClick={() =>
                                                setShowAddMember(true)
                                            }
                                            className="text-accent hover:text-accent-hover text-caption1 flex items-center gap-1 font-medium"
                                        >
                                            <Plus className="size-3.5" />
                                            Add
                                        </button>
                                    </div>
                                )}
                            </div>

                            <ul className="divide-hairline divide-y">
                                {members.map((member) => (
                                    <li
                                        key={member.id}
                                        className="flex items-center justify-between gap-2 px-6 py-3"
                                    >
                                        <div className="flex min-w-0 items-center gap-3">
                                            <Avatar
                                                name={member.name}
                                                size="sm"
                                            />
                                            <div className="min-w-0">
                                                <p className="text-content text-footnote truncate font-medium">
                                                    {member.name}
                                                </p>
                                                <p className="text-content-tertiary text-caption1 truncate">
                                                    {member.email}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            <Badge
                                                tone={
                                                    member.role === 'admin'
                                                        ? 'warning'
                                                        : 'neutral'
                                                }
                                            >
                                                {member.role ?? 'member'}
                                            </Badge>
                                            {can_manage && (
                                                <>
                                                    {is_super_admin && (
                                                        <button
                                                            title="Toggle admin role"
                                                            onClick={() =>
                                                                changeRole(
                                                                    member,
                                                                    member.role ===
                                                                        'admin'
                                                                        ? 'member'
                                                                        : 'admin',
                                                                )
                                                            }
                                                            className="text-content-secondary hover:text-content text-caption1 font-medium"
                                                        >
                                                            {member.role ===
                                                            'admin'
                                                                ? 'Demote'
                                                                : 'Promote'}
                                                        </button>
                                                    )}
                                                    {(is_super_admin ||
                                                        member.role !==
                                                            'admin') && (
                                                        <button
                                                            title="Remove member"
                                                            onClick={() =>
                                                                setRemoving(
                                                                    member,
                                                                )
                                                            }
                                                            className="text-danger hover:text-danger text-caption1 font-medium hover:opacity-75"
                                                        >
                                                            Remove
                                                        </button>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </li>
                                ))}
                                {members.length === 0 && (
                                    <li className="text-content-secondary text-footnote px-6 py-8 text-center">
                                        No members yet.
                                    </li>
                                )}
                            </ul>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Add member */}
            <Modal
                open={showAddMember}
                onClose={() => setShowAddMember(false)}
                title="Add member"
                width="sm"
                footer={
                    <>
                        <Button
                            variant="secondary"
                            onClick={() => setShowAddMember(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            form="add-member-form"
                            loading={processing}
                        >
                            Add
                        </Button>
                    </>
                }
            >
                <form
                    id="add-member-form"
                    onSubmit={submitAddMember}
                    className="space-y-4"
                >
                    <Field label="User ID" error={errors.user_id}>
                        <Input
                            id="user_id"
                            type="number"
                            value={data.user_id}
                            onChange={(e) => setData('user_id', e.target.value)}
                            invalid={Boolean(errors.user_id)}
                        />
                    </Field>

                    <Field label="Role" error={errors.role}>
                        <Select
                            id="role"
                            value={data.role}
                            onChange={(e) => setData('role', e.target.value)}
                        >
                            <option value="member">Member</option>
                            {is_super_admin && (
                                <option value="admin">Admin</option>
                            )}
                        </Select>
                    </Field>
                </form>
            </Modal>

            {/* Bulk add members */}
            <Modal
                open={showBulkAdd}
                onClose={() => setShowBulkAdd(false)}
                title="Bulk add members"
                description="Paste emails separated by commas, spaces, or one per line. Each person gets an email in the background once added."
                width="md"
                footer={
                    <>
                        <Button
                            variant="secondary"
                            onClick={() => setShowBulkAdd(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            form="bulk-add-form"
                            loading={bulkForm.processing}
                        >
                            Add all
                        </Button>
                    </>
                }
            >
                <form
                    id="bulk-add-form"
                    onSubmit={submitBulkAdd}
                    className="space-y-4"
                >
                    <Field label="Emails" error={bulkForm.errors.emails}>
                        <Textarea
                            id="emails"
                            rows={5}
                            value={bulkForm.data.emails}
                            onChange={(e) =>
                                bulkForm.setData('emails', e.target.value)
                            }
                            placeholder={'jane@example.com\njohn@example.com'}
                            invalid={Boolean(bulkForm.errors.emails)}
                        />
                    </Field>

                    <Field label="Role">
                        <Select
                            id="bulk_role"
                            value={bulkForm.data.role}
                            onChange={(e) =>
                                bulkForm.setData('role', e.target.value)
                            }
                        >
                            <option value="member">Member</option>
                            {is_super_admin && (
                                <option value="admin">Admin</option>
                            )}
                        </Select>
                    </Field>
                </form>
            </Modal>

            {/* Remove member confirmation */}
            <Modal
                open={removing !== null}
                onClose={() => setRemoving(null)}
                title="Remove member"
                description={
                    removing
                        ? `Remove ${removing.name} from this group?`
                        : undefined
                }
                width="sm"
                footer={
                    <>
                        <Button
                            variant="secondary"
                            onClick={() => setRemoving(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={confirmRemoveMember}
                        >
                            Remove
                        </Button>
                    </>
                }
            />
        </>
    );
}

function GroupHeading() {
    const { group, can_manage } = usePageProps<Props>();

    return (
        <div className="flex items-center justify-between gap-4">
            <h2 className="text-headline text-chrome-content flex items-center gap-2">
                <Users className="size-4 opacity-70" />
                {group.name}
            </h2>
            <div className="flex items-center gap-4">
                {can_manage && (
                    <Link
                        href={`/groups/${group.id}/messages`}
                        className="text-chrome-content/70 hover:text-chrome-content text-footnote font-medium"
                    >
                        Messages
                    </Link>
                )}
                <Link
                    href={route('groups.index')}
                    className="text-chrome-content/70 hover:text-chrome-content text-footnote font-medium"
                >
                    Back to groups
                </Link>
            </div>
        </div>
    );
}

GroupsShow.layout = (page: ReactNode) => (
    <AuthenticatedLayout header={<GroupHeading />}>{page}</AuthenticatedLayout>
);
