import type { SharedProps } from '@/types/shared';

declare global {
    /** The router object Ziggy returns when `route()` is called with no name. */
    interface ZiggyRouter {
        /** Name of the route currently being viewed. */
        current(): string | undefined;
        /** Whether the current route matches `name` (wildcards allowed). */
        current(name: string, params?: unknown): boolean;
        params: Record<string, unknown>;
    }

    // Provided at runtime by the Ziggy `@routes` Blade directive.
    function route(): ZiggyRouter;
    function route(name: string, params?: unknown, absolute?: boolean): string;
}

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        // Mirrors HandleInertiaRequests::share() exactly. Inertia contributes
        // `errors` on top of this; `PageProps`'s index signature still allows
        // page-specific props through as `unknown`.
        sharedPageProps: SharedProps;
    }
}
