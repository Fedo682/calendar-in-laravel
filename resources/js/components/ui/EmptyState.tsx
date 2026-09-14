import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';

export interface EmptyStateProps {
    icon?: LucideIcon;
    title: string;
    description?: ReactNode;
    /** The one thing to do next. Keep it to a single primary action. */
    action?: ReactNode;
    className?: string;
}

/**
 * The "nothing here yet" panel.
 *
 * The icon sits in a soft accent disc rather than floating grey on the
 * canvas: on a dark field a lone outline glyph reads as a rendering failure,
 * and the disc gives it somewhere to be.
 */
export default function EmptyState({
    icon: Icon = Inbox,
    title,
    description,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center px-6 py-14 text-center',
                className,
            )}
        >
            <span className="bg-accent-soft text-accent mb-4 inline-flex size-12 items-center justify-center rounded-full">
                <Icon aria-hidden="true" className="size-5" />
            </span>

            <h3 className="text-headline text-content">{title}</h3>

            {description !== undefined && (
                <p className="text-subhead text-content-secondary mt-1.5 max-w-sm text-balance">
                    {description}
                </p>
            )}

            {action !== undefined && <div className="mt-5">{action}</div>}
        </div>
    );
}
