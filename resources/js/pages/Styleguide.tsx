import {
    Avatar,
    Badge,
    Breadcrumbs,
    Button,
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
    Checkbox,
    ColorSwatchPicker,
    EmptyState,
    Field,
    Input,
    Menu,
    MenuAction,
    MenuDivider,
    MenuLabel,
    Modal,
    PageHeader,
    SegmentedControl,
    Select,
    Sheet,
    Skeleton,
    SkeletonText,
    Spinner,
    Switch,
    Tabs,
    Textarea,
    Tooltip,
    toast,
} from '@/components/ui';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { CalendarDays, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

/**
 * Every primitive, in every variant, on one page.
 *
 * This is the visual QA surface for the phase that converts the app's pages
 * over: a change to a token shows up here in both appearances at once,
 * instead of being discovered one page at a time. Local environment only -
 * the route 404s elsewhere, so it is not a surface to secure.
 */

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="mb-10">
            <h2 className="text-title3 text-content font-semibold">{title}</h2>
            {description && (
                <p className="text-footnote text-content-secondary mt-1">
                    {description}
                </p>
            )}
            <div className="mt-4">{children}</div>
        </section>
    );
}

function Row({ children }: { children: ReactNode }) {
    return <div className="flex flex-wrap items-center gap-3">{children}</div>;
}

export default function Styleguide() {
    const [modalOpen, setModalOpen] = useState(false);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [checked, setChecked] = useState(true);
    const [switched, setSwitched] = useState(false);
    const [segment, setSegment] = useState('month');
    const [tab, setTab] = useState('upcoming');
    const [color, setColor] = useState('#0a84ff');

    return (
        <>
            <Head title="Styleguide" />

            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
                <PageHeader
                    title="Styleguide"
                    description="Every primitive, in every variant. Switch appearance from the avatar menu to check both."
                />

                <Section
                    title="Typography"
                    description="The iOS text styles, by role rather than by size."
                >
                    <div className="space-y-1">
                        <p className="text-largetitle text-content">
                            Large title
                        </p>
                        <p className="text-title1 text-content">Title 1</p>
                        <p className="text-title2 text-content">Title 2</p>
                        <p className="text-title3 text-content">Title 3</p>
                        <p className="text-headline text-content">Headline</p>
                        <p className="text-body text-content">Body</p>
                        <p className="text-subhead text-content-secondary">
                            Subhead
                        </p>
                        <p className="text-footnote text-content-secondary">
                            Footnote
                        </p>
                        <p className="text-caption1 text-content-tertiary">
                            Caption 1
                        </p>
                        <p className="text-caption2 text-content-tertiary">
                            Caption 2
                        </p>
                    </div>
                </Section>

                <Section
                    title="Materials"
                    description="Four thicknesses, chrome only. Grid cells and list rows get flat surfaces - backdrop-filter per cell is what makes a month view stutter."
                >
                    <div className="canvas-wash rounded-card grid grid-cols-2 gap-3 p-4 sm:grid-cols-4">
                        {(
                            ['ultrathin', 'thin', 'regular', 'thick'] as const
                        ).map((thickness) => (
                            <div
                                key={thickness}
                                className={`material-${thickness} rounded-card text-caption1 text-content p-4`}
                            >
                                {thickness}
                            </div>
                        ))}
                    </div>
                </Section>

                <Section title="Buttons">
                    <div className="space-y-3">
                        <Row>
                            <Button variant="primary">Primary</Button>
                            <Button variant="secondary">Secondary</Button>
                            <Button variant="ghost">Ghost</Button>
                            <Button variant="destructive">Destructive</Button>
                            <Button variant="glass">Glass</Button>
                        </Row>
                        <Row>
                            <Button size="sm">Small</Button>
                            <Button size="md">Medium</Button>
                            <Button size="lg">Large</Button>
                            <Button size="icon" aria-label="Add">
                                <Plus className="size-4" />
                            </Button>
                        </Row>
                        <Row>
                            <Button icon={Plus}>With icon</Button>
                            <Button icon={Trash2} variant="destructive">
                                Delete
                            </Button>
                            <Button loading>Loading</Button>
                            <Button disabled>Disabled</Button>
                        </Row>
                        <Button fullWidth>Full width</Button>
                    </div>
                </Section>

                <Section title="Cards">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Card material="thin">
                            <CardHeader>
                                <CardTitle>Thin</CardTitle>
                                <CardDescription>
                                    The default card material.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                        <Card material="flat">
                            <CardHeader>
                                <CardTitle>Flat</CardTitle>
                                <CardDescription>
                                    No blur. For dense lists.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                        <Card material="thin" glow interactive>
                            <CardHeader>
                                <CardTitle>Interactive</CardTitle>
                                <CardDescription>
                                    Glows on hover.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </div>
                </Section>

                <Section title="Form controls">
                    <div className="max-w-md space-y-4">
                        <Field label="Title" hint="Shown on the calendar.">
                            <Input placeholder="Weekly standup" />
                        </Field>
                        <Field
                            label="Title"
                            error="The title field is required."
                        >
                            <Input invalid placeholder="Weekly standup" />
                        </Field>
                        <Field label="Notes">
                            <Textarea rows={3} placeholder="Optional" />
                        </Field>
                        <Field label="Visibility">
                            <Select defaultValue="public">
                                <option value="public">Public</option>
                                <option value="private">Private</option>
                                <option value="busy">Show as busy</option>
                            </Select>
                        </Field>
                        <Checkbox
                            checked={checked}
                            onChange={(e) => setChecked(e.target.checked)}
                            label="All day"
                        />
                        <Switch
                            checked={switched}
                            onChange={setSwitched}
                            label="Send reminders"
                        />
                        <Field label="Calendar colour">
                            <ColorSwatchPicker
                                value={color}
                                onChange={setColor}
                                label="Calendar colour"
                            />
                        </Field>
                    </div>
                </Section>

                <Section title="Navigation">
                    <div className="space-y-4">
                        <Breadcrumbs
                            items={[
                                { label: 'Groups', href: '#' },
                                { label: 'Engineering', href: '#' },
                                { label: 'Team calendar' },
                            ]}
                        />
                        <SegmentedControl
                            value={segment}
                            onChange={setSegment}
                            label="Calendar view"
                            options={[
                                { value: 'day', label: 'Day' },
                                { value: 'week', label: 'Week' },
                                { value: 'month', label: 'Month' },
                            ]}
                        />
                        <Tabs
                            value={tab}
                            onChange={setTab}
                            label="Event range"
                            tabs={[
                                { value: 'upcoming', label: 'Upcoming' },
                                { value: 'past', label: 'Past' },
                                { value: 'all', label: 'All' },
                            ]}
                        />
                        <Menu
                            trigger={<Button variant="secondary">Menu</Button>}
                        >
                            <MenuLabel>Actions</MenuLabel>
                            <MenuAction onClick={() => undefined} icon={Plus}>
                                New event
                            </MenuAction>
                            <MenuDivider />
                            <MenuAction
                                onClick={() => undefined}
                                icon={Trash2}
                                destructive
                            >
                                Delete calendar
                            </MenuAction>
                        </Menu>
                    </div>
                </Section>

                <Section title="Overlays">
                    <Row>
                        <Button onClick={() => setModalOpen(true)}>
                            Open modal
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() => setSheetOpen(true)}
                        >
                            Open sheet
                        </Button>
                        <Button
                            variant="ghost"
                            onClick={() =>
                                toast.success('Event created successfully.')
                            }
                        >
                            Success toast
                        </Button>
                        <Button
                            variant="ghost"
                            onClick={() =>
                                toast.error('That timezone is not recognised.')
                            }
                        >
                            Error toast
                        </Button>
                        <Tooltip label="Shown on hover and on focus">
                            <Button variant="ghost">Tooltip</Button>
                        </Tooltip>
                    </Row>
                </Section>

                <Section title="Status and feedback">
                    <div className="space-y-4">
                        <Row>
                            <Badge>Neutral</Badge>
                            <Badge tone="accent">Accent</Badge>
                            <Badge tone="success">Success</Badge>
                            <Badge tone="warning">Warning</Badge>
                            <Badge tone="danger">Danger</Badge>
                        </Row>
                        <Row>
                            <Avatar name="Ada Lovelace" size="sm" />
                            <Avatar name="Grace Hopper" size="md" />
                            <Avatar name="Alan Turing" size="lg" />
                            <Spinner size="sm" />
                            <Spinner size="md" />
                        </Row>
                        <div className="max-w-sm space-y-2">
                            <Skeleton className="h-8 w-full" />
                            <SkeletonText lines={3} />
                        </div>
                        <EmptyState
                            icon={CalendarDays}
                            title="No events yet"
                            description="Nothing is scheduled in this range."
                            action={<Button icon={Plus}>New event</Button>}
                        />
                    </div>
                </Section>

                <Modal
                    open={modalOpen}
                    onClose={() => setModalOpen(false)}
                    title="Delete calendar"
                    description="Its events go with it. This cannot be undone."
                    footer={
                        <>
                            <Button
                                variant="secondary"
                                onClick={() => setModalOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => setModalOpen(false)}
                            >
                                Delete
                            </Button>
                        </>
                    }
                >
                    <p className="text-body text-content-secondary">
                        Modals use the thickest material, because everything
                        behind them is out of play.
                    </p>
                </Modal>

                <Sheet
                    open={sheetOpen}
                    onClose={() => setSheetOpen(false)}
                    title="New event"
                >
                    <p className="text-body text-content-secondary">
                        A sheet is the same content rising from the bottom edge,
                        which is what a modal should be on a phone.
                    </p>
                </Sheet>
            </div>
        </>
    );
}

Styleguide.layout = (page: ReactNode) => (
    <AuthenticatedLayout
        header={<h2 className="text-headline text-content">Styleguide</h2>}
    >
        {page}
    </AuthenticatedLayout>
);
