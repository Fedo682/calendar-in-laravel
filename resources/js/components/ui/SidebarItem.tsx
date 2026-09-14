import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export interface SidebarItemProps {
    /** Omit to render a button instead of a link (a disclosure row, say). */
    href?: string;
    icon?: LucideIcon;
    /** A calendar's colour, drawn as a filled dot in place of an icon. */
    dot?: string | null;
    active?: boolean;
    /** Collapsed rail: icon only, with `children` moved to the tooltip/title. */
    collapsed?: boolean;
    /** Rendered at the trailing edge - a count, a visibility checkbox. */
    trailing?: ReactNode;
    onClick?: () => void;
    className?: string;
    children: ReactNode;
}

/**
 * One row of the source list.
 *
 * No material: the sidebar itself is `material-regular`, and a blurred row
 * inside a blurred panel is both muddy and expensive. The active row is a
 * flat accent-soft fill, which is what the Finder and Mail sidebars do.
 */
export default function SidebarItem({
    href,
    icon: Icon,
    dot,
    active = false,
    collapsed = false,
    trailing,
    onClick,
    className,
    children,
}: SidebarItemProps) {
    const classes = cn(
        'group flex w-full items-center gap-2.5 rounded-control px-2.5 py-1.5 text-subhead ease-hig duration-fast transition',
        active
            ? 'bg-accent-soft text-content font-medium'
            : 'text-content-secondary hover:bg-surface-raised hover:text-content',
        collapsed && 'justify-center px-0',
        className,
    );

    const leading =
        dot != null ? (
            <span
                aria-hidden="true"
                className="size-2.5 shrink-0 rounded-full"
                style={{ backgroundColor: dot }}
            />
        ) : Icon ? (
            <Icon
                aria-hidden="true"
                className={cn(
                    'size-4 shrink-0',
                    active ? 'text-accent' : 'text-content-tertiary',
                )}
            />
        ) : null;

    const body = (
        <>
            {leading}
            {!collapsed && (
                <span className="min-w-0 flex-1 truncate text-left">
                    {children}
                </span>
            )}
            {!collapsed && trailing !== undefined && (
                <span className="shrink-0">{trailing}</span>
            )}
        </>
    );

    if (href === undefined) {
        return (
            <button
                type="button"
                onClick={onClick}
                aria-current={active ? 'true' : undefined}
                className={classes}
            >
                {body}
            </button>
        );
    }

    return (
        <Link
            href={href}
            onClick={onClick}
            aria-current={active ? 'page' : undefined}
            className={classes}
        >
            {body}
        </Link>
    );
}

/** The small uppercase heading above a run of sidebar items. */
export function SidebarSection({
    title,
    action,
    collapsed = false,
    children,
}: {
    title: string;
    action?: ReactNode;
    collapsed?: boolean;
    children: ReactNode;
}) {
    return (
        <div className="space-y-0.5">
            {!collapsed && (
                <div className="flex items-center justify-between gap-2 px-2.5 pt-4 pb-1">
                    <h2 className="text-caption1 text-content-tertiary font-semibold tracking-wide uppercase">
                        {title}
                    </h2>
                    {action}
                </div>
            )}
            {collapsed && <div className="bg-hairline mx-2 my-2 h-px" />}
            {children}
        </div>
    );
}
