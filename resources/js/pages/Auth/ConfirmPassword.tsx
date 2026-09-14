import { Button, Field, Input } from '@/components/ui';
import GuestLayout from '@/layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirm Password" />

            <p className="text-content-secondary text-footnote mb-4">
                This is a secure area of the application. Please confirm your
                password before continuing.
            </p>

            <form onSubmit={submit} className="space-y-4">
                <Field label="Password" error={errors.password}>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        autoFocus
                        invalid={Boolean(errors.password)}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </Field>

                <div className="flex items-center justify-end pt-2">
                    <Button type="submit" loading={processing}>
                        Confirm
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
