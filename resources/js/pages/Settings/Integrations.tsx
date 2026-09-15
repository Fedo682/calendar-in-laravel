import { Button, Card, Field, Input } from '@/components/ui';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';
import { usePageProps } from '@/types/shared';
import { Head, router, useForm } from '@inertiajs/react';
import { Rss } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

interface FeedToken {
    id: number;
    label: string | null;
    last_used_at: string | null;
    created_at: string;
}

interface Props {
    tokens: FeedToken[];
}

function formatWhen(iso: string | null): string {
    if (iso === null) return 'Never used';

    return new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function SettingsIntegrations({ tokens }: Props) {
    const { flash } = usePageProps<Props>();
    const [copied, setCopied] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ label: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/settings/integrations/ics-tokens', {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const copy = async (url: string) => {
        await navigator.clipboard.writeText(url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const revoke = (id: number) => {
        if (
            !window.confirm(
                "Revoke this feed URL? Anything subscribed to it will stop updating.",
            )
        ) {
            return;
        }
        router.delete(`/settings/integrations/ics-tokens/${id}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Integrations" />

            <div className="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {flash.new_feed_url && (
                    <Card material="thin" className="border-accent">
                        <h3 className="text-headline text-content mb-2">
                            Your new feed URL
                        </h3>
                        <p className="text-content-secondary text-footnote mb-3">
                            Copy this now - it won't be shown again. Add it to
                            iOS as a subscribed calendar, or paste it into
                            Google Calendar / Outlook's "subscribe from URL."
                        </p>
                        <div className="flex gap-2">
                            <Input
                                readOnly
                                value={flash.new_feed_url}
                                className="font-mono text-caption1"
                            />
                            <Button onClick={() => copy(flash.new_feed_url!)}>
                                {copied ? 'Copied' : 'Copy'}
                            </Button>
                        </div>
                        <a
                            href={flash.new_feed_url.replace(
                                /^https?:\/\//,
                                'webcal://',
                            )}
                            className="text-accent hover:underline text-footnote mt-2 inline-block"
                        >
                            Open in Calendar app (webcal://)
                        </a>
                    </Card>
                )}

                <Card material="thin" padded={false}>
                    <div className="border-hairline flex items-center gap-2 border-b px-6 py-4">
                        <Rss className="text-content-secondary size-4" />
                        <h3 className="text-headline text-content">
                            Calendar feed
                        </h3>
                    </div>

                    {tokens.length === 0 ? (
                        <div className="px-6 py-8 text-center">
                            <p className="text-content-secondary text-footnote mb-4">
                                No feed URL yet. Generate one to subscribe
                                from an iPhone, Google Calendar, or Outlook.
                            </p>
                            <Button
                                onClick={() =>
                                    post('/settings/integrations/ics-tokens')
                                }
                            >
                                Generate a feed URL
                            </Button>
                        </div>
                    ) : (
                        <ul className="divide-hairline divide-y">
                            {tokens.map((token) => (
                                <li
                                    key={token.id}
                                    className="flex items-center justify-between gap-3 px-6 py-3"
                                >
                                    <div>
                                        <p className="text-content text-footnote font-medium">
                                            {token.label || 'Calendar feed'}
                                        </p>
                                        <p className="text-content-tertiary text-caption1">
                                            {formatWhen(token.last_used_at)}
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => revoke(token.id)}
                                    >
                                        Revoke
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}

                    {tokens.length > 0 && (
                        <form
                            onSubmit={submit}
                            className="border-hairline flex items-end gap-2 border-t px-6 py-4"
                        >
                            <Field label="Add another device" className="flex-1">
                                <Input
                                    value={data.label}
                                    onChange={(e) =>
                                        setData('label', e.target.value)
                                    }
                                    placeholder="e.g. iPad"
                                />
                            </Field>
                            <Button type="submit" loading={processing}>
                                Generate
                            </Button>
                        </form>
                    )}
                </Card>
            </div>
        </>
    );
}

SettingsIntegrations.layout = (page: ReactNode) => (
    <AuthenticatedLayout
        header={
            <h2 className="text-headline text-chrome-content">
                Integrations
            </h2>
        }
    >
        {page}
    </AuthenticatedLayout>
);
