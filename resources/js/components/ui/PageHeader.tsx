import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';
import Breadcrumbs from './Breadcrumbs';
import type { Crumb } from './Breadcrumbs';

export interface PageHeaderProps {
    title: ReactNode;
    description?: ReactNode;
    breadcrumbs?: Crumb[];
    /** Buttons for the page as a whole, aligned to the trailing edge. */
    actions?: ReactNode;
    className?: string;
}

/**
 * The title block at the top of a page's content.
 *
 * Deliberately not glass and not a bar: it scrolls away with the content the
 * way a HIG large title does, while the toolbar in `AppShell` is the fixed
 * chrome above it. Two stacked translucent bars would be one too many.
 */
export default function PageHeader({
    title,
    description,
    breadcrumbs,
    actions,
    className,
}: PageHeaderProps) {
    return (
        <header className={cn('mb-6', className)}>
            {breadcrumbs !== undefined && breadcrumbs.length > 0 && (
                <Breadcrumbs items={breadcrumbs} className="mb-2" />
            )}

            <div className="flex flex-wrap items-end justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="text-largetitle text-content font-semibold">
                        {title}
                    </h1>

                    {description !== undefined && (
                        <p className="text-subhead text-content-secondary mt-1.5">
                            {description}
                        </p>
                    )}
                </div>

                {actions !== undefined && (
                    <div className="flex shrink-0 items-center gap-2">
                        {actions}
                    </div>
                )}
            </div>
        </header>
    );
}
