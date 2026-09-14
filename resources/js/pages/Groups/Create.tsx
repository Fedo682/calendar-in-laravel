import { Button, Card, Field, Input, Textarea } from '@/components/ui';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler, ReactNode } from 'react';

export default function CreateGroup() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/groups');
    };

    return (
        <>
            <Head title="Create Group" />

            <div className="mx-auto max-w-lg px-4 py-8 sm:px-6 lg:px-8">
                <Card material="thin">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Group name" error={errors.name}>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="e.g., Engineering, Sales"
                                autoFocus
                                invalid={Boolean(errors.name)}
                            />
                        </Field>

                        <Field
                            label="Description (optional)"
                            error={errors.description}
                        >
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder="What is this group for?"
                                rows={4}
                                invalid={Boolean(errors.description)}
                            />
                        </Field>

                        <div className="flex items-center justify-end gap-3 pt-2">
                            <Link
                                href="/groups"
                                className="text-content-secondary hover:text-content focus-visible:outline-accent rounded-control text-footnote font-medium focus-visible:outline-2"
                            >
                                Cancel
                            </Link>
                            <Button type="submit" loading={processing}>
                                Create group
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </>
    );
}

CreateGroup.layout = (page: ReactNode) => (
    <AuthenticatedLayout
        header={
            <h2 className="text-headline text-chrome-content">
                Create a new group
            </h2>
        }
    >
        {page}
    </AuthenticatedLayout>
);
