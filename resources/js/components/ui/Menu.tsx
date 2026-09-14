import { cn } from '@/lib/utils';
import {
    Menu as HeadlessMenu,
    MenuButton,
    MenuItem,
    MenuItems,
    MenuSeparator,
    Transition,
} from '@headlessui/react';
import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { Fragment } from 'react';

export interface MenuProps {
    /** The element that opens the menu. Rendered inside a Headless MenuButton. */
    trigger: ReactNode;
    align?: 'start' | 'end';
    /** Tailwind width class for the panel. */
    width?: string;
    className?: string;
    children: ReactNode;
}

/**
 * A popover menu on the thick material - it floats over content, so it takes
 * the same thickness as a modal.
 *
 * The trigger is passed as a node rather than as a render prop: every call
 * site so far wants "this button, but it opens a menu", and a render prop
 * would make each of them write a closure to get there.
 */
export default function Menu({
    trigger,
    align = 'end',
    width = 'w-56',
    className,
    children,
}: MenuProps) {
    return (
        <HeadlessMenu as="div" className="relative inline-block text-left">
            <MenuButton as={Fragment}>{trigger}</MenuButton>

            <Transition
                as={Fragment}
                enter="ease-hig-out duration-fast"
                enterFrom="opacity-0 scale-95 -translate-y-1"
                enterTo="opacity-100 scale-100 translate-y-0"
                leave="ease-hig duration-fast"
                leaveFrom="opacity-100 scale-100 translate-y-0"
                leaveTo="opacity-0 scale-95 -translate-y-1"
            >
                <MenuItems
                    className={cn(
                        'material-thick rounded-card shadow-overlay absolute z-50 mt-2 origin-top overflow-hidden p-1 focus:outline-none',
                        align === 'end' ? 'end-0' : 'start-0',
                        width,
                        className,
                    )}
                >
                    {children}
                </MenuItems>
            </Transition>
        </HeadlessMenu>
    );
}

const ITEM_BASE =
    'group flex w-full items-center gap-2.5 rounded-control px-2.5 py-2 text-subhead text-content transition ease-hig duration-fast data-focus:bg-accent data-focus:text-on-accent data-disabled:pointer-events-none data-disabled:opacity-40';

const DESTRUCTIVE = 'text-danger data-focus:bg-danger data-focus:text-on-accent';

interface MenuItemCommonProps {
    icon?: LucideIcon;
    destructive?: boolean;
    children?: ReactNode;
}

export interface MenuActionProps
    extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children'>,
        MenuItemCommonProps {}

export function MenuAction({
    icon: Icon,
    destructive = false,
    className,
    children,
    ...props
}: MenuActionProps) {
    return (
        <MenuItem>
            <button
                {...props}
                type="button"
                className={cn(ITEM_BASE, destructive && DESTRUCTIVE, className)}
            >
                {Icon && (
                    <Icon aria-hidden="true" className="size-4 shrink-0" />
                )}
                {children}
            </button>
        </MenuItem>
    );
}

export interface MenuLinkProps
    extends Omit<InertiaLinkProps, 'children' | 'className'>,
        MenuItemCommonProps {
    className?: string;
}

export function MenuLink({
    icon: Icon,
    destructive = false,
    className,
    children,
    ...props
}: MenuLinkProps) {
    return (
        <MenuItem>
            <Link
                {...props}
                className={cn(ITEM_BASE, destructive && DESTRUCTIVE, className)}
            >
                {Icon && (
                    <Icon aria-hidden="true" className="size-4 shrink-0" />
                )}
                {children}
            </Link>
        </MenuItem>
    );
}

export function MenuDivider() {
    return <MenuSeparator className="bg-hairline my-1 h-px" />;
}

export function MenuLabel({ children }: { children: ReactNode }) {
    return (
        <p className="text-caption1 text-content-tertiary px-2.5 pt-2 pb-1 font-medium">
            {children}
        </p>
    );
}
