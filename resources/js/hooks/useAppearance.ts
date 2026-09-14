import { appearance as appearanceRoute } from '@/routes/profile';
import { usePageProps } from '@/types/shared';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

/** What the user chose. `system` defers to the OS. */
export type Appearance = 'system' | 'light' | 'dark';

/** What `system` actually resolved to right now. */
export type ResolvedAppearance = 'light' | 'dark';

/**
 * Shared with the blocking script in `app.blade.php`. That script has to run
 * before this module is even downloaded, so the key is duplicated there by
 * necessity - change one and you must change the other.
 */
const STORAGE_KEY = 'theme';

/**
 * The app is dark by default, so the media query asks about *light*: a client
 * that reports no preference at all falls through to dark rather than to the
 * browser's own default of light.
 */
const LIGHT_QUERY = '(prefers-color-scheme: light)';

function readStoredAppearance(): Appearance | null {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        return stored === 'light' || stored === 'dark' || stored === 'system'
            ? stored
            : null;
    } catch {
        // Safari in private mode throws on any localStorage access.
        return null;
    }
}

function writeStoredAppearance(value: Appearance): void {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Losing the fast path costs a flash on next load, nothing more - the
        // DB copy is what actually follows the user between devices.
    }
}

export function resolveAppearance(value: Appearance): ResolvedAppearance {
    if (value !== 'system') {
        return value;
    }

    return window.matchMedia(LIGHT_QUERY).matches ? 'light' : 'dark';
}

/**
 * Stamp the resolved appearance onto `<html>`.
 *
 * Both classes are toggled rather than one being added, because the blocking
 * script may already have written the other one.
 */
export function applyAppearance(value: Appearance): void {
    const resolved = resolveAppearance(value);
    const root = document.documentElement;

    root.classList.toggle('dark', resolved === 'dark');
    root.classList.toggle('light', resolved === 'light');
}

interface UseAppearanceResult {
    /** The stored preference, including `system`. */
    appearance: Appearance;
    /** What `appearance` currently renders as. */
    resolved: ResolvedAppearance;
    setAppearance: (next: Appearance) => void;
}

/**
 * The theme preference, persisted in two places on purpose.
 *
 * localStorage is read by a blocking script in the document head, which is
 * the only way to avoid a flash of the wrong theme on first paint. The DB
 * column is what carries the preference to the user's other devices. Neither
 * alone is sufficient, so both are written on every change.
 */
export function useAppearance(): UseAppearanceResult {
    const { viewer } = usePageProps();
    const storedOnServer = viewer.theme;

    const [appearance, setStoredAppearance] = useState<Appearance>(
        () => readStoredAppearance() ?? storedOnServer,
    );

    const [resolved, setResolved] = useState<ResolvedAppearance>('dark');

    // A device that has never toggled the theme has nothing in localStorage;
    // adopt whatever the account carries so the preference actually travels.
    useEffect(() => {
        if (readStoredAppearance() === null) {
            setStoredAppearance(storedOnServer);
        }
    }, [storedOnServer]);

    useEffect(() => {
        const query = window.matchMedia(LIGHT_QUERY);

        const sync = () => {
            applyAppearance(appearance);
            setResolved(resolveAppearance(appearance));
        };

        sync();

        // Subscribed unconditionally: when the preference is an explicit
        // light/dark, `sync` is idempotent and the OS change is a no-op, which
        // is cheaper than tearing the listener down and rebuilding it.
        query.addEventListener('change', sync);

        return () => {
            query.removeEventListener('change', sync);
        };
    }, [appearance]);

    const setAppearance = useCallback((next: Appearance) => {
        setStoredAppearance(next);
        writeStoredAppearance(next);
        // Applied here as well as in the effect so the class lands in the same
        // tick as the click, with no frame of the old theme in between.
        applyAppearance(next);

        router.patch(
            appearanceRoute.url(),
            { theme: next },
            {
                preserveScroll: true,
                preserveState: true,
                // The endpoint redirects back; reloading the whole page's props
                // to persist one preference would be a waste.
                only: ['viewer'],
            },
        );
    }, []);

    return { appearance, resolved, setAppearance };
}
