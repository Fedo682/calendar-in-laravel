import { toast } from '@/components/ui/Toast';
import type { FlashMessages, SharedProps } from '@/types/shared';
import type { PageProps } from '@inertiajs/core';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/**
 * Turn the `flash` shared prop into toasts.
 *
 * Several pages grew their own copy of this: a `useState` holding a banner, a
 * `useEffect` watching `flash.success`, and a hand-rolled dismiss timer. They
 * all drifted (different durations, no error handling, no dismiss button).
 * This is the one implementation; `AppShell` calls it, so every page inside
 * the shell gets flash toasts without asking.
 *
 * Keyed on the identity of the `flash` object rather than on the message
 * text, because Inertia builds a fresh props object per response: performing
 * the same action twice and getting the same message twice must produce two
 * toasts, which a `[flash.success]` dependency would silently swallow.
 */
export function useFlashToasts(): void {
    const page = usePage<SharedProps & PageProps>();
    const flash: FlashMessages | undefined = page.props.flash;

    const lastHandled = useRef<FlashMessages | null>(null);

    useEffect(() => {
        if (flash === undefined || lastHandled.current === flash) {
            return;
        }

        lastHandled.current = flash;

        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash]);
}

export default useFlashToasts;
