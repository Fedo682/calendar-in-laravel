import { useMemo } from 'react';

/**
 * Builds an RRULE string from a small set of presets.
 *
 * Deliberately narrow. The server's ValidRRule allowlist is the intersection
 * of what iCalendar, Google's recurrence[] array and our expander can all be
 * trusted to round-trip; offering a picker wider than that would only produce
 * rules the server then rejects. The Custom option is the escape hatch, and
 * it is still validated server-side.
 */

interface RecurrenceEditorProps {
    /** The current rule, or '' for a one-off. */
    value: string;
    onChange: (rule: string) => void;
    /** The event's start, which decides what "weekly on this day" means. */
    startsAt: string;
    /** IANA zone the rule is anchored to. Required whenever a rule is set. */
    timezone: string;
    onTimezoneChange: (timezone: string) => void;
    error?: string;
    timezoneError?: string;
}

const WEEKDAY_CODES = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'] as const;
const WEEKDAY_LABELS = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];

type Preset = 'never' | 'daily' | 'weekly' | 'monthly' | 'yearly' | 'custom';

const SELECT_CLASS =
    'w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none';

/** Which preset produced a rule, so reopening an event shows what it is. */
function presetFor(rule: string): Preset {
    if (rule === '') return 'never';

    const freq = /FREQ=([A-Z]+)/.exec(rule)?.[1];
    const isPlain = (expected: string) =>
        freq === expected && !/INTERVAL=|BYSETPOS=|COUNT=|UNTIL=/.test(rule);

    if (isPlain('DAILY')) return 'daily';
    if (isPlain('WEEKLY') && /^FREQ=WEEKLY(;BYDAY=[A-Z,]+)?$/.test(rule)) {
        return 'weekly';
    }
    if (isPlain('MONTHLY')) return 'monthly';
    if (isPlain('YEARLY')) return 'yearly';

    return 'custom';
}

function selectedDays(rule: string): string[] {
    const byDay = /BYDAY=([A-Z,]+)/.exec(rule)?.[1];

    return byDay ? byDay.split(',') : [];
}

/** A sentence describing the rule, for people who do not read RRULE. */
export function describeRule(rule: string, timezone: string): string {
    if (rule === '') return 'Does not repeat';

    const freq = /FREQ=([A-Z]+)/.exec(rule)?.[1] ?? '';
    const interval = Number(/INTERVAL=(\d+)/.exec(rule)?.[1] ?? '1');
    const days = selectedDays(rule);
    const count = /COUNT=(\d+)/.exec(rule)?.[1];
    const until = /UNTIL=([0-9TZ]+)/.exec(rule)?.[1];

    const every: Record<string, [string, string]> = {
        DAILY: ['day', 'days'],
        WEEKLY: ['week', 'weeks'],
        MONTHLY: ['month', 'months'],
        YEARLY: ['year', 'years'],
    };

    const unit = every[freq];
    if (!unit) return rule;

    let text =
        interval === 1 ? `Every ${unit[0]}` : `Every ${interval} ${unit[1]}`;

    if (freq === 'WEEKLY' && days.length > 0) {
        const names = days.map(
            (d) => WEEKDAY_LABELS[WEEKDAY_CODES.indexOf(d as 'MO')],
        );
        text += ` on ${names.join(', ')}`;
    }

    if (count) text += `, ${count} times`;
    if (until)
        text += `, until ${until.slice(0, 4)}-${until.slice(4, 6)}-${until.slice(6, 8)}`;
    if (timezone) text += ` (${timezone})`;

    return text;
}

export default function RecurrenceEditor({
    value,
    onChange,
    startsAt,
    timezone,
    onTimezoneChange,
    error,
    timezoneError,
}: RecurrenceEditorProps) {
    const preset = presetFor(value);
    const days = selectedDays(value);

    const startDayCode = useMemo(() => {
        const parsed = new Date(startsAt);

        return Number.isNaN(parsed.valueOf())
            ? 'MO'
            : WEEKDAY_CODES[parsed.getDay()];
    }, [startsAt]);

    const browserZone = useMemo(() => {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        } catch {
            return 'UTC';
        }
    }, []);

    const applyPreset = (next: Preset) => {
        if (next === 'never') {
            onChange('');

            return;
        }

        // A rule needs a zone, and the server rejects one without it rather
        // than defaulting - so fill it from the browser the moment a rule
        // first appears, instead of failing validation to say so.
        if (timezone === '') {
            onTimezoneChange(browserZone);
        }

        switch (next) {
            case 'daily':
                onChange('FREQ=DAILY');
                break;
            case 'weekly':
                onChange(`FREQ=WEEKLY;BYDAY=${startDayCode}`);
                break;
            case 'monthly':
                onChange('FREQ=MONTHLY');
                break;
            case 'yearly':
                onChange('FREQ=YEARLY');
                break;
            case 'custom':
                onChange(value === '' ? 'FREQ=WEEKLY;INTERVAL=2' : value);
                break;
        }
    };

    const toggleDay = (code: string) => {
        const next = days.includes(code)
            ? days.filter((d) => d !== code)
            : [...days, code];

        // Weekly with no day selected has no meaning; fall back to the day
        // the event itself starts on rather than emitting an empty BYDAY.
        const ordered = WEEKDAY_CODES.filter((c) => next.includes(c));
        const byDay = ordered.length > 0 ? ordered.join(',') : startDayCode;

        onChange(`FREQ=WEEKLY;BYDAY=${byDay}`);
    };

    return (
        <div>
            <label
                htmlFor="recurrence_preset"
                className="mb-1 block text-sm font-medium text-gray-700"
            >
                Repeats
            </label>

            <select
                id="recurrence_preset"
                value={preset}
                onChange={(e) => applyPreset(e.target.value as Preset)}
                className={SELECT_CLASS}
            >
                <option value="never">Does not repeat</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
                <option value="custom">Custom...</option>
            </select>

            {preset === 'weekly' && (
                <div className="mt-2 flex flex-wrap gap-1">
                    {WEEKDAY_CODES.map((code, index) => (
                        <button
                            key={code}
                            type="button"
                            onClick={() => toggleDay(code)}
                            aria-pressed={days.includes(code)}
                            className={`rounded-md border px-2 py-1 text-xs font-medium transition ${
                                days.includes(code)
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                    : 'border-gray-300 text-gray-600 hover:bg-gray-50'
                            }`}
                        >
                            {WEEKDAY_LABELS[index].slice(0, 3)}
                        </button>
                    ))}
                </div>
            )}

            {preset === 'custom' && (
                <input
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value.toUpperCase())}
                    placeholder="FREQ=WEEKLY;INTERVAL=2;BYDAY=MO"
                    className={`${SELECT_CLASS} mt-2 font-mono text-xs`}
                />
            )}

            {value !== '' && (
                <>
                    <p className="mt-1 text-sm text-gray-600">
                        {describeRule(value, timezone)}
                    </p>

                    <label
                        htmlFor="recurrence_timezone"
                        className="mt-2 mb-1 block text-sm font-medium text-gray-700"
                    >
                        Repeats in timezone
                    </label>
                    <input
                        id="recurrence_timezone"
                        type="text"
                        value={timezone}
                        onChange={(e) => onTimezoneChange(e.target.value)}
                        className={SELECT_CLASS}
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        A 9am series stays at 9am here even when the clocks
                        change.
                    </p>
                    {timezoneError && (
                        <p className="mt-1 text-sm text-red-500">
                            {timezoneError}
                        </p>
                    )}
                </>
            )}

            {error && <p className="mt-1 text-sm text-red-500">{error}</p>}
        </div>
    );
}
