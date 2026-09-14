# Team Busy-Visibility, Conflict Scoping, and Member-to-Admin Messaging

## Context

Two related problems were reported against the dashboard's month grid and
conflict detection:

1. **Group admins can't see their team's busy status.** `User::accessibleCalendarIds()`
   only returns a non-Super-Admin's own personal calendar plus the calendars
   of groups they belong to. It never includes a teammate's personal
   calendar, so an admin has no way to see "Jane is busy 2-3pm" anywhere.
2. **The Super Admin's dashboard is unusable at platform scale.** Because
   `accessibleCalendarIds()` returns *every* calendar on the platform for a
   Super Admin, their dashboard month grid mixes in every other user's
   personal calendar, and `ConflictDetector::detect()` is handed all of it at
   once. Two unrelated people's independent appointments overlapping in time
   get flagged as a "conflict" for the Super Admin, who is not actually
   double-booked - they just happen to be able to see both calendars.

Additionally, a group admin currently learns about a scheduling clash only
through the existing "Report conflict" action, which silently emails the
group's admins with no persisted record and no way for a member to instead
just say "this event is a problem" without it being a timing clash.

This spec covers four changes, in the order they build on each other:

1. **Two new calendar-id sets on `User`** that separate "what should this
   person's own schedule (grid + conflicts) contain" from "what can this
   person browse" - the existing `accessibleCalendarIds()` is untouched and
   keeps its current meaning.
2. **Conflict detection scoped to the viewer's real affiliations**, closing
   the Super Admin false-positive case while leaving every existing
   non-Super-Admin behavior identical.
3. **A "Team busy" dashboard panel**, grouped by group and collapsed by
   default, showing "Busy - Jane Smith" blocks for teammates' personal
   calendars - for a group admin, their own administered group(s); for a
   Super Admin, every group on the platform. This is the feature that
   answers problem 1, deliberately kept out of the month grid and out of
   conflict detection so it never reintroduces problem 2.
4. **Member-to-admin messaging**, a new `event_messages` table backing both
   the existing "Report conflict" action and a new "Message admin" action,
   sharing one dedup rule so a member can't spam admins about the same
   event instance twice regardless of which action they used.

## Section 1: Calendar-id sets

Three methods will exist on `User` afterward, each with a distinct, narrow
purpose. Only one is new logic; the other is a straight extraction.

```php
/**
 * Calendars this viewer personally belongs to: their own personal
 * calendar, plus every calendar of a group they are an actual member of.
 * Deliberately has no Super Admin branch - "my own affiliations" does not
 * get bigger just because a platform role grants broader browsing rights
 * elsewhere. This is the set conflict detection uses.
 */
public function personalCalendarIds(): Collection
{
    return Calendar::query()
        ->where('owner_id', $this->id)
        ->orWhereIn('group_id', $this->memberGroups()->select('groups.id'))
        ->pluck('id');
}

/**
 * Calendars that make up this viewer's own schedule for the dashboard's
 * month grid and agenda: personalCalendarIds(), plus - for a Super Admin
 * only - every GROUP calendar on the platform. This is what lets a Super
 * Admin's dashboard still show every group's events (the existing,
 * load-bearing behavior under test), while never pulling in another
 * individual user's personal calendar the way accessibleCalendarIds()
 * does. That visibility instead comes only through teamBusyByGroup().
 */
public function scheduleCalendarIds(): Collection
{
    $own = $this->personalCalendarIds();

    if (! $this->isSuperAdmin()) {
        return $own;
    }

    return $own->merge(Calendar::query()->where('type', Calendar::TYPE_GROUP)->pluck('id'))
        ->unique()
        ->values();
}
```

`accessibleCalendarIds()` is **unchanged** - it still returns literally every
calendar for a Super Admin. It stays the browse-everything set backing
`CalendarController::all()` (the calendars list page), `WritableCalendars`
(the "New event" calendar picker), and `OccurrenceQuery::mastersFor()` (the
not-yet-built ICS feed). None of those are in scope here and none of them
have the false-conflict or clutter problem - they either show one row per
calendar rather than mixing events together, or they gate writes through
`EventPolicy`, which already independently refuses a Super Admin write
access to another user's personal calendar.

`DashboardController::index()` swaps its two `OccurrenceQuery::forUser()`
calls (which read `accessibleCalendarIds()`) for `OccurrenceQuery::forCalendars()`
calls against `scheduleCalendarIds()` for display, and a third,
narrower `forCalendars()` call against `personalCalendarIds()` feeding only
`ConflictDetector::detect()`. `OccurrenceQuery` itself needs no new method -
`forCalendars()` already takes an arbitrary id collection.

## Section 2: Conflict scoping

```php
public function index(Request $request): Response
{
    $user = $request->user();
    $windowDays = (int) config('calendar.conflicts.window_days');
    $month = $this->month($request);
    [$gridFrom, $gridTo] = $this->gridRange($month);

    $scheduleIds = $user->scheduleCalendarIds();

    $monthOccurrences = $this->occurrences->forCalendars($scheduleIds, $gridFrom, $gridTo, $user);
    $upcoming = $this->occurrences->forCalendars($scheduleIds, now(), now()->addDays($windowDays), $user);

    $conflictCandidates = $this->occurrences->forCalendars(
        $user->personalCalendarIds(),
        now(),
        now()->addDays($windowDays),
        $user,
    );
    $conflicts = $this->conflicts->detect($conflictCandidates);

    $teamBusy = $this->teamBusyPayload($user, $windowDays); // Section 3

    return Inertia::render('Dashboard', [
        'month' => $month->toDateString(),
        'occurrences' => $monthOccurrences->map(fn ($o) => $o->toArray())->values(),
        'upcoming_events' => $upcoming->map(function ($occurrence) use ($conflicts) {
            $clashes = $conflicts[$occurrence->key()] ?? [];

            return [...$occurrence->toArray(), 'conflicts_with' => array_map(fn ($o) => $o->event->title, $clashes)];
        })->values(),
        'window_days' => $windowDays,
        'writable_calendars' => $this->writable->options($user),
        'team_busy' => $teamBusy,
    ]);
}
```

`Occurrence::key()` (`"{event_id}"` or `"{event_id}:{timestamp}"`) is stable
regardless of which query fetched the row, so looking up
`$conflicts[$occurrence->key()]` against the wider `$upcoming` list correctly
resolves to "was this exact occurrence also present in the narrower,
personally-affiliated set, and did it overlap something else in that same
narrower set" - which is exactly "am I, the viewer, actually double-booked."

**Behavior change, by role:**

- **Member / Admin (unchanged):** `personalCalendarIds()` and
  `scheduleCalendarIds()` compute identically for anyone who isn't a Super
  Admin - both are exactly today's `accessibleCalendarIds()` else-branch.
  Zero behavioral difference; the existing conflict-report flow and
  dashboard tests for these roles are unaffected.
- **Super Admin (fixed):** the month grid still shows every group's events
  (via `scheduleCalendarIds()`'s Super Admin branch), so
  `tests/Feature/DashboardTest.php`'s "super admin sees events across every
  group" keeps passing unmodified. But conflict detection now runs only
  over their own personal calendar (`personalCalendarIds()` has no Super
  Admin branch, and a Super Admin is not a `group_user` member of any group
  by construction - `Gate::before` grants their access, not a pivot row).
  Two unrelated groups' overlapping events no longer flag a phantom
  conflict for them.

## Section 3: Team busy panel

### Backend

```php
/**
 * Groups this viewer administers: the ones where they hold the 'admin'
 * role, or every group on the platform for a Super Admin.
 *
 * @return Collection<int, Group>
 */
public function administeredGroups(): Collection
{
    if ($this->isSuperAdmin()) {
        return Group::query()->get();
    }

    $adminRoleId = Role::where('name', RoleName::Admin->value)->value('id');

    return Group::query()
        ->whereIn('id', GroupUser::where('user_id', $this->id)->where('role_id', $adminRoleId)->pluck('group_id'))
        ->get();
}

/**
 * For each group this viewer administers, the personal calendar ids of
 * every OTHER member of that group.
 *
 * Three queries regardless of group or member count: the groups
 * themselves, every membership row across all of them, and every personal
 * calendar owned by any of those members. Grouping happens in PHP over
 * already-fetched collections rather than one query per group.
 *
 * @return Collection<int, array{group: Group, calendar_ids: Collection<int, int>}>
 */
public function teamBusyByGroup(): Collection
{
    $groups = $this->administeredGroups();

    if ($groups->isEmpty()) {
        return collect();
    }

    $memberships = GroupUser::whereIn('group_id', $groups->pluck('id'))
        ->where('user_id', '!=', $this->id)
        ->get(['group_id', 'user_id']);

    $calendarIdByOwner = Calendar::query()
        ->where('type', Calendar::TYPE_PERSONAL)
        ->whereIn('owner_id', $memberships->pluck('user_id')->unique())
        ->pluck('id', 'owner_id');

    return $groups->map(fn (Group $group) => [
        'group' => $group,
        'calendar_ids' => $memberships
            ->where('group_id', $group->id)
            ->pluck('user_id')
            ->map(fn ($userId) => $calendarIdByOwner->get($userId))
            ->filter()
            ->values(),
    ])->values();
}
```

`administeredGroups()` and `teamBusyByGroup()` are new methods on `User`,
next to the existing `accessibleCalendarIds()`/`personalCalendarIds()`/
`scheduleCalendarIds()`.

`DashboardController` gets a private helper building the Inertia payload:

```php
/**
 * @return array<int, array{group_id: int, group_name: string, busy_count: int, occurrences: array<int, array<string, mixed>>}>
 */
private function teamBusyPayload(User $user, int $windowDays): array
{
    return $user->teamBusyByGroup()->map(function (array $entry) use ($user, $windowDays) {
        $occurrences = $entry['calendar_ids']->isEmpty()
            ? collect()
            : $this->occurrences->forCalendars(
                $entry['calendar_ids'],
                now(),
                now()->addDays($windowDays),
                $user,
            );

        return [
            'group_id' => $entry['group']->id,
            'group_name' => $entry['group']->name,
            'busy_count' => $occurrences->count(),
            'occurrences' => $occurrences->map(fn ($o) => $o->toArray())->values(),
        ];
    })->values()->all();
}
```

Every calendar in `$entry['calendar_ids']` is someone else's personal
calendar, which defaults to `private` visibility. `EventRedactor::canSeeDetails()`
requires `isOwnEvent()` (creator or calendar owner) for anything not
`public`, and the viewing admin is neither, so these occurrences arrive
already redacted to the literal title `"Busy"` with null description and
location - exactly as `EventRedactor` already treats a Super Admin peeking at
someone else's calendar today. The one gap is identity: today there is no
way to tell *whose* "Busy" block it is. That's `RedactedEvent::$ownerName`,
below.

### `RedactedEvent::$ownerName`

New nullable constructor parameter on `RedactedEvent`, appended after the
existing recurrence fields (all of which already have defaults, so this is
non-breaking for any other caller):

```php
public ?string $ownerName = null,
```

Populated in `EventRedactor::redact()`:

```php
ownerName: ($calendar->isPersonal() && ! $visible) ? $calendar->owner?->name : null,
```

Only set when both are true: the calendar is a personal calendar (never a
group calendar - a redacted group event stays anonymous, since "who created
this" was never something `EventRedactor` revealed and this change must not
start doing so by accident), and the event is actually redacted (`! $visible`)
- a *public* event on someone's personal calendar already shows its real
title, so no separate owner label is needed or added.

`EventRedactor::redactMany()` needs one more eager-loaded relation so this
doesn't N+1:

```php
$events->loadMissing('calendar.group', 'calendar.owner');
```

`Occurrence::toArray()` gains one line:

```php
'owner_name' => $this->event->ownerName,
```

### Frontend

`resources/js/types/calendar.ts` - add to the `Occurrence` interface:

```ts
/** Set only for a redacted event on someone's personal calendar - "Busy - Jane Smith". */
owner_name: string | null;
```

`resources/js/pages/Dashboard.tsx`:

```ts
interface TeamBusyGroup {
    group_id: number;
    group_name: string;
    busy_count: number;
    occurrences: Occurrence[];
}

interface DashboardProps {
    // ...existing fields
    team_busy: TeamBusyGroup[];
}
```

A new `Card material="thin" padded={false}` block in the aside, below the
existing "Next N days" card, rendered only when `team_busy.length > 0`.
Each group is its own row: a button toggling that group's id in a
`Set<number>` of expanded ids (`useState<Set<number>>(new Set())`, default
empty - collapsed by default, per the design brief), showing
`"{group_name} - {busy_count} busy"` with a chevron, and when expanded, a
plain list of that group's occurrences formatted the same way the agenda
list already formats time ranges (reusing `formatTimeRange`), each line
reading `"Busy - {owner_name}"` (or `"Busy"` if `owner_name` is null - it
always will be non-null in this panel by construction, but the fallback
costs nothing and matches how the rest of the page already treats
`group_name` defensively). No click-through, no edit, no report and no
message action on these rows - they are a read-only busy indicator, never a
real event a viewer is entitled to open.

## Section 4: Member-to-admin messaging

### Migration

```php
Schema::create('event_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
    $table->string('type', 16);
    // Always the resolved occurrence's actual start instant, never null -
    // even for a one-off event, this stores that event's own starts_at.
    // MySQL/MariaDB unique indexes treat NULL as distinct from every other
    // NULL, which would silently defeat the dedup constraint below for
    // every non-recurring event if this were nullable.
    $table->dateTime('occurrence_start');
    $table->text('body');
    // Only populated for type='conflict'; null for a general message.
    $table->json('conflicting_titles')->nullable();
    $table->timestamps();

    // One message per sender per occurrence, regardless of type - a
    // member who already reported a conflict on this instance is blocked
    // from also sending a general message about it, and vice versa. This
    // is the literal dedup mechanism, not merely an index.
    $table->unique(['event_id', 'sender_id', 'occurrence_start'], 'event_messages_dedup');
});
```

### Model and enum

`app/Enums/EventMessageType.php`:

```php
enum EventMessageType: string
{
    case Conflict = 'conflict';
    case General = 'general';
}
```

`app/Models/EventMessage.php`:

```php
class EventMessage extends Model
{
    protected $fillable = ['event_id', 'sender_id', 'type', 'occurrence_start', 'body', 'conflicting_titles'];

    protected function casts(): array
    {
        return [
            'type' => EventMessageType::class,
            'occurrence_start' => 'datetime',
            'conflicting_titles' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
```

### Shared admin lookup

`EventController::groupAdmins()` is extracted verbatim into
`app/Support/Calendar/GroupAdminLookup.php`:

```php
final class GroupAdminLookup
{
    /**
     * @return Collection<int, User>
     */
    public function forGroup(Group $group): Collection
    {
        $adminRoleId = Role::where('name', RoleName::Admin->value)->value('id');

        return GroupUser::where('group_id', $group->id)
            ->where('role_id', $adminRoleId)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->values();
    }
}
```

`EventController` gains a fourth constructor-promoted dependency,
`private readonly GroupAdminLookup $groupAdminLookup`, and its private
`groupAdmins()` method is deleted; both call sites below use
`$this->groupAdminLookup->forGroup($group)`.

### `reportConflict()` changes

Same route, same signature, same existing behavior for every currently
passing test in `tests/Feature/ConflictReportTest.php` - the only change is
a dedup check inserted after resolving the subject occurrence, and
persisting an `EventMessage` row alongside the existing email:

```php
public function reportConflict(Request $request, Group $group, Calendar $calendar, Event $event): RedirectResponse
{
    $this->authorize('view', $event);

    $user = $request->user();
    $subject = $this->occurrences->forEvent($event, $user, $this->occurrenceStartFrom($request));

    if ($subject === null) {
        abort(404);
    }

    if ($this->alreadyMessaged($event, $user, $subject->startsAt)) {
        return back()->with('error', 'You already sent the admins a message about this event.');
    }

    $candidates = $this->occurrences->forUser($user, $subject->startsAt, $subject->endsAt);
    $conflicting = $this->conflicts->conflictsFor($subject, $candidates);

    if ($conflicting->isEmpty()) {
        return back()->with('success', 'No conflict found for this event anymore.');
    }

    $titles = $conflicting->map(fn (Occurrence $o) => $o->event->title)->values();

    EventMessage::create([
        'event_id' => $event->id,
        'sender_id' => $user->id,
        'type' => EventMessageType::Conflict,
        'occurrence_start' => $subject->startsAt,
        'body' => 'Reported a scheduling conflict.',
        'conflicting_titles' => $titles->all(),
    ]);

    foreach ($this->groupAdminLookup->forGroup($group) as $admin) {
        Mail::to($admin->email)->send(new ConflictReportedMail($user, $event, $titles));
    }

    return back()->with('success', 'Conflict reported to the group admin(s).');
}

private function alreadyMessaged(Event $event, User $user, CarbonImmutable $occurrenceStart): bool
{
    return EventMessage::where('event_id', $event->id)
        ->where('sender_id', $user->id)
        ->where('occurrence_start', $occurrenceStart)
        ->exists();
}
```

The dedup check runs before the conflict is recomputed, so "reporting a
non-existent conflict does not send mail" (an existing passing test) is
unaffected: the table is empty on a first call, `alreadyMessaged` is
`false`, and the method proceeds to its existing empty-conflict early
return - which still bails out *before* the `EventMessage::create()` call,
so a report on a since-resolved conflict still creates no row and sends no
mail, exactly as today.

The candidate gathering (`$this->occurrences->forUser(...)`) deliberately
keeps using `accessibleCalendarIds()` rather than switching to
`personalCalendarIds()`. This is a single, explicit, member-initiated check
over a narrow window (exactly the subject occurrence's own span), not the
dashboard's automatic sweep - the reported "tiring, lots of conflicts"
complaint was specifically about that automatic sweep, not about a person
deliberately clicking "Report" on one event. Narrowing this too would mean
a Super Admin manually reporting a conflict could miss a real one that only
shows up on a calendar they can see but don't personally belong to, which
this spec has no reason to break.

### `sendMessage()` - new sibling endpoint

```php
public function sendMessage(Request $request, Group $group, Calendar $calendar, Event $event): RedirectResponse
{
    $this->authorize('view', $event);

    $request->validate([
        'body' => ['required', 'string', 'max:2000'],
    ]);

    $user = $request->user();
    $subject = $this->occurrences->forEvent($event, $user, $this->occurrenceStartFrom($request));

    if ($subject === null) {
        abort(404);
    }

    if ($this->alreadyMessaged($event, $user, $subject->startsAt)) {
        return back()->with('error', 'You already sent the admins a message about this event.');
    }

    EventMessage::create([
        'event_id' => $event->id,
        'sender_id' => $user->id,
        'type' => EventMessageType::General,
        'occurrence_start' => $subject->startsAt,
        'body' => $request->string('body')->toString(),
    ]);

    foreach ($this->groupAdminLookup->forGroup($group) as $admin) {
        Mail::to($admin->email)->send(new EventMessageMail($user, $event, $request->string('body')->toString()));
    }

    return back()->with('success', 'Message sent to the group admin(s).');
}
```

Authorization is `view` on the event - the same ability that already gates
`reportConflict` and `show`/`index` - so anyone who can see the event (any
member of its group, not only someone with edit rights) can send a message
about it. Both actions are reachable only through the existing
`groups/{group}/calendars/{calendar}/events/{event}/...` route group, so
this is structurally impossible to invoke against a personal-calendar event
(those use `PersonalEventController` and a disjoint set of flat routes with
no equivalent action) - "message the team admin" only ever applies to a
real team event, matching the request as asked.

### Mail

`app/Mail/EventMessageMail.php`, modeled directly on the existing
`ConflictReportedMail`:

```php
class EventMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $sender, public Event $event, public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Message about: {$this->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.event-message');
    }
}
```

`resources/views/emails/event-message.blade.php`, modeled on
`emails/conflict-reported.blade.php`:

```blade
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
```

### Route

```php
Route::post('events/{event}/message', [EventController::class, 'sendMessage'])
    ->name('events.message');
```

Added next to the existing `events.report-conflict` route, inside the same
`groups/{group}/calendars/{calendar}/` scoped-bindings group.

### Frontend

`resources/js/components/Calendar/EventMessageDialog.tsx` - new component,
built on the `Modal` primitive from `@/components/ui` (the same primitive
`EventDialog` already uses), with a single `Textarea` bound to local state
and a submit button that posts via Inertia's `router.post` to
`` `/groups/${groupId}/calendars/${calendarId}/events/${eventId}/message` ``
with `{ body, occurrence_start }`, closing on success.

`resources/js/pages/Dashboard.tsx` - the existing agenda action area (the
`event.can_edit && !event.is_redacted ? <Edit button> : (...)` block) is
extended. Today, the `else` branch only ever renders something when
`conflicts_with.length > 0`. It becomes two independent, stackable actions:

- **Message admin** - shown whenever `event.group_id !== null && !event.can_edit`,
  regardless of conflict status. Opens `EventMessageDialog` for that event.
  Once sent, tracked in the same `reportedIds`-style local state (renamed
  `messagedIds` conceptually, or reusing one combined "already messaged this
  event" set since the dedup is shared server-side) so the button becomes
  inert text after a successful send, mirroring the existing `Reported` /
  `Report` pattern.
- **Report** - unchanged, still only shown when `conflicts_with.length > 0`
  and `event.group_id !== null`.

Both actions can appear together on the same row when an event both
conflicts with something and the member wants to also leave a note - they
are independent buttons, not a toggle, matching how the backend treats them
as two entry points into one dedup rule rather than one feature with two
labels.

This is added only to `Dashboard.tsx`'s agenda list, not to
`Events/Index.tsx` (the per-calendar month grid page). "Report" already
exists only on the dashboard - there is no established pattern for either
action on the calendar page, and clicking a non-editable event there
today is already a silent no-op with no comparable surface to extend.
Keeping both actions in the one place they already have a home avoids
building a second, divergent entry point for the same underlying route.

Team-busy panel rows (Section 3) never render either action - they are not
real events the viewer has any standing to message about.

## Testing strategy

- **`tests/Feature/DashboardTest.php`** (extend):
  - Keep "super admin sees events across every group" passing unmodified -
    it now exercises `scheduleCalendarIds()`'s Super Admin branch instead of
    `accessibleCalendarIds()`, with an identical assertion.
  - New: a Super Admin with two unrelated groups' overlapping events no
    longer gets a conflict on either occurrence (`conflicts_with` empty for
    both).
  - New: an admin's own double-booking (their personal calendar event
    overlapping an event in a group they belong to) is still flagged -
    unchanged from today, asserted explicitly so a future change can't
    silently regress it.
  - New: `team_busy` is absent/empty for a plain member with no administered
    groups, present with one entry for a group admin, and present with one
    entry per platform group for a Super Admin.
- **New `tests/Feature/TeamBusyVisibilityTest.php`:**
  - An admin sees a teammate's private personal event as `"Busy"` with
    `owner_name` set to the teammate's name, grouped under the correct
    `group_id`/`group_name`, with the correct `busy_count`.
  - The admin's own personal calendar is excluded from their own team-busy
    panel (the `user_id != $this->id` filter in `teamBusyByGroup()`).
  - A teammate's *public* personal event is unaffected by this feature
    (still shows its real title through the normal accessible-calendar path
    if applicable, and is simply absent from the team-busy panel's own
    redaction-only accounting - the panel doesn't need to special-case this,
    since it only ever queries other members' personal calendars for busy
    accounting, and a public event there is a pre-existing, unrelated
    concern).
  - A Super Admin's `team_busy` covers every group on the platform, not just
    ones they happen to be a `group_user` row in (they have none).
- **`tests/Feature/ConflictReportTest.php`:** all three existing tests
  continue to pass unmodified; no new assertions needed there since the
  dedup and persistence behavior is covered by the new test file below.
- **New `tests/Feature/EventMessageTest.php`:**
  - A member can send a general message about a group event; the group's
    admin(s) receive `EventMessageMail`; a row is persisted with
    `type = 'general'`.
  - Sending a second message (general or `report-conflict`) for the same
    event and the same occurrence by the same sender returns the
    `"You already sent..."` error, persists no second row, and queues no
    second mail - covering all four combinations of
    (first action, second action) x (conflict, general).
  - A different sender, or the same sender on a *different* recurring
    occurrence of the same series, is not blocked by another sender's or
    another occurrence's row.
  - A non-member is forbidden from `sendMessage`, mirroring the existing
    "a non-member cannot report a conflict" test.
  - `occurrence_start` recorded for a recurring event's instance matches the
    instance's actual start, not the master's.

## Out of scope / deferred

- No resolve/dismiss/read workflow for `event_messages` rows in this pass -
  they exist to back the dedup rule and the email, not yet to power an
  admin-facing inbox UI. A future admin "messages" list is a natural
  follow-up but not requested here.
- No change to `EventPolicy`, `CalendarPolicy`, or any write path - this is
  entirely a read-visibility and messaging addition.
- The team-busy panel and messaging are both scoped to group events /
  group-member personal calendars; personal-calendar-to-personal-calendar
  messaging (e.g., two individuals with no shared group) is not a case this
  request describes and is not built.
