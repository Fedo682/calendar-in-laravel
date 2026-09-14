import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import { useId } from 'react';

export interface SegmentedOption<T extends string> {
    value: T;
    label: string;
    icon?: LucideIcon;
    /** Show the icon only, with `label` moved to the accessible name. */
    iconOnly?: boolean;
}

export interface SegmentedControlProps<T extends string> {
    options: SegmentedOption<T>[];
    value: T;
    onChange: (value: T) => void;
    /** Announced as the group's purpose, e.g. "Calendar view". */
    label: string;
    size?: 'sm' | 'md';
    fullWidth?: boolean;
    className?: string;
}

/**
 * The Month / Week / Day switcher.
 *
 * The selected pill is one absolutely-positioned element that slides between
 * slots, rather than a background toggled per button. That is what produces
 * the iOS feel: the indicator travels, it does not cross-fade. It also means
 * exactly one element animates regardless of how many segments there are.
 *
 * `role="radiogroup"` rather than tabs, because this selects a value; it does
 * not reveal a panel.
 */
export default function SegmentedControl<T extends string>({
    options,
    value,
    onChange,
    label,
    size = 'md',
    fullWidth = false,
    className,
}: SegmentedControlProps<T>) {
    const groupId = useId();
    const selectedIndex = Math.max(
        0,
        options.findIndex((option) => option.value === value),
    );

    return (
        <div
            role="radiogroup"
            aria-label={label}
            className={cn(
                'material-ultrathin rounded-control relative isolate inline-flex p-0.5',
                fullWidth && 'flex w-full',
                className,
            )}
        >
            <span
                aria-hidden="true"
                className="bg-surface-raised border-hairline ease-hig duration-base absolute inset-y-0.5 z-0 rounded-[0.4375rem] border shadow-sm transition-[left,width]"
                style={{
                    width: `calc((100% - 0.25rem) / ${options.length})`,
                    left: `calc(0.125rem + (100% - 0.25rem) * ${selectedIndex} / ${options.length})`,
                }}
            />

            {options.map((option) => {
                const Icon = option.icon;
                const selected = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        id={`${groupId}-${option.value}`}
                        aria-checked={selected}
                        aria-label={option.iconOnly ? option.label : undefined}
                        onClick={() => {
                            onChange(option.value);
                        }}
                        className={cn(
                            'rounded-control ease-hig duration-fast relative z-10 inline-flex items-center justify-center gap-1.5 font-medium transition',
                            size === 'sm'
                                ? 'text-caption1 h-7 px-2.5'
                                : 'text-footnote h-8 px-3.5',
                            fullWidth && 'flex-1',
                            selected
                                ? 'text-content'
                                : 'text-content-secondary hover:text-content',
                        )}
                    >
                        {Icon && (
                            <Icon aria-hidden="true" className="size-3.5" />
                        )}
                        {!option.iconOnly && option.label}
                    </button>
                );
            })}
        </div>
    );
}
