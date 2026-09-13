import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';

interface NavLinkProps extends InertiaLinkProps {
    active?: boolean;
}

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}: NavLinkProps) {
    return (
        <Link
            {...props}
            className={cn(
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm leading-5 font-medium transition duration-150 ease-in-out focus:outline-none',
                active
                    ? 'border-indigo-400 text-gray-900 focus:border-indigo-700'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 focus:border-gray-300 focus:text-gray-700',
                className,
            )}
        >
            {children}
        </Link>
    );
}
