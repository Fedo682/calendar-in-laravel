import InputError from '@/components/InputError';
import InputLabel from '@/components/InputLabel';
import PrimaryButton from '@/components/PrimaryButton';
import TextInput from '@/components/TextInput';
import { cn } from '@/lib/utils';
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
    className?: string;
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

/** Mirrors the classes `TextInput` applies, so selects line up with inputs. */
const SELECT_CLASS =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

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
    className = '',
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
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Profile Information
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Update your account's profile information and email address.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value="Name" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800">
                            Your email address is unverified.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600">
                                A new verification link has been sent to your
                                email address.
                            </div>
                        )}
                    </div>
                )}

                <div>
                    <InputLabel htmlFor="timezone" value="Timezone" />

                    <select
                        id="timezone"
                        className={SELECT_CLASS}
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
                    </select>

                    <p className="mt-1 text-sm text-gray-600">
                        Times are stored in UTC and shown in this zone,
                        including in calendar feeds you subscribe to.
                    </p>

                    <InputError className="mt-2" message={errors.timezone} />

                    {zoneMismatch && (
                        <div className="mt-2 rounded-md bg-indigo-50 p-3 text-sm text-indigo-900">
                            This device looks like it is in{' '}
                            <span className="font-medium">{browserZone}</span>.
                            <button
                                type="button"
                                onClick={useBrowserZone}
                                disabled={processing}
                                className={cn(
                                    'ml-2 font-medium underline hover:text-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none',
                                    processing && 'opacity-50',
                                )}
                            >
                                Use {browserZone} instead
                            </button>
                        </div>
                    )}
                </div>

                <div>
                    <InputLabel
                        htmlFor="week_starts_on"
                        value="Week starts on"
                    />

                    <select
                        id="week_starts_on"
                        className={SELECT_CLASS}
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
                    </select>

                    <InputError
                        className="mt-2"
                        message={errors.week_starts_on}
                    />
                </div>

                <div>
                    <InputLabel htmlFor="time_format" value="Time format" />

                    <select
                        id="time_format"
                        className={SELECT_CLASS}
                        value={data.time_format}
                        onChange={(e) => setData('time_format', e.target.value)}
                    >
                        <option value="12h">12-hour (1:30 PM)</option>
                        <option value="24h">24-hour (13:30)</option>
                    </select>

                    <InputError className="mt-2" message={errors.time_format} />
                </div>

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">Saved.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
