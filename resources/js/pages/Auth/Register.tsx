import { Button, Field, Input } from '@/components/ui';
import GuestLayout from '@/layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            <form onSubmit={submit} className="space-y-4">
                <Field label="Name" error={errors.name} required>
                    <Input
                        id="name"
                        name="name"
                        value={data.name}
                        autoComplete="name"
                        autoFocus
                        required
                        invalid={Boolean(errors.name)}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                </Field>

                <Field label="Email" error={errors.email} required>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
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
                        autoComplete="new-password"
                        required
                        invalid={Boolean(errors.password)}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </Field>

                <Field
                    label="Confirm password"
                    error={errors.password_confirmation}
                    required
                >
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        required
                        invalid={Boolean(errors.password_confirmation)}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                </Field>

                <div className="flex items-center justify-end gap-4 pt-2">
                    <Link
                        href={route('login')}
                        className="text-content-secondary hover:text-content focus-visible:outline-accent rounded-control text-footnote underline focus-visible:outline-2"
                    >
                        Already registered?
                    </Link>

                    <Button type="submit" loading={processing}>
                        Register
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
