import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const pages = import.meta.glob('./pages/**/*.{jsx,tsx}', { eager: true });

void createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        for (const path of Object.keys(pages)) {
            if (path.endsWith(`${name}.jsx`) || path.endsWith(`${name}.tsx`)) {
                return pages[path];
            }
        }
        throw new Error(`Page not found: ${name}`);
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
