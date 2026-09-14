import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
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
        <Dialog open={open} onClose={onCancel} className="relative z-[60]">
            <div className="fixed inset-0 bg-black/30" aria-hidden="true" />
            <div className="fixed inset-0 flex items-center justify-center p-4">
                <DialogPanel className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                    <DialogTitle className="mb-1 text-base font-semibold text-gray-900">
                        {action === 'delete'
                            ? 'Delete recurring event'
                            : 'Save recurring event'}
                    </DialogTitle>
                    <p className="mb-4 text-sm text-gray-600">
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
                                className={`flex cursor-pointer gap-3 rounded-md border px-3 py-2 transition ${
                                    scope === option.value
                                        ? 'border-indigo-500 bg-indigo-50'
                                        : 'border-gray-200 hover:bg-gray-50'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="recurrence_scope"
                                    value={option.value}
                                    checked={scope === option.value}
                                    onChange={() => setScope(option.value)}
                                    className="mt-1 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span>
                                    <span className="block text-sm font-medium text-gray-900">
                                        {option.label}
                                    </span>
                                    <span className="block text-xs text-gray-500">
                                        {option.hint}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </fieldset>

                    <div className="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={onCancel}
                            className="rounded-lg border border-gray-300 px-4 py-2 font-medium text-gray-700 transition hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={() => onConfirm(scope)}
                            disabled={processing}
                            className={`rounded-lg px-4 py-2 font-medium text-white transition disabled:opacity-50 ${
                                action === 'delete'
                                    ? 'bg-red-600 hover:bg-red-500'
                                    : 'bg-gray-800 hover:bg-gray-700'
                            }`}
                        >
                            {action === 'delete' ? 'Delete' : 'Save'}
                        </button>
                    </div>
                </DialogPanel>
            </div>
        </Dialog>
    );
}
