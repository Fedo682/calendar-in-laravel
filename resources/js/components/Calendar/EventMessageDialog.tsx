import { Button, Field, Modal, Textarea } from '@/components/ui';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface EventMessageDialogProps {
    open: boolean;
    onClose: () => void;
    onSent: () => void;
    groupId: number;
    calendarId: number;
    eventId: number;
    occurrenceStart: string;
}

/**
 * Lets a member tell the group's admins about a problem event - a
 * scheduling clash they'd rather describe in their own words, or anything
 * else. Shares its dedup with the "Report conflict" action server-side: once
 * either has been used for this event and occurrence, the other is blocked
 * too, so this dialog's only job is collecting the free-text body.
 */
export default function EventMessageDialog({
    open,
    onClose,
    onSent,
    groupId,
    calendarId,
    eventId,
    occurrenceStart,
}: EventMessageDialogProps) {
    const [body, setBody] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>(undefined);

    const submit = () => {
        if (body.trim() === '') return;

        setProcessing(true);

        router.post(
            `/groups/${groupId}/calendars/${calendarId}/events/${eventId}/message`,
            { body, occurrence_start: occurrenceStart },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setBody('');
                    setError(undefined);
                    onSent();
                    onClose();
                },
                onError: (errors) => setError(errors.body),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Modal open={open} onClose={onClose} title="Message the group admins">
            <Field label="Message" error={error} required>
                <Textarea
                    rows={4}
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                    invalid={Boolean(error)}
                    placeholder="What's the problem with this event?"
                />
            </Field>

            <div className="mt-6 flex justify-end gap-3">
                <Button type="button" variant="secondary" onClick={onClose}>
                    Cancel
                </Button>
                <Button
                    type="button"
                    onClick={submit}
                    loading={processing}
                    disabled={body.trim() === ''}
                >
                    Send
                </Button>
            </div>
        </Modal>
    );
}
