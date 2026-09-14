import { Card } from '@/components/ui';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import type { TimezoneGroup } from './types';

interface EditProps {
    mustVerifyEmail: boolean;
    status?: string;
    timezoneOptions: TimezoneGroup[];
}

export default function Edit({
    mustVerifyEmail,
    status,
    timezoneOptions,
}: EditProps) {
    return (
        <>
            <Head title="Profile" />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <Card material="thin">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                        timezoneOptions={timezoneOptions}
                    />
                </Card>

                <Card material="thin">
                    <UpdatePasswordForm />
                </Card>

                <Card material="thin">
                    <DeleteUserForm />
                </Card>
            </div>
        </>
    );
}

Edit.layout = (page: ReactNode) => (
    <AuthenticatedLayout
        header={<h2 className="text-headline text-chrome-content">Profile</h2>}
    >
        {page}
    </AuthenticatedLayout>
);
