import { cn } from '@/lib/utils';
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import type { ReactNode } from 'react';
import { Fragment } from 'react';

export type SheetSide = 'bottom' | 'left' | 'right';

const PANEL_POSITION: Record<SheetSide, string> = {
    bottom: 'inset-x-0 bottom-0 max-h-[92vh] rounded-t-sheet',
    left: 'inset-y-0 start-0 h-full w-80 max-w-[88vw] rounded-e-sheet',
    right: 'inset-y-0 end-0 h-full w-80 max-w-[88vw] rounded-s-sheet',
};

const ENTER_FROM: Record<SheetSide, string> = {
    bottom: 'translate-y-full',
    left: '-translate-x-full',
    right: 'translate-x-full',
};

export interface SheetProps {
    open: boolean;
    onClose: () => void;
    side?: SheetSide;
    title?: ReactNode;
    /** The drag handle iOS puts at the top of a sheet. Bottom sheets only. */
    grabber?: boolean;
    className?: string;
    /** Replaces the scrolling body's padding - `p-0` for a flush drawer. */
    bodyClassName?: string;
    children?: ReactNode;
}

/**
 * The same presentation as `Modal`, anchored to an edge instead of centred.
 *
 * On a phone a dialog that floats in the middle is the wrong shape - the
 * thumb is at the bottom of the screen. This is the mobile counterpart, and
 * also carries the navigation drawer that `AppShell` opens below the `lg`
 * breakpoint.
 */
export default function Sheet({
    open,
    onClose,
    side = 'bottom',
    title,
    grabber = true,
    className,
    bodyClassName,
    children,
}: SheetProps) {
    return (
        <Transition show={open} as={Fragment}>
            <Dialog as="div" onClose={onClose} className="relative z-50">
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
                        className="bg-overlay fixed inset-0"
                    />
                </TransitionChild>

                <TransitionChild
                    as={Fragment}
                    enter="ease-hig-out duration-base"
                    enterFrom={ENTER_FROM[side]}
                    enterTo="translate-y-0 translate-x-0"
                    leave="ease-hig duration-fast"
                    leaveFrom="translate-y-0 translate-x-0"
                    leaveTo={ENTER_FROM[side]}
                >
                    <DialogPanel
                        className={cn(
                            'material-thick shadow-modal fixed flex transform flex-col overflow-hidden transition-transform',
                            PANEL_POSITION[side],
                            className,
                        )}
                    >
                        {grabber && side === 'bottom' && (
                            <span
                                aria-hidden="true"
                                className="bg-content-tertiary mx-auto mt-2 h-1 w-9 shrink-0 rounded-full opacity-60"
                            />
                        )}

                        {title !== undefined && (
                            <DialogTitle className="text-headline text-content hairline-b px-5 py-4">
                                {title}
                            </DialogTitle>
                        )}

                        <div
                            className={cn(
                                'flex min-h-0 flex-1 flex-col overflow-y-auto px-5 py-4',
                                bodyClassName,
                            )}
                        >
                            {children}
                        </div>
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
