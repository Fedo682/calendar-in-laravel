import type { RecurrenceScope } from '@/components/Calendar/RecurrenceScopeDialog';
import type { Occurrence, Visibility } from '@/types/calendar';
import type { InertiaFormProps } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';

/**
 * The fields every event create/edit dialog collects, across all three pages
 * that own one (a group calendar, a personal calendar, the dashboard picker).
 */
export interface EventFormData {
    title: string;
    description: string;
    location: string;
    starts_at: string;
    ends_at: string;
    all_day: boolean;
    visibility: Visibility;
    recurrence_rule: string;
    recurrence_timezone: string;
    /** Which occurrences a save or delete applies to. */
    scope: RecurrenceScope;
    /** Which instance was opened, for the two scoped operations. */
    occurrence_start: string;
}

const BLANK: EventFormData = {
    title: '',
    description: '',
    location: '',
    starts_at: '',
    ends_at: '',
    all_day: false,
    visibility: 'public',
    recurrence_rule: '',
    recurrence_timezone: '',
    scope: 'all',
    occurrence_start: '',
};

/** Format a Date as the value expected by <input type="datetime-local">. */
export function toLocalInputValue(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/**
 * The form behind every event dialog: what a fresh "new event" looks like,
 * and what reopening an existing occurrence fills in.
 *
 * Deliberately does not own dialog open/close state or the submit URL - those
 * differ per page (a group calendar posts to a group-nested route, a personal
 * calendar to a flat one, and which occurrence is being edited matters for
 * building that URL and for the this/following/all scope prompt). This hook
 * is only the part that was identical across all three: how a click becomes
 * form data.
 */
export function useEventForm(defaults?: Partial<EventFormData>): {
    form: InertiaFormProps<EventFormData>;
    openCreate: (date: Date, visibility?: Visibility) => void;
    openEdit: (occurrence: Occurrence) => void;
    reset: () => void;
} {
    const form = useForm<EventFormData>({ ...BLANK, ...defaults });

    const openCreate = (date: Date, visibility: Visibility = 'public') => {
        const start = new Date(date);

        // From the month grid, `date` has no time component (midnight) -
        // default that to a sensible working hour. From a DayView slot
        // click, `date` already carries the clicked hour - keep it as is.
        if (start.getHours() === 0 && start.getMinutes() === 0) {
            start.setHours(9, 0, 0, 0);
        }

        const end = new Date(start);
        end.setHours(start.getHours() + 1);

        form.setData({
            ...BLANK,
            starts_at: toLocalInputValue(start),
            ends_at: toLocalInputValue(end),
            visibility,
        });
    };

    const openEdit = (occurrence: Occurrence) => {
        form.setData({
            title: occurrence.title,
            description: occurrence.description ?? '',
            location: occurrence.location ?? '',
            starts_at: toLocalInputValue(new Date(occurrence.starts_at)),
            ends_at: toLocalInputValue(new Date(occurrence.ends_at)),
            all_day: occurrence.all_day,
            visibility: occurrence.visibility,
            recurrence_rule: occurrence.recurrence_rule ?? '',
            recurrence_timezone: occurrence.recurrence_timezone ?? '',
            scope: 'all',
            // The instance the user actually clicked. RECURRENCE-ID if this
            // occurrence already has an override, otherwise its own start.
            occurrence_start: occurrence.recurrence_id ?? occurrence.starts_at,
        });
    };

    const reset = () => {
        form.reset();
        form.clearErrors();
    };

    return { form, openCreate, openEdit, reset };
}
