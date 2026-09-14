import { Button, Field, Input } from '@/components/ui';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { useRef } from 'react';

export default function UpdatePasswordForm() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section>
            <header>
                <h2 className="text-headline text-content">Update password</h2>

                <p className="text-content-secondary text-footnote mt-1">
                    Ensure your account is using a long, random password to stay
                    secure.
                </p>
            </header>

            <form onSubmit={updatePassword} className="mt-6 space-y-4">
                <Field label="Current password" error={errors.current_password}>
                    <Input
                        id="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) =>
                            setData('current_password', e.target.value)
                        }
                        type="password"
                        autoComplete="current-password"
                        invalid={Boolean(errors.current_password)}
                    />
                </Field>

                <Field label="New password" error={errors.password}>
                    <Input
                        id="password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        type="password"
                        autoComplete="new-password"
                        invalid={Boolean(errors.password)}
                    />
                </Field>

                <Field
                    label="Confirm password"
                    error={errors.password_confirmation}
                >
                    <Input
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        type="password"
                        autoComplete="new-password"
                        invalid={Boolean(errors.password_confirmation)}
                    />
                </Field>

                <div className="flex items-center gap-4">
                    <Button type="submit" loading={processing}>
                        Save
                    </Button>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-content-secondary text-footnote">
                            Saved.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
