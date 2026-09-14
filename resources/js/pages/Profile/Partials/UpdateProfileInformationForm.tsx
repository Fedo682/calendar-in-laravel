import { Button, Field, Input, Select } from '@/components/ui';
import { Transition } from '@headlessui/react';
import { Link, router, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { usePageProps } from '@/types/shared';
import type { User } from '@/types/auth';
import type { TimezoneGroup } from '../types';

interface UpdateProfileInformationProps {
    mustVerifyEmail: boolean;
    status?: string;
    timezoneOptions: TimezoneGroup[];
}

interface ProfileForm {
    name: string;
    email: string;
    timezone: string;
    // Deliberately wider than the shared prop's literal unions: a <select>
    // hands back a plain string, and the server validates the range anyway.
    week_starts_on: number;
    time_format: string;
}

const WEEK_DAYS = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];

/**
 * The zone this browser is actually in, or null where `Intl` cannot say.
 *
 * Read during render rather than in an effect: it is a synchronous, side-effect
 * free lookup, and it never reaches the server unless the user opts in.
 */
function detectBrowserZone(): string | null {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || null;
    } catch {
        return null;
    }
}

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    timezoneOptions,
}: UpdateProfileInformationProps) {
    // This form only ever renders behind auth, so `user` is non-null here -
    // narrowing it via the generic beats an assertion.
    const { auth, viewer } = usePageProps<{ auth: { user: User } }>();
    const user = auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm<ProfileForm>({
            name: user.name,
            email: user.email,
            // Preferences come from the shared `viewer` prop, which is typed;
            // `auth.user` widens anything outside its own type to `unknown`.
            timezone: viewer.timezone,
            week_starts_on: viewer.week_starts_on,
            time_format: viewer.time_format,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    const browserZone = detectBrowserZone();
    const zoneMismatch = browserZone !== null && browserZone !== data.timezone;

    // One click, one request. `setData` only lands on the next render, so the
    // detected zone goes into the request explicitly rather than being read
    // back out of form state that has not updated yet.
    const useBrowserZone = () => {
        if (browserZone === null) {
            return;
        }

        setData('timezone', browserZone);

        router.patch(
            route('profile.update'),
            { ...data, timezone: browserZone },
            { preserveScroll: true },
        );
    };

    return (
        <section>
            <header>
                <h2 className="text-headline text-content">
                    Profile information
                </h2>

                <p className="text-content-secondary text-footnote mt-1">
                    Update your account's profile information and email address.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <Field label="Name" error={errors.name} required>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus
                        autoComplete="name"
                        invalid={Boolean(errors.name)}
                    />
                </Field>

                <Field label="Email" error={errors.email} required>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                        invalid={Boolean(errors.email)}
                    />
                </Field>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="text-content text-footnote mt-2">
                            Your email address is unverified.{' '}
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="text-content-secondary hover:text-content focus-visible:outline-accent rounded-control underline focus-visible:outline-2"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <p className="text-success text-footnote mt-2 font-medium">
                                A new verification link has been sent to your
                                email address.
                            </p>
                        )}
                    </div>
                )}

                <Field
                    label="Timezone"
                    error={errors.timezone}
                    hint="Times are stored in UTC and shown in this zone, including in calendar feeds you subscribe to."
                    required
                >
                    <Select
                        id="timezone"
                        value={data.timezone}
                        onChange={(e) => setData('timezone', e.target.value)}
                        required
                    >
                        {timezoneOptions.map((group) => (
                            <optgroup key={group.region} label={group.region}>
                                {group.timezones.map((zone) => (
                                    <option key={zone.value} value={zone.value}>
                                        {zone.label}
                                    </option>
                                ))}
                            </optgroup>
                        ))}
                    </Select>
                </Field>

                {zoneMismatch && (
                    <div className="bg-accent-soft text-content rounded-card text-footnote p-3">
                        This device looks like it is in{' '}
                        <span className="font-medium">{browserZone}</span>.{' '}
                        <button
                            type="button"
                            onClick={useBrowserZone}
                            disabled={processing}
                            className="text-accent hover:text-accent-hover focus-visible:outline-accent rounded-control font-medium underline focus-visible:outline-2 disabled:opacity-50"
                        >
                            Use {browserZone} instead
                        </button>
                    </div>
                )}

                <Field label="Week starts on" error={errors.week_starts_on}>
                    <Select
                        id="week_starts_on"
                        value={data.week_starts_on}
                        onChange={(e) =>
                            setData('week_starts_on', Number(e.target.value))
                        }
                    >
                        {WEEK_DAYS.map((day, index) => (
                            <option key={day} value={index}>
                                {day}
                            </option>
                        ))}
                    </Select>
                </Field>

                <Field label="Time format" error={errors.time_format}>
                    <Select
                        id="time_format"
                        value={data.time_format}
                        onChange={(e) => setData('time_format', e.target.value)}
                    >
                        <option value="12h">12-hour (1:30 PM)</option>
                        <option value="24h">24-hour (13:30)</option>
                    </Select>
                </Field>

                <div className="flex items-center gap-4 pt-2">
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
