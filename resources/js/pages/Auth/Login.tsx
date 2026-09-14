import { Button, Checkbox, Field, Input } from '@/components/ui';
import GuestLayout from '@/layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            {status && (
                <p className="text-success text-footnote mb-4 font-medium">
                    {status}
                </p>
            )}

            <form onSubmit={submit} className="space-y-4">
                <Field label="Email" error={errors.email} required>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        required
                        invalid={Boolean(errors.email)}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </Field>

                <Field label="Password" error={errors.password} required>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        required
                        invalid={Boolean(errors.password)}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </Field>

                <Checkbox
                    name="remember"
                    checked={data.remember}
                    onChange={(e) => setData('remember', e.target.checked)}
                    label="Remember me"
                />

                <div className="flex items-center justify-end gap-4 pt-2">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-content-secondary hover:text-content focus-visible:outline-accent rounded-control text-footnote underline focus-visible:outline-2"
                        >
                            Forgot your password?
                        </Link>
                    )}

                    <Button type="submit" loading={processing}>
                        Log in
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
