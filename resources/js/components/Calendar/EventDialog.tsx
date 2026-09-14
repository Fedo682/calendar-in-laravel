import RecurrenceEditor from '@/components/Calendar/RecurrenceEditor';
import type { EventFormData } from '@/components/Calendar/useEventForm';
import {
    Button,
    Checkbox,
    Field,
    Input,
    Modal,
    Select,
    Textarea,
} from '@/components/ui';
import type { Visibility, WritableCalendar } from '@/types/calendar';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';

export interface EventDialogProps {
    open: boolean;
    mode: 'create' | 'edit';
    form: InertiaFormProps<EventFormData>;
    /** Absent on the personal calendar, which has only one target. */
    calendars?: WritableCalendar[];
    targetCalendarId?: number | null;
    onTargetChange?: (id: number) => void;
    onSubmit: (e: FormEvent) => void;
    onDelete?: () => void;
    onClose: () => void;
}

/**
 * The event create/edit dialog shared by every page that has one - a group
 * calendar, the personal calendar, and the dashboard.
 *
 * Deliberately does not own the submit URL, the scope prompt, or which
 * occurrence is being edited - those differ per page. This is only the form
 * itself: the fields, the recurrence editor, and (when `calendars` is passed)
 * the picker for which calendar a new event belongs to.
 */
export default function EventDialog({
    open,
    mode,
    form,
    calendars,
    targetCalendarId,
    onTargetChange,
    onSubmit,
    onDelete,
    onClose,
}: EventDialogProps) {
    return (
        <Modal
            open={open}
            onClose={onClose}
            title={mode === 'edit' ? 'Edit event' : 'New event'}
            width="lg"
        >
            <form
                id="event-dialog-form"
                onSubmit={onSubmit}
                className="space-y-4"
            >
                {calendars && calendars.length > 0 && (
                    <Field
                        label="Calendar"
                        hint={
                            calendars.find((c) => c.id === targetCalendarId)
                                ?.type === 'personal'
                                ? 'Events here are private by default - others see only that you are busy.'
                                : undefined
                        }
                    >
                        <Select
                            value={targetCalendarId ?? ''}
                            onChange={(e) =>
                                onTargetChange?.(Number(e.target.value))
                            }
                        >
                            {calendars.map((calendar) => (
                                <option key={calendar.id} value={calendar.id}>
                                    {calendar.group_name
                                        ? `${calendar.group_name} - ${calendar.name}`
                                        : calendar.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                )}

                <Field label="Title" error={form.errors.title} required>
                    <Input
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        required
                        invalid={Boolean(form.errors.title)}
                    />
                </Field>

                <Field label="Description" error={form.errors.description}>
                    <Textarea
                        rows={3}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        invalid={Boolean(form.errors.description)}
                    />
                </Field>

                <Field label="Location" error={form.errors.location}>
                    <Input
                        value={form.data.location}
                        onChange={(e) =>
                            form.setData('location', e.target.value)
                        }
                        invalid={Boolean(form.errors.location)}
                    />
                </Field>

                <Checkbox
                    checked={form.data.all_day}
                    onChange={(e) => form.setData('all_day', e.target.checked)}
                    label="All day"
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field
                        label="Starts at"
                        error={form.errors.starts_at}
                        required
                    >
                        <Input
                            type="datetime-local"
                            value={form.data.starts_at}
                            onChange={(e) =>
                                form.setData('starts_at', e.target.value)
                            }
                            required
                            invalid={Boolean(form.errors.starts_at)}
                        />
                    </Field>
                    <Field label="Ends at" error={form.errors.ends_at} required>
                        <Input
                            type="datetime-local"
                            value={form.data.ends_at}
                            onChange={(e) =>
                                form.setData('ends_at', e.target.value)
                            }
                            required
                            invalid={Boolean(form.errors.ends_at)}
                        />
                    </Field>
                </div>

                <RecurrenceEditor
                    value={form.data.recurrence_rule}
                    onChange={(rule) => form.setData('recurrence_rule', rule)}
                    startsAt={form.data.starts_at}
                    timezone={form.data.recurrence_timezone}
                    onTimezoneChange={(tz) =>
                        form.setData('recurrence_timezone', tz)
                    }
                    error={form.errors.recurrence_rule}
                    timezoneError={form.errors.recurrence_timezone}
                />

                <Field label="Visibility" error={form.errors.visibility}>
                    <Select
                        value={form.data.visibility}
                        onChange={(e) =>
                            form.setData(
                                'visibility',
                                e.target.value as Visibility,
                            )
                        }
                    >
                        <option value="public">
                            Public - everyone on this calendar sees the details
                        </option>
                        <option value="private">
                            Private - others see only that you are busy
                        </option>
                        <option value="busy">
                            Busy - details hidden from everyone
                        </option>
                    </Select>
                </Field>
            </form>

            <div className="mt-6 flex items-center justify-between">
                <div>
                    {mode === 'edit' && onDelete && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={onDelete}
                            disabled={form.processing}
                        >
                            Delete
                        </Button>
                    )}
                </div>
                <div className="flex gap-3">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        form="event-dialog-form"
                        loading={form.processing}
                    >
                        {mode === 'edit' ? 'Save changes' : 'Create event'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
