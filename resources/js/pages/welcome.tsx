import ApplicationLogo from '@/components/ApplicationLogo';
import { Card, LinkButton } from '@/components/ui';
import { Head } from '@inertiajs/react';
import { CalendarDays, Link2, Users, Zap, type LucideIcon } from 'lucide-react';

interface WelcomeProps {
    canLogin: boolean;
    canRegister: boolean;
}

const FEATURES: Array<{
    icon: LucideIcon;
    title: string;
    description: string;
}> = [
    {
        icon: CalendarDays,
        title: 'Multiple calendars',
        description: 'Manage calendars for different groups all in one place.',
    },
    {
        icon: Users,
        title: 'Group management',
        description: 'Create, organize, and manage multiple groups with ease.',
    },
    {
        icon: Link2,
        title: 'Instant sharing',
        description: 'Share events and invite group members with one click.',
    },
    {
        icon: Zap,
        title: 'Real-time sync',
        description: 'Every event updates instantly across all group members.',
    },
];

export default function Welcome({ canLogin, canRegister }: WelcomeProps) {
    return (
        <>
            <Head title="Welcome to Group Calendar" />

            <div className="bg-canvas min-h-screen">
                <nav className="border-hairline border-b">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-2">
                            <ApplicationLogo className="text-accent h-8 w-8 fill-current" />
                            <span className="text-headline text-content font-semibold">
                                GroupSync Calendar
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            {canLogin && (
                                <LinkButton href="/login" variant="ghost">
                                    Log in
                                </LinkButton>
                            )}
                            {canRegister && (
                                <LinkButton href="/register" variant="primary">
                                    Sign up
                                </LinkButton>
                            )}
                        </div>
                    </div>
                </nav>

                <div className="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8">
                    <div className="mb-16 text-center">
                        <h1 className="text-largetitle text-content mb-6 font-bold">
                            Manage group calendars effortlessly
                        </h1>
                        <p className="text-content-secondary text-body mx-auto mb-8 max-w-2xl">
                            Coordinate with multiple groups, see who's busy at a
                            glance, and never miss an important date again.
                        </p>
                        <div className="flex justify-center gap-3">
                            {canRegister && (
                                <LinkButton
                                    href="/register"
                                    variant="primary"
                                    size="lg"
                                >
                                    Get started
                                </LinkButton>
                            )}
                            <LinkButton
                                href="#features"
                                variant="secondary"
                                size="lg"
                            >
                                Learn more
                            </LinkButton>
                        </div>
                    </div>

                    <div
                        id="features"
                        className="grid grid-cols-1 gap-6 py-16 md:grid-cols-2 lg:grid-cols-4"
                    >
                        {FEATURES.map(({ icon: Icon, title, description }) => (
                            <Card key={title} material="thin">
                                <Icon
                                    aria-hidden="true"
                                    className="text-accent mx-auto mb-4 size-8"
                                />
                                <h3 className="text-headline text-content mb-2 text-center">
                                    {title}
                                </h3>
                                <p className="text-content-secondary text-footnote text-center">
                                    {description}
                                </p>
                            </Card>
                        ))}
                    </div>

                    <Card
                        material="thin"
                        className="bg-accent-soft mt-16 text-center"
                    >
                        <h3 className="text-title2 text-content mb-4 font-semibold">
                            Ready to simplify your scheduling?
                        </h3>
                        <p className="text-content-secondary text-body mb-8">
                            Join teams staying organized with GroupSync
                            Calendar.
                        </p>
                        {canRegister && (
                            <LinkButton
                                href="/register"
                                variant="primary"
                                size="lg"
                            >
                                Start free today
                            </LinkButton>
                        )}
                    </Card>
                </div>

                <footer className="border-hairline border-t">
                    <div className="text-content-tertiary text-footnote mx-auto max-w-6xl px-4 py-8 text-center sm:px-6 lg:px-8">
                        &copy; 2026 GroupSync Calendar. All rights reserved.
                    </div>
                </footer>
            </div>
        </>
    );
}
