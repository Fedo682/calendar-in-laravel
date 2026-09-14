import { cn } from '@/lib/utils';
import { CircleAlert } from 'lucide-react';
import { useId } from 'react';
import type { ReactElement, ReactNode } from 'react';
import { cloneElement, isValidElement } from 'react';

export interface FieldProps {
    label: ReactNode;
    /** Explanatory text under the control. Hidden while an error shows. */
    hint?: ReactNode;
    error?: string | null;
    required?: boolean;
    className?: string;
    /** Omit the visible label but keep it for screen readers. */
    hideLabel?: boolean;
    children: ReactNode;
}

/**
 * Label, control, hint and error as one block, with the wiring that makes a
 * screen reader read them together.
 *
 * The control is cloned to receive `id`, `aria-describedby` and
 * `aria-invalid`, so a caller writes `<Field label="Name"><Input /></Field>`
 * and gets a correctly associated field without repeating an id three times.
 * A caller that sets its own `id` keeps it.
 */
export default function Field({
    label,
    hint,
    error,
    required = false,
    className,
    hideLabel = false,
    children,
}: FieldProps) {
    const generatedId = useId();
    const hintId = `${generatedId}-hint`;
    const errorId = `${generatedId}-error`;

    const describedBy =
        [error ? errorId : null, hint && !error ? hintId : null]
            .filter(Boolean)
            .join(' ') || undefined;

    let control = children;

    if (isValidElement(children)) {
        const element = children as ReactElement<Record<string, unknown>>;

        control = cloneElement(element, {
            id: element.props.id ?? generatedId,
            'aria-describedby':
                element.props['aria-describedby'] ?? describedBy,
            'aria-invalid': error ? true : element.props['aria-invalid'],
            required: element.props.required ?? (required || undefined),
        });
    }

    return (
        <div className={cn('space-y-1.5', className)}>
            <label
                htmlFor={generatedId}
                className={cn(
                    'text-subhead text-content block font-medium',
                    hideLabel && 'sr-only',
                )}
            >
                {label}
                {required && (
                    <span aria-hidden="true" className="text-danger ms-0.5">
                        *
                    </span>
                )}
            </label>

            {control}

            {error ? (
                <p
                    id={errorId}
                    role="alert"
                    className="text-footnote text-danger flex items-center gap-1"
                >
                    <CircleAlert aria-hidden="true" className="size-3.5" />
                    {error}
                </p>
            ) : hint ? (
                <p
                    id={hintId}
                    className="text-footnote text-content-secondary"
                >
                    {hint}
                </p>
            ) : null}
        </div>
    );
}
