<!DOCTYPE html>
<html>
    <body style="font-family: sans-serif; color: #1f2937; padding: 24px;">
        <h2 style="margin-bottom: 8px;">Scheduling conflict reported</h2>
        <p>
            <strong>{{ $reporter->name }}</strong> ({{ $reporter->email }}) reported a conflict
            for <strong>{{ $event->title }}</strong>
            ({{ $event->starts_at->format('D, M j g:i A') }} – {{ $event->ends_at->format('g:i A') }}).
        </p>
        <p>It overlaps with:</p>
        <ul>
            @foreach ($conflictingTitles as $title)
                <li>{{ $title }}</li>
            @endforeach
        </ul>
        <p>
            <a href="{{ url('/groups/'.$event->calendar->group_id.'/calendars/'.$event->calendar_id.'/events') }}">
                Open the calendar
            </a>
        </p>
    </body>
</html>
