import { Button, Field, Input, Modal } from '@/components/ui';
import { useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { useRef, useState } from 'react';

export default function DeleteUserForm() {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <section className="space-y-4">
            <header>
                <h2 className="text-headline text-content">Delete account</h2>

                <p className="text-content-secondary text-footnote mt-1">
                    Once your account is deleted, all of its resources and data
                    will be permanently deleted. Before deleting your account,
                    please download any data or information that you wish to
                    retain.
                </p>
            </header>

            <Button variant="destructive" onClick={confirmUserDeletion}>
                Delete account
            </Button>

            <Modal
                open={confirmingUserDeletion}
                onClose={closeModal}
                title="Are you sure you want to delete your account?"
                description="Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account."
                footer={
                    <>
                        <Button variant="secondary" onClick={closeModal}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            form="delete-user-form"
                            variant="destructive"
                            loading={processing}
                        >
                            Delete account
                        </Button>
                    </>
                }
            >
                <form id="delete-user-form" onSubmit={deleteUser}>
                    <Field label="Password" error={errors.password}>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            autoFocus
                            placeholder="Password"
                            invalid={Boolean(errors.password)}
                        />
                    </Field>
                </form>
            </Modal>
        </section>
    );
}
