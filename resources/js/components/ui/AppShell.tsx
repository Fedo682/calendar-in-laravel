import { useAppearance } from '@/hooks/useAppearance';
import type { Appearance } from '@/hooks/useAppearance';
import { useCalendarVisibility } from '@/hooks/useCalendarVisibility';
import { useFlashToasts } from '@/hooks/useFlashToasts';
import { cn } from '@/lib/utils';
import type { CalendarSummary } from '@/types/calendar';
import { usePageProps } from '@/types/shared';
import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    LayoutDashboard,
    LogOut,
    Monitor,
    Moon,
    PanelLeft,
    Rss,
    Search,
    Settings,
    Sun,
    User,
    Users,
} from 'lucide-react';
import type { ReactNode, Ref } from 'react';
import { useMemo, useRef, useState } from 'react';
import Avatar from './Avatar';
import Checkbox from './Checkbox';
import Menu, { MenuDivider, MenuLabel, MenuLink } from './Menu';
import SegmentedControl from './SegmentedControl';
import Sheet from './Sheet';
import SidebarItem, { SidebarSection } from './SidebarItem';
import { Toaster } from './Toast';
import Tooltip from './Tooltip';

const SIDEBAR_STORAGE_KEY = 'sidebar:collapsed';

const APPEARANCE_OPTIONS: {
    value: Appearance;
    label: string;
    icon: typeof Sun;
}[] = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];

function readCollapsed(): boolean {
    try {
        return localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function writeCollapsed(collapsed: boolean): void {
    try {
        localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? '1' : '0');
    } catch {
        // Purely a convenience; the session still works without it.
    }
}

/**
 * The calendars the source list is currently able to show.
 *
 * Read off the page's own props where a page happens to send them. The
 * middleware shares `auth`, `flash` and `viewer` and nothing else, so there is
 * no app-wide list of the viewer's calendars yet - adding one means editing
 * `HandleInertiaRequests::share()`, which is explicitly the one method
 * parallel branches must not all touch at once.
 */
function useSidebarCalendars(
    provided: CalendarSummary[] | undefined,
): CalendarSummary[] {
    const props = usePageProps<{ calendars?: unknown }>();

    return useMemo(() => {
        if (provided !== undefined) {
            return provided;
        }

        const fromPage = props.calendars;

        if (!Array.isArray(fromPage)) {
            return [];
        }

        return fromPage.filter(
            (item): item is CalendarSummary =>
                typeof item === 'object' &&
                item !== null &&
                typeof (item as { id?: unknown }).id === 'number' &&
                typeof (item as { name?: unknown }).name === 'string',
        );
    }, [provided, props.calendars]);
}

interface SourceListProps {
    collapsed: boolean;
    filter: string;
    onFilterChange: (value: string) => void;
    filterRef?: Ref<HTMLInputElement>;
    calendars: CalendarSummary[];
    onNavigate?: () => void;
}

/**
 * One calendar in the source list.
 *
 * The whole row is the checkbox's own `<label>`, not a button wrapping an
 * input - nesting a form control inside a button is invalid HTML and gives
 * the row two competing click targets that cancel each other out.
 */
function CalendarSourceRow({
    calendar,
    visible,
    onToggle,
    collapsed,
}: {
    calendar: CalendarSummary;
    visible: boolean;
    onToggle: () => void;
    collapsed: boolean;
}) {
    if (collapsed) {
        return (
            <Tooltip label={calendar.name} side="right">
                <button
                    type="button"
                    onClick={onToggle}
                    aria-pressed={visible}
                    aria-label={`Show ${calendar.name}`}
                    className="rounded-control hover:bg-surface-raised ease-hig duration-fast flex w-full items-center justify-center py-1.5 transition"
                >
                    <span
                        aria-hidden="true"
                        className={cn(
                            'size-2.5 rounded-full',
                            !visible && 'opacity-30',
                        )}
                        style={{
                            backgroundColor: calendar.color ?? 'var(--accent)',
                        }}
                    />
                </button>
            </Tooltip>
        );
    }

    return (
        <label className="rounded-control hover:bg-surface-raised text-subhead text-content-secondary hover:text-content ease-hig duration-fast flex cursor-pointer items-center gap-2.5 px-2.5 py-1.5 transition">
            <Checkbox
                checked={visible}
                onChange={onToggle}
                aria-label={`Show ${calendar.name}`}
            />
            <span
                aria-hidden="true"
                className={cn(
                    'size-2.5 shrink-0 rounded-full',
                    !visible && 'opacity-30',
                )}
                style={{ backgroundColor: calendar.color ?? 'var(--accent)' }}
            />
            <span className={cn('min-w-0 flex-1 truncate', !visible && 'opacity-50')}>
                {calendar.name}
            </span>
        </label>
    );
}

function SourceList({
    collapsed,
    filter,
    onFilterChange,
    filterRef,
    calendars,
    onNavigate,
}: SourceListProps) {
    const visibility = useCalendarVisibility();

    const needle = filter.trim().toLowerCase();

    const matching = useMemo(
        () =>
            needle === ''
                ? calendars
                : calendars.filter(
                      (calendar) =>
                          calendar.name.toLowerCase().includes(needle) ||
                          (calendar.group?.name.toLowerCase().includes(needle) ??
                              false),
                  ),
        [calendars, needle],
    );

    /** Group name -> its calendars, preserving the server's ordering. */
    const grouped = useMemo(() => {
        const buckets = new Map<string, CalendarSummary[]>();

        for (const calendar of matching) {
            const key = calendar.group?.name ?? 'Personal';
            const bucket = buckets.get(key);

            if (bucket) {
                bucket.push(calendar);
            } else {
                buckets.set(key, [calendar]);
            }
        }

        return [...buckets.entries()];
    }, [matching]);

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            {!collapsed && (
                <div className="relative px-3 pt-3">
                    <Search
                        aria-hidden="true"
                        className="text-content-tertiary pointer-events-none absolute start-6 top-1/2 size-3.5 -translate-y-1/2"
                    />
                    <input
                        ref={filterRef}
                        type="search"
                        value={filter}
                        onChange={(event) => {
                            onFilterChange(event.target.value);
                        }}
                        placeholder="Filter calendars"
                        aria-label="Filter calendars"
                        className="border-hairline bg-surface-raised text-footnote text-content placeholder:text-content-tertiary focus:border-accent rounded-control ease-hig duration-fast h-8 w-full appearance-none border ps-8 pe-2 transition"
                    />
                </div>
            )}

            <nav className="min-h-0 flex-1 overflow-y-auto px-3 pb-4">
                <SidebarSection title="Calendar" collapsed={collapsed}>
                    <SidebarItem
                        href={route('dashboard')}
                        icon={LayoutDashboard}
                        active={route().current('dashboard')}
                        collapsed={collapsed}
                        onClick={onNavigate}
                    >
                        Dashboard
                    </SidebarItem>
                    <SidebarItem
                        href={route('calendars.index')}
                        icon={CalendarDays}
                        active={
                            route().current('calendars.index') ||
                            route().current('groups.calendars.*')
                        }
                        collapsed={collapsed}
                        onClick={onNavigate}
                    >
                        Calendars
                    </SidebarItem>
                    <SidebarItem
                        href={route('calendars.personal')}
                        icon={User}
                        active={route().current('calendars.personal*')}
                        collapsed={collapsed}
                        onClick={onNavigate}
                    >
                        My Calendar
                    </SidebarItem>
                    <SidebarItem
                        href={route('groups.index')}
                        icon={Users}
                        active={route().current('groups.*')}
                        collapsed={collapsed}
                        onClick={onNavigate}
                    >
                        Groups
                    </SidebarItem>
                </SidebarSection>

                {grouped.map(([groupName, groupCalendars]) => (
                    <SidebarSection
                        key={groupName}
                        title={groupName}
                        collapsed={collapsed}
                    >
                        {groupCalendars.map((calendar) => (
                            <CalendarSourceRow
                                key={calendar.id}
                                calendar={calendar}
                                collapsed={collapsed}
                                visible={visibility.isVisible(calendar.id)}
                                onToggle={() => {
                                    visibility.toggle(calendar.id);
                                }}
                            />
                        ))}
                    </SidebarSection>
                ))}

                {calendars.length > 0 && matching.length === 0 && (
                    <p className="text-footnote text-content-tertiary px-2.5 pt-4">
                        No calendars match “{filter}”.
                    </p>
                )}
            </nav>
        </div>
    );
}

function BrandMark({ collapsed }: { collapsed: boolean }) {
    return (
        <Link
            href={route('dashboard')}
            className="rounded-control flex items-center gap-2.5 px-3 py-3"
        >
            <span className="bg-accent text-on-accent rounded-control inline-flex size-7 shrink-0 items-center justify-center">
                <CalendarDays aria-hidden="true" className="size-4" />
            </span>
            {!collapsed && (
                <span className="text-headline text-content truncate">
                    Calendar
                </span>
            )}
        </Link>
    );
}

export interface AppShellProps {
    /** The page's title bar content, rendered in the toolbar. */
    header?: ReactNode;
    /**
     * Toolbar controls owned by the page - date navigation, Today, the
     * Month/Week/Day switcher. A slot rather than built in, because only the
     * calendar pages have a date to navigate.
     */
    toolbar?: ReactNode;
    /** Overrides the opportunistic read of the page's `calendars` prop. */
    calendars?: CalendarSummary[];
    /** Drop the centred max-width wrapper, for full-bleed calendar grids. */
    fullBleed?: boolean;
    children?: ReactNode;
}

/**
 * The application chrome: a collapsible source list on the leading edge and a
 * translucent toolbar across the top.
 *
 * This is the HIG structure for a calendar app, and it is where the
 * multi-calendar visibility toggles have to live now that a user can have
 * personal calendars alongside their groups'.
 *
 * Mounted once by `AuthenticatedLayout`, which Inertia keeps alive across
 * navigations - so the sidebar's scroll position, its collapsed state and the
 * filter text all survive a page change, and none of this subtree remounts.
 *
 * The sidebar and toolbar are a single solid bar in this design - a desk
 * calendar's header, not a frosted panel - so they take `bg-chrome` rather
 * than a material. Blur is left to the things the toolbar opens (menus, the
 * mobile sheet), which genuinely float over scrolling content.
 */
export default function AppShell({
    header,
    toolbar,
    calendars: providedCalendars,
    fullBleed = false,
    children,
}: AppShellProps) {
    const { auth } = usePageProps();
    const { appearance, setAppearance } = useAppearance();

    useFlashToasts();

    const [collapsed, setCollapsed] = useState(readCollapsed);
    const [navOpen, setNavOpen] = useState(false);
    const [filter, setFilter] = useState('');

    // Two refs, not one: the desktop rail stays mounted (just `hidden`) below
    // the lg breakpoint, so while the drawer is open there are two filter
    // inputs in the DOM and a single shared ref would point at whichever
    // attached last - often the invisible one.
    const railFilterRef = useRef<HTMLInputElement>(null);
    const drawerFilterRef = useRef<HTMLInputElement>(null);

    const calendars = useSidebarCalendars(providedCalendars);

    const user = auth.user;

    const toggleCollapsed = () => {
        setCollapsed((previous) => {
            writeCollapsed(!previous);

            return !previous;
        });
    };

    /**
     * The toolbar's search button. Below `lg` the sidebar is a sheet, so it
     * has to be opened first; above it, the field is already on screen and
     * only needs focus. Either way the one control does the expected thing.
     */
    const focusFilter = () => {
        const onDesktop = window.matchMedia('(min-width: 1024px)').matches;

        if (onDesktop) {
            setCollapsed(false);
            writeCollapsed(false);
        } else {
            setNavOpen(true);
        }

        // Deferred until the drawer has mounted / the rail has expanded;
        // focusing an element that is still `hidden` does nothing.
        window.setTimeout(() => {
            const target = onDesktop ? railFilterRef : drawerFilterRef;

            target.current?.focus();
        }, 60);
    };

    return (
        <div className="canvas-wash text-content min-h-screen">
            {/* Fixed rather than sticky: a sticky sidebar inside a scrolling
                page repaints its blur on every scroll frame, which is the one
                place backdrop-filter genuinely hurts. */}
            <aside
                className={cn(
                    'bg-chrome text-chrome-content ease-hig duration-base fixed inset-y-0 start-0 z-40 hidden flex-col transition-[width] lg:flex',
                    collapsed ? 'w-16' : 'w-64',
                )}
            >
                <BrandMark collapsed={collapsed} />
                <SourceList
                    collapsed={collapsed}
                    filter={filter}
                    onFilterChange={setFilter}
                    filterRef={railFilterRef}
                    calendars={calendars}
                />
            </aside>

            {/* Below lg the source list is a drawer, because 256px of a phone
                screen is most of the phone. */}
            <Sheet
                open={navOpen}
                onClose={() => {
                    setNavOpen(false);
                }}
                side="left"
                bodyClassName="p-0"
            >
                <BrandMark collapsed={false} />
                <SourceList
                    collapsed={false}
                    filter={filter}
                    onFilterChange={setFilter}
                    filterRef={drawerFilterRef}
                    calendars={calendars}
                    onNavigate={() => {
                        setNavOpen(false);
                    }}
                />
            </Sheet>

            <div
                className={cn(
                    'ease-hig duration-base flex min-h-screen flex-col transition-[padding]',
                    collapsed ? 'lg:ps-16' : 'lg:ps-64',
                )}
            >
                <header className="bg-chrome text-chrome-content sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 px-3 sm:px-4">
                    <button
                        type="button"
                        onClick={() => {
                            setNavOpen(true);
                        }}
                        aria-label="Open navigation"
                        className="text-content-secondary hover:bg-surface-raised hover:text-content rounded-control ease-hig duration-fast inline-flex size-9 shrink-0 items-center justify-center transition lg:hidden"
                    >
                        <PanelLeft aria-hidden="true" className="size-4" />
                    </button>

                    <Tooltip
                        label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        side="bottom"
                    >
                        <button
                            type="button"
                            onClick={toggleCollapsed}
                            aria-label={
                                collapsed ? 'Expand sidebar' : 'Collapse sidebar'
                            }
                            aria-expanded={!collapsed}
                            className="text-content-secondary hover:bg-surface-raised hover:text-content rounded-control ease-hig duration-fast hidden size-9 shrink-0 items-center justify-center transition lg:inline-flex"
                        >
                            <PanelLeft aria-hidden="true" className="size-4" />
                        </button>
                    </Tooltip>

                    <div className="min-w-0 flex-1 truncate">{header}</div>

                    {toolbar !== undefined && (
                        <div className="flex shrink-0 items-center gap-2">
                            {toolbar}
                        </div>
                    )}

                    <button
                        type="button"
                        onClick={focusFilter}
                        aria-label="Search calendars"
                        className="text-content-secondary hover:bg-surface-raised hover:text-content rounded-control ease-hig duration-fast inline-flex size-9 shrink-0 items-center justify-center transition"
                    >
                        <Search aria-hidden="true" className="size-4" />
                    </button>

                    <Menu
                        width="w-60"
                        trigger={
                            <button
                                type="button"
                                aria-label="Account menu"
                                className="rounded-full"
                            >
                                <Avatar
                                    name={user?.name ?? 'Account'}
                                    src={
                                        typeof user?.avatar === 'string'
                                            ? user.avatar
                                            : null
                                    }
                                    size="sm"
                                />
                            </button>
                        }
                    >
                        {user && (
                            <div className="hairline-b mb-1 px-2.5 pt-1.5 pb-2.5">
                                <p className="text-subhead text-content truncate font-medium">
                                    {user.name}
                                </p>
                                <p className="text-caption1 text-content-secondary truncate">
                                    {user.email}
                                </p>
                            </div>
                        )}

                        <MenuLabel>Appearance</MenuLabel>
                        <div className="px-2.5 pb-2">
                            <SegmentedControl
                                label="Appearance"
                                fullWidth
                                size="sm"
                                value={appearance}
                                onChange={setAppearance}
                                options={APPEARANCE_OPTIONS}
                            />
                        </div>

                        <MenuDivider />

                        <MenuLink href={route('profile.edit')} icon={Settings}>
                            Profile
                        </MenuLink>
                        <MenuLink href={route('settings.integrations')} icon={Rss}>
                            Calendar feed
                        </MenuLink>
                        <MenuLink
                            href={route('logout')}
                            method="post"
                            as="button"
                            icon={LogOut}
                            destructive
                        >
                            Log Out
                        </MenuLink>
                    </Menu>
                </header>

                <main className="flex-1">
                    <div
                        className={cn(
                            !fullBleed && 'mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8',
                        )}
                    >
                        {children}
                    </div>
                </main>
            </div>

            <Toaster />
        </div>
    );
}
