import AppShell from '@/components/ui/AppShell';
import type { PropsWithChildren, ReactNode } from 'react';

interface AuthenticatedLayoutProps extends PropsWithChildren {
    /** The page's title bar content. Passed straight through to the shell. */
    header?: ReactNode;
    /**
     * Toolbar controls owned by the page - date navigation, a view switcher.
     * Only the calendar pages have anything to put here.
     */
    toolbar?: ReactNode;
    /** Full-bleed content, for calendar grids that should not be centred. */
    fullBleed?: boolean;
}

/**
 * The authenticated chrome.
 *
 * Deliberately a thin pass-through to AppShell rather than a second layout.
 * Every page already declares `Page.layout = (page) => <AuthenticatedLayout
 * header={...}>{page}</AuthenticatedLayout>`, and app.tsx relies on that
 * assignment being a function of one argument to detect a layout resolver.
 * Keeping this component's name and its `header` prop means the shell can be
 * swapped underneath without touching any of those pages - and because
 * Inertia keeps the layout mounted across navigations, the sidebar's
 * collapsed state and scroll position survive a page change.
 */
export default function AuthenticatedLayout({
    header,
    toolbar,
    fullBleed,
    children,
}: AuthenticatedLayoutProps) {
    return (
        <AppShell header={header} toolbar={toolbar} fullBleed={fullBleed}>
            {children}
        </AppShell>
    );
}
