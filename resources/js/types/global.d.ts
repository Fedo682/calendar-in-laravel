import type { Auth } from '@/types/auth';

declare global {
    // Provided at runtime by the Ziggy `@routes` Blade directive.
    function route(name?: string, params?: unknown, absolute?: boolean): string;
}

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
