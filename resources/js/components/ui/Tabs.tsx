import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import Badge from './Badge';

export interface TabItem<T extends string> {
    value: T;
    label: ReactNode;
    icon?: LucideIcon;
    /** A count beside the label - members, conflicts, pending invites. */
    count?: number;
}

export interface TabsProps<T extends string> {
    tabs: TabItem<T>[];
    value: T;
    onChange: (value: T) => void;
    label: string;
    className?: string;
}

/**
 * Underlined section tabs, for switching the content of a page.
 *
 * Distinct from `SegmentedControl`, which picks a *value*. These are
 * `role="tablist"` and expect the caller to render a matching
 * `role="tabpanel"`; when in doubt, if the thing being switched is a whole
 * region of the page it is a tab, and if it is one setting it is a segment.
 */
export default function Tabs<T extends string>({
    tabs,
    value,
    onChange,
    label,
    className,
}: TabsProps<T>) {
    return (
        <div
            role="tablist"
            aria-label={label}
            className={cn('hairline-b flex items-stretch gap-1', className)}
        >
            {tabs.map((tab) => {
                const Icon = tab.icon;
                const selected = tab.value === value;

                return (
                    <button
                        key={tab.value}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        onClick={() => {
                            onChange(tab.value);
                        }}
                        className={cn(
                            'text-subhead ease-hig duration-fast relative -mb-px inline-flex items-center gap-2 border-b-2 px-3 pb-2.5 pt-1.5 font-medium transition',
                            selected
                                ? 'border-accent text-content'
                                : 'text-content-secondary hover:text-content border-transparent',
                        )}
                    >
                        {Icon && (
                            <Icon aria-hidden="true" className="size-4" />
                        )}
                        {tab.label}
                        {tab.count !== undefined && (
                            <Badge tone={selected ? 'accent' : 'neutral'}>
                                {tab.count}
                            </Badge>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
