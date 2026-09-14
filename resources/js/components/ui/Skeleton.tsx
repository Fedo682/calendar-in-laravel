import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'react';

export interface SkeletonProps extends HTMLAttributes<HTMLDivElement> {
    /** Rounded to a circle, for avatar placeholders. */
    circle?: boolean;
}

/**
 * A loading placeholder.
 *
 * Deliberately `bg-surface-raised` with `animate-pulse` rather than a moving
 * shimmer gradient: a shimmer on a translucent surface fights whatever the
 * blur is pulling through from behind it, and looks broken in light mode.
 */
export default function Skeleton({
    circle = false,
    className,
    ...props
}: SkeletonProps) {
    return (
        <div
            {...props}
            aria-hidden="true"
            className={cn(
                'bg-surface-raised animate-pulse',
                circle ? 'rounded-full' : 'rounded-control',
                className,
            )}
        />
    );
}

/** A run of `lines` text placeholders, the last one short like real prose. */
export function SkeletonText({
    lines = 3,
    className,
}: {
    lines?: number;
    className?: string;
}) {
    return (
        <div className={cn('space-y-2', className)}>
            {Array.from({ length: lines }, (_, index) => (
                <Skeleton
                    key={index}
                    className={cn(
                        'h-3',
                        index === lines - 1 ? 'w-2/3' : 'w-full',
                    )}
                />
            ))}
        </div>
    );
}
