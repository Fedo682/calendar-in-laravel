import ApplicationLogo from '@/components/ApplicationLogo';
import { Card } from '@/components/ui';
import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

/**
 * The shell for everything reachable while logged out.
 *
 * A single sheet centred on the canvas. It deliberately has no chrome - there
 * is no calendar to navigate yet, and the source list would have nothing in
 * it.
 */
export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="bg-canvas flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <Link href="/" className="mb-6">
                <ApplicationLogo className="text-content-secondary h-16 w-16 fill-current" />
            </Link>

            <Card material="thin" className="w-full sm:max-w-md">
                {children}
            </Card>
        </div>
    );
}
