<!DOCTYPE html>
<html>
    <body style="font-family: sans-serif; color: #1f2937; padding: 24px;">
        <h2 style="margin-bottom: 8px;">You've been added to {{ $group->name }}</h2>
        <p>
            You now have the <strong>{{ $role }}</strong> role in
            <strong>{{ $group->name }}</strong>.
        </p>
        @if ($group->description)
            <p style="color: #6b7280;">{{ $group->description }}</p>
        @endif
        <p>
            <a href="{{ url('/groups/'.$group->id) }}">View the group</a>
        </p>
    </body>
</html>
