import { cn } from '@/lib/utils';
import type { GridEvent } from '@/types/calendar';
import type { CSSProperties } from 'react';

/**
 * An event as it appears in a grid: a glossy badge, the way a desk calendar
 * prints one.
 *
 * The gradient is derived from the calendar's own colour rather than being a
 * fixed blue, so two calendars sitting in the same day cell stay tellable
 * apart. `color-mix` does the darkening, which means one stored hex per
 * calendar produces the whole badge - no second colour to keep in sync, and
 * no palette to maintain.
 *
 * A redacted event gets the flat `busy` fill instead. It carries no
 * information, so it should not carry a colour either - a coloured badge
 * would imply the viewer could tell which calendar it belongs to.
 */

interface EventPillProps {
    event: GridEvent;
    onClick?: (event: GridEvent) => void;
    /** Absolute positioning from the day view's overlap layout. */
    style?: CSSProperties;
    className?: string;
    /** The day view shows a time range under the title; the month grid does not. */
    subtitle?: string;
}

/** The fallback when a calendar has no colour of its own. */
const DEFAULT_COLOR = 'var(--event-top)';

function pillStyle(event: GridEvent): CSSProperties {
    if (event.is_redacted) {
        return {
            background: 'var(--busy)',
            color: 'var(--content)',
        };
    }

    const base = event.calendar_color || DEFAULT_COLOR;

    return {
        // Top-to-bottom gloss, then a one-pixel inner highlight along the top
        // edge - the two things that read as "raised" rather than "printed".
        background: `linear-gradient(180deg, ${base} 0%, color-mix(in oklch, ${base} 68%, #000) 100%)`,
        boxShadow:
            'inset 0 1px 0 rgb(255 255 255 / 0.28), 0 1px 1px rgb(0 0 0 / 0.16)',
        color: '#fff',
    };
}

export default function EventPill({
    event,
    onClick,
    style,
    className,
    subtitle,
}: EventPillProps) {
    const interactive = onClick !== undefined;

    return (
        <button
            type="button"
            disabled={!interactive}
            onClick={(e) => {
                // Day cells are themselves buttons that open the create
                // dialog; without this, opening an event also opens "new
                // event" for the day underneath it.
                e.stopPropagation();
                onClick?.(event);
            }}
            style={{ ...pillStyle(event), ...style }}
            className={cn(
                'rounded-control text-caption1 block w-full truncate px-1.5 py-0.5 text-start leading-tight',
                'ease-hig duration-fast transition-[filter]',
                interactive && 'hover:brightness-110 focus-visible:outline-2',
                className,
            )}
            title={subtitle ? `${event.title} - ${subtitle}` : event.title}
        >
            <span className="block truncate font-medium">{event.title}</span>
            {subtitle && (
                <span className="block truncate opacity-80">{subtitle}</span>
            )}
        </button>
    );
}
