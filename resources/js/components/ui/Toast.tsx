import { cn } from '@/lib/utils';
import { Transition } from '@headlessui/react';
import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Fragment, useSyncExternalStore } from 'react';

export type ToastTone = 'success' | 'error' | 'info' | 'warning';

export interface ToastOptions {
    title: string;
    description?: string;
    tone?: ToastTone;
    /** Milliseconds on screen. `0` pins the toast until it is dismissed. */
    duration?: number;
    /**
     * Stable identity. Showing the same id twice replaces the existing toast
     * instead of stacking a duplicate - which is what stops a double-submitted
     * form from producing two identical banners.
     */
    id?: string;
}

interface ToastRecord {
    id: string;
    title: string;
    description?: string;
    tone: ToastTone;
    duration: number;
    /** False while the exit transition plays, before the record is dropped. */
    open: boolean;
}

/* -------------------------------------------------------------------------
 * Store
 *
 * Module-level rather than a React context on purpose: a toast is fired from
 * event handlers, Inertia callbacks and the flash-prop hook, and requiring a
 * provider above every one of those would mean wrapping the app twice (once
 * for the shell, once for pages that render their own chrome).
 * ---------------------------------------------------------------------- */

const EXIT_MS = 220;
const DEFAULT_DURATION = 5000;

let records: ToastRecord[] = [];
const listeners = new Set<() => void>();
const timers = new Map<string, ReturnType<typeof setTimeout>>();

function emit(): void {
    for (const listener of listeners) {
        listener();
    }
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

function getSnapshot(): ToastRecord[] {
    return records;
}

/** Nothing has been fired yet at render time on the server. */
const EMPTY: ToastRecord[] = [];

function getServerSnapshot(): ToastRecord[] {
    return EMPTY;
}

function clearTimer(id: string): void {
    const timer = timers.get(id);

    if (timer !== undefined) {
        clearTimeout(timer);
        timers.delete(id);
    }
}

function remove(id: string): void {
    records = records.filter((record) => record.id !== id);
    emit();
}

function dismiss(id: string): void {
    clearTimer(id);

    records = records.map((record) =>
        record.id === id ? { ...record, open: false } : record,
    );
    emit();

    // Held just long enough for the exit transition; removing immediately
    // would snap the toast out of existence mid-fade.
    setTimeout(() => {
        remove(id);
    }, EXIT_MS);
}

let sequence = 0;

function show(options: ToastOptions): string {
    const id = options.id ?? `toast-${String(++sequence)}`;
    const duration = options.duration ?? DEFAULT_DURATION;

    const record: ToastRecord = {
        id,
        title: options.title,
        description: options.description,
        tone: options.tone ?? 'info',
        duration,
        open: true,
    };

    clearTimer(id);

    const existing = records.some((candidate) => candidate.id === id);

    records = existing
        ? records.map((candidate) => (candidate.id === id ? record : candidate))
        : [...records, record];

    emit();

    if (duration > 0) {
        timers.set(
            id,
            setTimeout(() => {
                dismiss(id);
            }, duration),
        );
    }

    return id;
}

function withTone(tone: ToastTone) {
    return (title: string, options: Omit<ToastOptions, 'title' | 'tone'> = {}) =>
        show({ ...options, title, tone });
}

export const toast = {
    show,
    dismiss,
    success: withTone('success'),
    error: withTone('error'),
    info: withTone('info'),
    warning: withTone('warning'),
};

/** The live toast list. Exposed for anything that wants to render its own. */
export function useToasts(): ToastRecord[] {
    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}

/* -------------------------------------------------------------------------
 * Presentation
 * ---------------------------------------------------------------------- */

const TONE_ICONS: Record<ToastTone, LucideIcon> = {
    success: CircleCheck,
    error: CircleAlert,
    info: Info,
    warning: TriangleAlert,
};

const TONE_COLORS: Record<ToastTone, string> = {
    success: 'text-success',
    error: 'text-danger',
    info: 'text-accent',
    warning: 'text-warning',
};

function Toast({ record }: { record: ToastRecord }) {
    const Icon = TONE_ICONS[record.tone];

    return (
        <Transition
            appear
            show={record.open}
            as={Fragment}
            enter="ease-hig-out duration-base"
            enterFrom="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-4 scale-95"
            enterTo="opacity-100 translate-y-0 sm:translate-x-0 scale-100"
            leave="ease-hig duration-fast"
            leaveFrom="opacity-100 translate-x-0 scale-100"
            leaveTo="opacity-0 scale-95 sm:translate-x-4"
        >
            <div
                role={record.tone === 'error' ? 'alert' : 'status'}
                className="material-thick rounded-card shadow-overlay pointer-events-auto flex w-full items-start gap-3 p-3.5 transition sm:w-80"
            >
                <Icon
                    aria-hidden="true"
                    className={cn('mt-px size-4 shrink-0', TONE_COLORS[record.tone])}
                />

                <div className="min-w-0 flex-1">
                    <p className="text-subhead text-content font-medium">
                        {record.title}
                    </p>
                    {record.description !== undefined && (
                        <p className="text-footnote text-content-secondary mt-0.5">
                            {record.description}
                        </p>
                    )}
                </div>

                <button
                    type="button"
                    aria-label="Dismiss"
                    onClick={() => {
                        dismiss(record.id);
                    }}
                    className="text-content-tertiary hover:text-content rounded-control ease-hig duration-fast -mt-0.5 -me-0.5 inline-flex size-6 shrink-0 items-center justify-center transition"
                >
                    <X aria-hidden="true" className="size-3.5" />
                </button>
            </div>
        </Transition>
    );
}

/**
 * Mount once, near the root. `pointer-events-none` on the container with
 * `pointer-events-auto` on each toast keeps the empty column from swallowing
 * clicks meant for the page underneath.
 */
export function Toaster({ className }: { className?: string }) {
    const items = useToasts();

    return (
        <div
            aria-live="polite"
            aria-atomic="false"
            className={cn(
                'pointer-events-none fixed inset-x-4 top-4 z-[60] flex flex-col items-center gap-2 sm:inset-x-auto sm:end-4 sm:items-end',
                className,
            )}
        >
            {items.map((record) => (
                <Toast key={record.id} record={record} />
            ))}
        </div>
    );
}

export default Toast;
