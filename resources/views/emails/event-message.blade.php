<!DOCTYPE html>
<html>
    <body style="font-family: sans-serif; color: #1f2937; padding: 24px;">
        <h2 style="margin-bottom: 8px;">Message about an event</h2>
        <p>
            <strong>{{ $sender->name }}</strong> ({{ $sender->email }}) sent a message
            about <strong>{{ $event->title }}</strong>
            ({{ $event->starts_at->format('D, M j g:i A') }} – {{ $event->ends_at->format('g:i A') }}).
        </p>
        <p style="white-space: pre-line;">{{ $body }}</p>
        <p>
            <a href="{{ url('/groups/'.$event->calendar->group_id.'/calendars/'.$event->calendar_id.'/events') }}">
                Open the calendar
            </a>
        </p>
    </body>
</html>
