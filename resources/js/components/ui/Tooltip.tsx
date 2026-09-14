import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';
import { useId, useState } from 'react';

export type TooltipSide = 'top' | 'bottom' | 'left' | 'right';

const SIDES: Record<TooltipSide, string> = {
    top: 'bottom-full left-1/2 mb-2 -translate-x-1/2',
    bottom: 'top-full left-1/2 mt-2 -translate-x-1/2',
    left: 'right-full top-1/2 me-2 -translate-y-1/2',
    right: 'left-full top-1/2 ms-2 -translate-y-1/2',
};

export interface TooltipProps {
    label: ReactNode;
    side?: TooltipSide;
    className?: string;
    children: ReactNode;
}

/**
 * CSS-positioned rather than floating-ui-positioned: every tooltip in this
 * app hangs off a toolbar or sidebar button with room on the chosen side, and
 * a positioning engine would be a dependency and a measurement pass for a
 * label that is never near a viewport edge.
 *
 * Focus opens it as well as hover, so a keyboard user gets the same
 * affordance; `aria-describedby` is what actually carries the text to a
 * screen reader, and the visible bubble is `aria-hidden` so it is not read
 * twice.
 */
export default function Tooltip({
    label,
    side = 'top',
    className,
    children,
}: TooltipProps) {
    const id = useId();
    const [open, setOpen] = useState(false);

    return (
        <span
            className="relative inline-flex"
            onMouseEnter={() => {
                setOpen(true);
            }}
            onMouseLeave={() => {
                setOpen(false);
            }}
            onFocusCapture={() => {
                setOpen(true);
            }}
            onBlurCapture={() => {
                setOpen(false);
            }}
        >
            <span aria-describedby={id} className="contents">
                {children}
            </span>

            <span
                id={id}
                role="tooltip"
                aria-hidden={!open}
                className={cn(
                    'material-thick text-caption1 text-content rounded-control shadow-overlay ease-hig duration-fast pointer-events-none absolute z-50 whitespace-nowrap px-2 py-1 transition',
                    SIDES[side],
                    open ? 'opacity-100' : 'invisible opacity-0',
                    className,
                )}
            >
                {label}
            </span>
        </span>
    );
}
