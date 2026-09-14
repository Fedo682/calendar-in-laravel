import { Button, Field, Input } from '@/components/ui';
import GuestLayout from '@/layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

interface ResetPasswordProps {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Reset Password" />

            <form onSubmit={submit} className="space-y-4">
                <Field label="Email" error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        invalid={Boolean(errors.email)}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </Field>

                <Field label="Password" error={errors.password}>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        autoFocus
                        invalid={Boolean(errors.password)}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </Field>

                <Field
                    label="Confirm password"
                    error={errors.password_confirmation}
                >
                    <Input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        invalid={Boolean(errors.password_confirmation)}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                </Field>

                <div className="flex items-center justify-end pt-2">
                    <Button type="submit" loading={processing}>
                        Reset password
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
