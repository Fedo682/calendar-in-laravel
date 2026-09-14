import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';
import { useId } from 'react';

/**
 * Apple's twelve calendar colours, as hex so they survive a round trip
 * through the `calendars.color` column (which is validated as `#rrggbb`).
 *
 * Fixed palette rather than a free colour input: an arbitrary picker lets a
 * user choose something with no contrast against either theme, and a calendar
 * colour has to stay legible as a 10px dot on both a near-black and a
 * near-white field.
 */
export const CALENDAR_COLORS = [
    { value: '#FF3B30', name: 'Red' },
    { value: '#FF9500', name: 'Orange' },
    { value: '#FFCC00', name: 'Yellow' },
    { value: '#34C759', name: 'Green' },
    { value: '#30B0C7', name: 'Teal' },
    { value: '#32ADE6', name: 'Cyan' },
    { value: '#007AFF', name: 'Blue' },
    { value: '#5856D6', name: 'Indigo' },
    { value: '#AF52DE', name: 'Purple' },
    { value: '#FF2D55', name: 'Pink' },
    { value: '#A2845E', name: 'Brown' },
    { value: '#8E8E93', name: 'Graphite' },
] as const;

export interface ColorSwatchPickerProps {
    value: string | null;
    onChange: (value: string) => void;
    /** Announced as the group's purpose, e.g. "Calendar colour". */
    label: string;
    /** Defaults to the twelve system colours. */
    colors?: readonly { value: string; name: string }[];
    /** Submitted with a plain form post. */
    name?: string;
    className?: string;
}

export default function ColorSwatchPicker({
    value,
    onChange,
    label,
    colors = CALENDAR_COLORS,
    name,
    className,
}: ColorSwatchPickerProps) {
    const groupName = useId();

    return (
        <div
            role="radiogroup"
            aria-label={label}
            className={cn('flex flex-wrap gap-2', className)}
        >
            {colors.map((color) => {
                const selected =
                    value?.toLowerCase() === color.value.toLowerCase();

                return (
                    <button
                        key={color.value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        aria-label={color.name}
                        title={color.name}
                        onClick={() => {
                            onChange(color.value);
                        }}
                        className={cn(
                            'ease-hig duration-fast relative inline-flex size-7 items-center justify-center rounded-full transition',
                            'hover:scale-110 active:scale-95',
                            // The ring is drawn outside the swatch so the
                            // colour itself is never overlaid or tinted.
                            selected &&
                                'ring-content ring-offset-canvas ring-2 ring-offset-2',
                        )}
                        style={{ backgroundColor: color.value }}
                    >
                        {selected && (
                            <Check
                                aria-hidden="true"
                                strokeWidth={3}
                                className="size-3.5 text-white drop-shadow"
                            />
                        )}
                    </button>
                );
            })}

            {name !== undefined && (
                <input
                    type="hidden"
                    name={name}
                    id={groupName}
                    value={value ?? ''}
                />
            )}
        </div>
    );
}
