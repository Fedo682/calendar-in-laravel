import { cn } from '@/lib/utils';
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Fragment } from 'react';

export type ModalWidth = 'sm' | 'md' | 'lg' | 'xl' | '2xl';

const WIDTHS: Record<ModalWidth, string> = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
};

export interface ModalProps {
    open: boolean;
    onClose: () => void;
    title?: ReactNode;
    description?: ReactNode;
    width?: ModalWidth;
    /** Suppress the close button and the backdrop/Escape dismissal. */
    dismissible?: boolean;
    /** Buttons, laid out at the foot of the sheet above a hairline. */
    footer?: ReactNode;
    children?: ReactNode;
}

/**
 * The thickest material in the system, because it is the only surface that
 * sits over arbitrary content and still has to be readable.
 *
 * Both transitions run on `--ease-hig`, UIKit's sheet curve: the panel leaves
 * fast and arrives slowly, which is what makes a dialog feel like it was
 * placed rather than popped.
 */
export default function Modal({
    open,
    onClose,
    title,
    description,
    width = 'lg',
    dismissible = true,
    footer,
    children,
}: ModalProps) {
    const close = () => {
        if (dismissible) {
            onClose();
        }
    };

    return (
        <Transition show={open} as={Fragment}>
            <Dialog
                as="div"
                onClose={close}
                className="relative z-50"
            >
                <TransitionChild
                    as={Fragment}
                    enter="ease-hig-out duration-base"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-hig duration-fast"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div
                        aria-hidden="true"
                        className="bg-overlay fixed inset-0 backdrop-blur-[2px]"
                    />
                </TransitionChild>

                <div className="fixed inset-0 overflow-y-auto">
                    <div className="flex min-h-full items-end justify-center p-4 sm:items-center">
                        <TransitionChild
                            as={Fragment}
                            enter="ease-hig-out duration-base"
                            enterFrom="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
                            enterTo="opacity-100 translate-y-0 sm:scale-100"
                            leave="ease-hig duration-fast"
                            leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                            leaveTo="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
                        >
                            <DialogPanel
                                className={cn(
                                    'material-thick rounded-sheet shadow-modal w-full transform text-left transition-all',
                                    WIDTHS[width],
                                )}
                            >
                                {(title !== undefined || dismissible) && (
                                    <div className="hairline-b flex items-start justify-between gap-4 px-5 py-4">
                                        <div className="min-w-0">
                                            {title !== undefined && (
                                                <DialogTitle className="text-headline text-content">
                                                    {title}
                                                </DialogTitle>
                                            )}
                                            {description !== undefined && (
                                                <p className="text-footnote text-content-secondary mt-1">
                                                    {description}
                                                </p>
                                            )}
                                        </div>

                                        {dismissible && (
                                            <button
                                                type="button"
                                                onClick={onClose}
                                                aria-label="Close"
                                                className="text-content-secondary hover:bg-surface-raised hover:text-content rounded-control ease-hig duration-fast -me-1 -mt-1 inline-flex size-8 shrink-0 items-center justify-center transition"
                                            >
                                                <X
                                                    aria-hidden="true"
                                                    className="size-4"
                                                />
                                            </button>
                                        )}
                                    </div>
                                )}

                                <div className="px-5 py-4">{children}</div>

                                {footer !== undefined && (
                                    <div className="hairline-t flex items-center justify-end gap-2 px-5 py-4">
                                        {footer}
                                    </div>
                                )}
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </Transition>
    );
}
