import '../css/app.css';
import './bootstrap';

import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { createInertiaApp } from '@inertiajs/react';
import type { ResolvedComponent } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ReactNode } from 'react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const pages = import.meta.glob<{ default: ResolvedComponent }>(
    './pages/**/*.tsx',
);

/**
 * Pages that render their own chrome. Everything else gets the authenticated
 * layout assigned below, which keeps one nav instance mounted across visits
 * instead of tearing it down and rebuilding it on every navigation.
 */
function usesOwnLayout(name: string): boolean {
    return name.startsWith('Auth/') || name === 'welcome';
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const page = await resolvePageComponent(`./pages/${name}.tsx`, pages);

        if (!usesOwnLayout(name)) {
            // `??=` so a page that declares its own `layout` (to pass a header
            // through, say) keeps it.
            page.default.layout ??= (child: ReactNode) => (
                <AuthenticatedLayout>{child}</AuthenticatedLayout>
            );
        }

        return page.default;
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
