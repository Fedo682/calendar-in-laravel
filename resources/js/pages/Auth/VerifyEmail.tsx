import { Button } from '@/components/ui';
import GuestLayout from '@/layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

interface VerifyEmailProps {
    status?: string;
}

export default function VerifyEmail({ status }: VerifyEmailProps) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <p className="text-content-secondary text-footnote mb-4">
                Thanks for signing up! Before getting started, could you verify
                your email address by clicking on the link we just emailed to
                you? If you didn't receive the email, we will gladly send you
                another.
            </p>

            {status === 'verification-link-sent' && (
                <p className="text-success text-footnote mb-4 font-medium">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </p>
            )}

            <form
                onSubmit={submit}
                className="flex items-center justify-between pt-2"
            >
                <Button type="submit" loading={processing}>
                    Resend verification email
                </Button>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="text-content-secondary hover:text-content focus-visible:outline-accent rounded-control text-footnote underline focus-visible:outline-2"
                >
                    Log out
                </Link>
            </form>
        </GuestLayout>
    );
}
