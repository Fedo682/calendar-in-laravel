import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import type { HTMLAttributes, ReactNode } from 'react';

export type BadgeTone =
    | 'neutral'
    | 'accent'
    | 'success'
    | 'warning'
    | 'danger'
    | 'busy';

const TONES: Record<BadgeTone, string> = {
    neutral: 'bg-surface-raised text-content-secondary border-hairline',
    accent: 'bg-accent-soft text-accent border-accent/30',
    success: 'bg-success-soft text-success border-success/30',
    warning: 'bg-warning-soft text-warning border-warning/30',
    danger: 'bg-danger-soft text-danger border-danger/30',
    // The "something is here, you may not read it" fill, matching the
    // redacted-event treatment on the calendar.
    busy: 'bg-busy/25 text-content-secondary border-busy/40',
};

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
    tone?: BadgeTone;
    icon?: LucideIcon;
    /** A filled circle before the label, for calendar colours. */
    dot?: string | null;
    children?: ReactNode;
}

export default function Badge({
    tone = 'neutral',
    icon: Icon,
    dot,
    className,
    children,
    ...props
}: BadgeProps) {
    return (
        <span
            {...props}
            className={cn(
                'rounded-control text-caption1 inline-flex items-center gap-1.5 border px-2 py-0.5 font-medium',
                TONES[tone],
                className,
            )}
        >
            {dot != null && (
                <span
                    aria-hidden="true"
                    className="size-2 shrink-0 rounded-full"
                    style={{ backgroundColor: dot }}
                />
            )}
            {Icon && <Icon aria-hidden="true" className="size-3" />}
            {children}
        </span>
    );
}
