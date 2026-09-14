import { cn } from '@/lib/utils';
import {
    Field as HeadlessField,
    Switch as HeadlessSwitch,
    Label,
} from '@headlessui/react';
import { Check, Minus } from 'lucide-react';
import type { InputHTMLAttributes, ReactNode, Ref } from 'react';
import { useCallback, useRef } from 'react';

export interface CheckboxProps
    extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'size'> {
    label?: ReactNode;
    /** Neither on nor off - "some of the calendars under this group". */
    indeterminate?: boolean;
    ref?: Ref<HTMLInputElement>;
}

/**
 * A real `<input type="checkbox">` kept in the tree but stripped of its native
 * appearance, with the tick drawn as a sibling driven by `peer-checked`. Keeps
 * native form submission, the `required` validation bubble and every
 * assistive-technology behaviour, none of which a div-with-a-role reproduces.
 */
export default function Checkbox({
    label,
    indeterminate = false,
    className,
    disabled,
    ref,
    ...props
}: CheckboxProps) {
    const inputRef = useRef<HTMLInputElement | null>(null);

    // `indeterminate` is a DOM property with no HTML attribute, so it can only
    // be set imperatively. Done on attach (rather than in an effect) so the
    // callback re-runs whenever the flag changes and the tri-state stays in
    // sync without a second effect watching it.
    const attach = useCallback(
        (node: HTMLInputElement | null) => {
            inputRef.current = node;

            if (node) {
                node.indeterminate = indeterminate;
            }

            if (typeof ref === 'function') {
                ref(node);
            } else if (ref) {
                ref.current = node;
            }
        },
        [indeterminate, ref],
    );

    const box = (
        <span className="relative inline-flex size-[1.125rem] shrink-0 items-center justify-center">
            <input
                {...props}
                ref={attach}
                type="checkbox"
                disabled={disabled}
                className="peer border-hairline bg-surface-raised checked:border-accent checked:bg-accent indeterminate:border-accent indeterminate:bg-accent ease-hig duration-fast absolute inset-0 size-full cursor-pointer appearance-none rounded-[0.375rem] border transition disabled:cursor-not-allowed disabled:opacity-50"
            />
            <Check
                aria-hidden="true"
                strokeWidth={3}
                className="text-on-accent pointer-events-none size-3 opacity-0 peer-checked:opacity-100 peer-indeterminate:opacity-0"
            />
            <Minus
                aria-hidden="true"
                strokeWidth={3}
                className="text-on-accent pointer-events-none absolute size-3 opacity-0 peer-indeterminate:opacity-100"
            />
        </span>
    );

    if (label === undefined) {
        return <span className={cn('inline-flex', className)}>{box}</span>;
    }

    return (
        <label
            className={cn(
                'text-subhead text-content inline-flex items-center gap-2.5',
                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                className,
            )}
        >
            {box}
            <span>{label}</span>
        </label>
    );
}

export interface SwitchProps {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label?: ReactNode;
    description?: ReactNode;
    disabled?: boolean;
    name?: string;
    className?: string;
}

/**
 * The iOS toggle. Headless UI owns the keyboard and ARIA behaviour; the
 * geometry - a squat pill with a knob very nearly as tall as the track - is
 * what makes it read as the system control rather than a generic toggle.
 */
export function Switch({
    checked,
    onChange,
    label,
    description,
    disabled = false,
    name,
    className,
}: SwitchProps) {
    const toggle = (
        <HeadlessSwitch
            checked={checked}
            onChange={onChange}
            disabled={disabled}
            name={name}
            className={cn(
                'ease-hig duration-base relative inline-flex h-[1.625rem] w-[2.75rem] shrink-0 cursor-pointer rounded-full border border-transparent p-0.5 transition',
                checked ? 'bg-success' : 'bg-busy/60',
                disabled && 'cursor-not-allowed opacity-50',
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'ease-hig duration-base pointer-events-none inline-block size-[1.375rem] rounded-full bg-white shadow transition',
                    checked ? 'translate-x-[1.125rem]' : 'translate-x-0',
                )}
            />
        </HeadlessSwitch>
    );

    if (label === undefined) {
        return <span className={className}>{toggle}</span>;
    }

    return (
        <HeadlessField
            className={cn('flex items-center justify-between gap-4', className)}
        >
            <span className="min-w-0">
                <Label className="text-subhead text-content block font-medium">
                    {label}
                </Label>
                {description !== undefined && (
                    <span className="text-footnote text-content-secondary block">
                        {description}
                    </span>
                )}
            </span>
            {toggle}
        </HeadlessField>
    );
}
