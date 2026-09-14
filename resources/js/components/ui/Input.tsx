import { cn } from '@/lib/utils';
import { ChevronDown } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type {
    InputHTMLAttributes,
    Ref,
    SelectHTMLAttributes,
    TextareaHTMLAttributes,
} from 'react';

/**
 * Every form control is styled here, from scratch.
 *
 * @tailwindcss/forms is deliberately not installed: a global forms reset
 * exists to make browser defaults uniform, and then a design system spends
 * its time overriding that reset. One explicit class string per control is
 * shorter and far easier to reason about than reset-plus-override.
 *
 * `appearance-none` is load-bearing without that reset - it is what stops
 * the native chrome (the select arrow, the iOS inner shadow on inputs) from
 * showing through.
 */
const CONTROL_BASE =
    'w-full appearance-none rounded-field border border-hairline bg-surface-raised text-content text-body ease-hig duration-fast transition placeholder:text-content-tertiary focus:border-accent disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-danger';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    /** Rendered inside the field, on the leading edge. */
    icon?: LucideIcon;
    invalid?: boolean;
    /** React 19 passes refs as a normal prop - no forwardRef needed. */
    ref?: Ref<HTMLInputElement>;
}

export default function Input({
    icon: Icon,
    invalid = false,
    className,
    type = 'text',
    ref,
    ...props
}: InputProps) {
    const field = (
        <input
            {...props}
            ref={ref}
            type={type}
            aria-invalid={invalid || undefined}
            className={cn(
                CONTROL_BASE,
                'h-10 px-3',
                Icon && 'ps-9',
                className,
            )}
        />
    );

    if (!Icon) {
        return field;
    }

    return (
        <div className="relative">
            <Icon
                aria-hidden="true"
                className="text-content-tertiary pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2"
            />
            {field}
        </div>
    );
}

export interface TextareaProps
    extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    invalid?: boolean;
    ref?: Ref<HTMLTextAreaElement>;
}

export function Textarea({
    invalid = false,
    className,
    rows = 4,
    ref,
    ...props
}: TextareaProps) {
    return (
        <textarea
            {...props}
            ref={ref}
            rows={rows}
            aria-invalid={invalid || undefined}
            className={cn(CONTROL_BASE, 'resize-y px-3 py-2', className)}
        />
    );
}

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    invalid?: boolean;
    ref?: Ref<HTMLSelectElement>;
}

export function Select({
    invalid = false,
    className,
    children,
    ref,
    ...props
}: SelectProps) {
    return (
        <div className="relative">
            <select
                {...props}
                ref={ref}
                aria-invalid={invalid || undefined}
                className={cn(CONTROL_BASE, 'h-10 pe-9 ps-3', className)}
            >
                {children}
            </select>
            {/* The native arrow went with appearance-none, so draw one. */}
            <ChevronDown
                aria-hidden="true"
                className="text-content-tertiary pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2"
            />
        </div>
    );
}
