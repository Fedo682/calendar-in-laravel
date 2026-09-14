import { cn } from '@/lib/utils';
import { LoaderCircle } from 'lucide-react';

export type SpinnerSize = 'sm' | 'md' | 'lg';

const SIZES: Record<SpinnerSize, string> = {
    sm: 'size-3.5',
    md: 'size-4',
    lg: 'size-6',
};

export interface SpinnerProps {
    size?: SpinnerSize;
    className?: string;
    /** Announced to screen readers. Set to null inside a button that already
     *  carries its own busy state, so the label is not read twice. */
    label?: string | null;
}

export default function Spinner({
    size = 'md',
    className,
    label = 'Loading',
}: SpinnerProps) {
    return (
        <>
            <LoaderCircle
                aria-hidden="true"
                className={cn('animate-spin', SIZES[size], className)}
            />
            {label !== null && <span className="sr-only">{label}</span>}
        </>
    );
}
