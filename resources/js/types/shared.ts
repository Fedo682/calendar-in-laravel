import type { PageProps } from '@inertiajs/core';
import { usePage } from '@inertiajs/react';
import type { User } from '@/types/auth';

/**
 * Props shared with every Inertia response by
 * `App\Http\Middleware\HandleInertiaRequests::share()`.
 *
 * Keep this in lockstep with that method - nothing else is shared, so nothing
 * else belongs here. Inertia adds `errors` on top of these itself.
 */

export interface FlashMessages {
    success: string | null;
    error: string | null;
    /** Set only right after issuing a new ICS feed token - the one time its plaintext is available. */
    new_feed_url: string | null;
}

/** Per-user display preferences. Defaults are applied server-side. */
export interface ViewerPreferences {
    timezone: string;
    theme: 'light' | 'dark' | 'system';
    /** 0 = Sunday ... 6 = Saturday. */
    week_starts_on: 0 | 1 | 2 | 3 | 4 | 5 | 6;
    time_format: '12h' | '24h';
}

export interface SharedProps {
    auth: {
        /** Null on guest routes - the middleware runs on those too. */
        user: User | null;
        is_super_admin: boolean;
    };
    flash: FlashMessages;
    viewer: ViewerPreferences;
}

/**
 * `usePage().props`, typed.
 *
 * Pass the page's own props as `T` to get them alongside the shared props:
 *
 * ```ts
 * const { auth, group } = usePageProps<{ group: GroupSummary }>();
 * ```
 */
export function usePageProps<T extends object = PageProps>() {
    // Intersecting with PageProps keeps the generic open to plain `interface`
    // declarations, which are not assignable to an index signature on their
    // own.
    return usePage<T & PageProps>().props;
}
