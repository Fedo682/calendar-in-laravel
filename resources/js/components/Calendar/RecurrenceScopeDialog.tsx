import { Button, Modal } from '@/components/ui';
import { cn } from '@/lib/utils';
import { useState } from 'react';

/**
 * The question every calendar asks before changing something that repeats.
 *
 * The three answers are genuinely different writes on the server - append an
 * EXDATE, split the series, or update the master - so the choice is made
 * explicitly here rather than inferred, and defaults to the least
 * destructive one.
 */

export type RecurrenceScope = 'this' | 'following' | 'all';

interface RecurrenceScopeDialogProps {
    open: boolean;
    /** 'save' and 'delete' differ enough in consequence to be worth wording. */
    action: 'save' | 'delete';
    onCancel: () => void;
    onConfirm: (scope: RecurrenceScope) => void;
    processing?: boolean;
}

const OPTIONS: Array<{
    value: RecurrenceScope;
    label: string;
    hint: string;
}> = [
    {
        value: 'this',
        label: 'This event',
        hint: 'Only the occurrence you opened.',
    },
    {
        value: 'following',
        label: 'This and following events',
        hint: 'Splits the series here; earlier occurrences are untouched.',
    },
    {
        value: 'all',
        label: 'All events',
        hint: 'Every occurrence, past and future.',
    },
];

export default function RecurrenceScopeDialog({
    open,
    action,
    onCancel,
    onConfirm,
    processing = false,
}: RecurrenceScopeDialogProps) {
    // Least destructive by default.
    const [scope, setScope] = useState<RecurrenceScope>('this');

    return (
        <Modal
            open={open}
            onClose={onCancel}
            title={
                action === 'delete'
                    ? 'Delete recurring event'
                    : 'Save recurring event'
            }
            width="sm"
        >
            <p className="text-content-secondary text-footnote mb-4">
                This event repeats. Which occurrences should this{' '}
                {action === 'delete' ? 'delete' : 'change'} apply to?
            </p>

            <fieldset className="space-y-2">
                <legend className="sr-only">
                    Which occurrences to {action}
                </legend>
                {OPTIONS.map((option) => (
                    <label
                        key={option.value}
                        className={cn(
                            'rounded-field ease-hig duration-fast flex cursor-pointer gap-3 border px-3 py-2 transition',
                            scope === option.value
                                ? 'border-accent bg-accent-soft'
                                : 'border-hairline hover:bg-surface-raised',
                        )}
                    >
                        <input
                            type="radio"
                            name="recurrence_scope"
                            value={option.value}
                            checked={scope === option.value}
                            onChange={() => setScope(option.value)}
                            className="accent-accent mt-1"
                        />
                        <span>
                            <span className="text-content text-footnote block font-medium">
                                {option.label}
                            </span>
                            <span className="text-content-tertiary text-caption1 block">
                                {option.hint}
                            </span>
                        </span>
                    </label>
                ))}
            </fieldset>

            <div className="mt-5 flex justify-end gap-2">
                <Button type="button" variant="secondary" onClick={onCancel}>
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant={action === 'delete' ? 'destructive' : 'primary'}
                    onClick={() => onConfirm(scope)}
                    loading={processing}
                >
                    {action === 'delete' ? 'Delete' : 'Save'}
                </Button>
            </div>
        </Modal>
    );
}
