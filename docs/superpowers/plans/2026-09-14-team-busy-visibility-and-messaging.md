# Team Busy-Visibility, Conflict Scoping, and Member-to-Admin Messaging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give group admins and the Super Admin visibility into their team's busy status without cluttering the dashboard or producing false conflicts, and let a member message a group's admins about a problem event (a scheduling clash or otherwise), with one shared dedup rule across both actions.

**Architecture:** Two new narrow calendar-id queries on `User` (`personalCalendarIds()`, `scheduleCalendarIds()`) replace the dashboard's use of the too-broad `accessibleCalendarIds()` for display and conflict detection; a third (`teamBusyByGroup()`) feeds a new, structurally separate "Team busy" dashboard panel. A new `RedactedEvent::$ownerName` field lets that panel show "Busy - Jane Smith". A new `event_messages` table, with a unique constraint on `(event_id, sender_id, occurrence_start)`, backs both the existing "Report conflict" action and a new "Message admin" action.

**Tech Stack:** Laravel 13 / PHP 8.4, Pest 5, Inertia 3 + React 19 + TypeScript, Tailwind 4.

**Spec:** `docs/superpowers/specs/2026-09-14-team-busy-visibility-and-messaging-design.md`

## Global Constraints

- `personalCalendarIds()` has **no** Super Admin branch - it is exactly today's `accessibleCalendarIds()` else-branch, unconditional.
- `accessibleCalendarIds()` itself is **never modified** - `CalendarController::all()`, `WritableCalendars`, and `OccurrenceQuery::mastersFor()` keep their current behavior untouched.
- `RedactedEvent::$ownerName` is populated **only** when `$calendar->isPersonal() && ! $visible` - never for a group calendar, never for a visible (non-redacted) event.
- `event_messages.occurrence_start` is **never null** - always the resolved occurrence's actual UTC start instant, even for a one-off event (its own `starts_at`). This is required for the unique constraint to behave identically on SQLite (tests) and MySQL/MariaDB (production), since MySQL treats `NULL` as distinct from other `NULL`s in a unique index.
- The dedup unique key is `(event_id, sender_id, occurrence_start)` - **no `type` column in the key**. A conflict report and a general message block each other for the same sender+event+occurrence.
- All existing tests in `tests/Feature/DashboardTest.php` and `tests/Feature/ConflictReportTest.php` must keep passing unmodified in assertion content (only their surrounding scaffolding may change if a task explicitly says so - none does).
- Every new PHP class follows the existing codebase convention of no import needed for same-namespace classes (e.g. `User.php`, `Calendar.php`, `Group.php`, `GroupUser.php`, `Role.php` all live in `App\Models` and reference each other unqualified).

---

### Task 1: `User` model - calendar-id sets and team lookups

**Files:**
- Modify: `app/Models/User.php:161-176` (after `accessibleCalendarIds()`)
- Test: `tests/Feature/UserCalendarVisibilityTest.php` (create)

**Interfaces:**
- Produces: `User::personalCalendarIds(): Collection<int, int>`, `User::scheduleCalendarIds(): Collection<int, int>`, `User::administeredGroups(): Collection<int, Group>`, `User::teamBusyByGroup(): Collection<int, array{group: Group, calendar_ids: Collection<int, int>}>`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/UserCalendarVisibilityTest.php`:

```php
<?php

use App\Models\Calendar;
use App\Models\Group;
use App\Models\User;

test('personalCalendarIds includes the own personal calendar and member-group calendars only', function () {
    $myGroup = Group::factory()->create();
    $otherGroup = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($myGroup, $user, 'member');

    $myGroupCalendar = Calendar::factory()->create(['group_id' => $myGroup->id]);
    $otherGroupCalendar = Calendar::factory()->create(['group_id' => $otherGroup->id]);
    $personal = $user->personalCalendar();

    $ids = $user->personalCalendarIds();

    expect($ids->sort()->values()->all())->toBe(
        collect([$myGroupCalendar->id, $personal->id])->sort()->values()->all(),
    );
    expect($ids->contains($otherGroupCalendar->id))->toBeFalse();
});

test('personalCalendarIds has no Super Admin escalation', function () {
    $group = Group::factory()->create();
    $superAdmin = asSuperAdmin();
    $groupCalendar = Calendar::factory()->create(['group_id' => $group->id]);
    $personal = $superAdmin->personalCalendar();

    $ids = $superAdmin->personalCalendarIds();

    expect($ids->contains($groupCalendar->id))->toBeFalse();
    expect($ids->all())->toBe([$personal->id]);
});

test('scheduleCalendarIds equals personalCalendarIds for a non-Super-Admin', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'admin');
    Calendar::factory()->create(['group_id' => $group->id]);
    $user->personalCalendar();

    expect($user->scheduleCalendarIds()->sort()->values()->all())
        ->toBe($user->personalCalendarIds()->sort()->values()->all());
});

test('scheduleCalendarIds includes every group calendar for a Super Admin but no other personal calendar', function () {
    $groupA = Group::factory()->create();
    $groupB = Group::factory()->create();
    $superAdmin = asSuperAdmin();
    $calendarA = Calendar::factory()->create(['group_id' => $groupA->id]);
    $calendarB = Calendar::factory()->create(['group_id' => $groupB->id]);

    $otherUser = User::factory()->create();
    $otherPersonal = $otherUser->personalCalendar();

    $ids = $superAdmin->scheduleCalendarIds();

    expect($ids->contains($calendarA->id))->toBeTrue();
    expect($ids->contains($calendarB->id))->toBeTrue();
    expect($ids->contains($otherPersonal->id))->toBeFalse();
});

test('administeredGroups returns only groups the user administers', function () {
    $administered = Group::factory()->create();
    $notAdministered = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($administered, $admin, 'admin');
    asGroupRole($notAdministered, $admin, 'member');

    $groups = $admin->administeredGroups();

    expect($groups->pluck('id')->all())->toBe([$administered->id]);
});

test('administeredGroups returns every group for a Super Admin', function () {
    Group::factory()->count(3)->create();
    $superAdmin = asSuperAdmin();

    expect($superAdmin->administeredGroups())->toHaveCount(3);
});

test('teamBusyByGroup groups teammates personal calendars by administered group, excluding self', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    $memberOne = User::factory()->create();
    $memberTwo = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    asGroupRole($group, $memberOne, 'member');
    asGroupRole($group, $memberTwo, 'member');

    $adminPersonal = $admin->personalCalendar();
    $memberOnePersonal = $memberOne->personalCalendar();
    $memberTwoPersonal = $memberTwo->personalCalendar();

    $result = $admin->teamBusyByGroup();

    expect($result)->toHaveCount(1);
    expect($result[0]['group']->id)->toBe($group->id);

    $calendarIds = $result[0]['calendar_ids']->sort()->values()->all();
    expect($calendarIds)->toBe(
        collect([$memberOnePersonal->id, $memberTwoPersonal->id])->sort()->values()->all(),
    );
    expect($calendarIds)->not->toContain($adminPersonal->id);
});

test('teamBusyByGroup is empty for a member who administers nothing', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');

    expect($member->teamBusyByGroup())->toBeEmpty();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run (PowerShell):
```
php artisan test --filter=UserCalendarVisibilityTest
```
Expected: FAIL - `Call to undefined method App\Models\User::personalCalendarIds()` (and similarly for the other three methods).

- [ ] **Step 3: Implement the four methods**

In `app/Models/User.php`, after the closing brace of `accessibleCalendarIds()` (currently the last method, ending the class at line 175-176), insert before the final `}`:

```php

    /**
     * Calendars this viewer personally belongs to: their own personal
     * calendar, plus every calendar of a group they are an actual member
     * of. Deliberately has no Super Admin branch - "my own affiliations"
     * does not get bigger just because a platform role grants broader
     * browsing rights elsewhere (that is accessibleCalendarIds()). This is
     * the set conflict detection uses.
     *
     * @return Collection<int, int>
     */
    public function personalCalendarIds(): Collection
    {
        return Calendar::query()
            ->where('owner_id', $this->id)
            ->orWhereIn('group_id', $this->memberGroups()->select('groups.id'))
            ->pluck('id');
    }

    /**
     * Calendars that make up this viewer's own schedule for the
     * dashboard's month grid and agenda: personalCalendarIds(), plus - for
     * a Super Admin only - every GROUP calendar on the platform. This lets
     * a Super Admin's dashboard still show every group's events without
     * pulling in another individual user's personal calendar the way
     * accessibleCalendarIds() does. That visibility instead comes only
     * through teamBusyByGroup().
     *
     * @return Collection<int, int>
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

    /**
     * Groups this viewer administers: the ones where they hold the
     * 'admin' role, or every group on the platform for a Super Admin.
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
     * themselves, every membership row across all of them, and every
     * personal calendar owned by any of those members. Grouping happens
     * in PHP over already-fetched collections rather than one query per
     * group.
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

- [ ] **Step 4: Run the tests to verify they pass**

Run:
```
php artisan test --filter=UserCalendarVisibilityTest
```
Expected: PASS, all 7 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Feature/UserCalendarVisibilityTest.php
git commit -m "feat(calendar): add personal/schedule calendar-id sets and team-busy lookup to User"
```

---

### Task 2: `RedactedEvent::$ownerName` and the redaction chokepoint

**Files:**
- Modify: `app/Support/Calendar/RedactedEvent.php`
- Modify: `app/Support/Calendar/EventRedactor.php`
- Modify: `app/Support/Calendar/Occurrence.php`
- Test: `tests/Feature/EventRedactorTest.php` (extend if it exists, else create)

**Interfaces:**
- Consumes: `RedactedEvent` constructor as currently defined (per `docs/superpowers/specs/2026-09-14-team-busy-visibility-and-messaging-design.md` Section 3).
- Produces: `RedactedEvent::$ownerName: ?string` (public readonly property), `Occurrence::toArray()['owner_name']: ?string`.

- [ ] **Step 1: Check for an existing EventRedactor test file and write the failing test**

Run:
```
Get-ChildItem tests/Feature -Filter "EventRedactorTest.php"
```

If it exists, read it first and add the test below inside it, matching its existing style (dataset/actingAs helpers). If it does not exist, create `tests/Feature/EventRedactorTest.php` with:

```php
<?php

use App\Enums\EventVisibility;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use App\Support\Calendar\EventRedactor;

test('a redacted personal-calendar event carries the owner name', function () {
    $owner = User::factory()->create(['name' => 'Jane Smith']);
    $viewer = User::factory()->create();
    $personal = $owner->personalCalendar();

    $event = Event::factory()->create([
        'calendar_id' => $personal->id,
        'visibility' => EventVisibility::Private,
        'created_by' => $owner->id,
    ]);

    $redacted = app(EventRedactor::class)->redact($event, $viewer);

    expect($redacted->isRedacted)->toBeTrue();
    expect($redacted->title)->toBe('Busy');
    expect($redacted->ownerName)->toBe('Jane Smith');
});

test('a visible personal-calendar event carries no owner name', function () {
    $owner = User::factory()->create(['name' => 'Jane Smith']);
    $viewer = User::factory()->create();
    $personal = $owner->personalCalendar();

    $event = Event::factory()->create([
        'calendar_id' => $personal->id,
        'visibility' => EventVisibility::Public,
        'created_by' => $owner->id,
    ]);

    $redacted = app(EventRedactor::class)->redact($event, $viewer);

    expect($redacted->isRedacted)->toBeFalse();
    expect($redacted->ownerName)->toBeNull();
});

test('a redacted group-calendar event carries no owner name', function () {
    $group = Group::factory()->create();
    $creator = User::factory()->create();
    asGroupRole($group, $creator, 'member');
    $viewer = User::factory()->create();
    asGroupRole($group, $viewer, 'member');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $event = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'visibility' => EventVisibility::Busy,
        'created_by' => $creator->id,
    ]);

    $redacted = app(EventRedactor::class)->redact($event, $viewer);

    expect($redacted->isRedacted)->toBeTrue();
    expect($redacted->ownerName)->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:
```
php artisan test --filter=EventRedactorTest
```
Expected: FAIL - `Unknown named parameter $ownerName` (the constructor doesn't accept it yet) or a property-access error once the constructor call in `EventRedactor::redact()` hasn't been updated. Either failure mode confirms the feature is missing.

- [ ] **Step 3: Add `$ownerName` to `RedactedEvent`**

In `app/Support/Calendar/RedactedEvent.php`, find the constructor's final parameter (`recurrenceInstanceId`, per the existing recurrence block) and add a new parameter immediately after it:

```php
        public ?CarbonImmutable $recurrenceInstanceId = null,
        /**
         * The personal calendar's owner's display name, set only when this
         * event is both on a personal calendar and redacted for this
         * viewer. Gives a "Busy" block an identity ("Busy - Jane Smith")
         * without ever revealing anything about a redacted GROUP event's
         * creator, which EventRedactor has never disclosed.
         */
        public ?string $ownerName = null,
```

(Read the file first to get the exact current parameter list and trailing comma placement before editing - the constructor's parameter order and any existing trailing comment must be preserved.)

- [ ] **Step 4: Populate it in `EventRedactor::redact()`**

In `app/Support/Calendar/EventRedactor.php`, inside `redact()`, add one line to the `RedactedEvent` construction (after `recurrenceInstanceId`, matching the parameter order just added):

```php
            recurrenceInstanceId: $event->recurrence_id === null
                ? null
                : CarbonImmutable::parse($event->recurrence_id)->utc(),
            ownerName: ($calendar->isPersonal() && ! $visible) ? $calendar->owner?->name : null,
```

- [ ] **Step 5: Eager-load the owner in `redactMany()`**

In the same file, `redactMany()` currently has:

```php
        $events->loadMissing('calendar.group');
```

Change to:

```php
        $events->loadMissing('calendar.group', 'calendar.owner');
```

- [ ] **Step 6: Add `owner_name` to `Occurrence::toArray()`**

In `app/Support/Calendar/Occurrence.php`, in `toArray()`, add one line after `'group_name' => $this->event->groupName,`:

```php
            'group_name' => $this->event->groupName,
            'owner_name' => $this->event->ownerName,
```

- [ ] **Step 7: Run the tests to verify they pass**

Run:
```
php artisan test --filter=EventRedactorTest
```
Expected: PASS, all 3 tests.

- [ ] **Step 8: Run the full existing calendar test suite to confirm nothing else broke**

Run:
```
php artisan test --filter=Redactor
php artisan test --filter=Occurrence
php artisan test --filter=Dashboard
```
Expected: PASS - `RedactedEvent`'s new constructor parameter is optional (default `null`) and appended last, so every existing call site that doesn't pass it is unaffected.

- [ ] **Step 9: Commit**

```bash
git add app/Support/Calendar/RedactedEvent.php app/Support/Calendar/EventRedactor.php app/Support/Calendar/Occurrence.php tests/Feature/EventRedactorTest.php
git commit -m "feat(calendar): carry a redacted personal event's owner name through to the frontend"
```

---

### Task 3: `DashboardController` - scoped conflicts and the team-busy payload

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Test: `tests/Feature/DashboardTest.php` (extend)
- Test: `tests/Feature/TeamBusyVisibilityTest.php` (create)

**Interfaces:**
- Consumes: `User::personalCalendarIds()`, `User::scheduleCalendarIds()`, `User::teamBusyByGroup()` (Task 1); `Occurrence::toArray()['owner_name']` (Task 2); `OccurrenceQuery::forCalendars(Collection|array $calendarIds, CarbonInterface $from, CarbonInterface $to, User $viewer): Collection` (existing, unmodified).
- Produces: Inertia prop `team_busy: list<array{group_id: int, group_name: string, busy_count: int, occurrences: list<array>}>` on the `Dashboard` page.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/DashboardTest.php` (append at the end of the file):

```php
test('a Super Admin does not get a false conflict between two unrelated groups', function () {
    $groupA = Group::factory()->create();
    $groupB = Group::factory()->create();
    $superAdmin = User::factory()->create();
    $superAdmin->roles()->attach(Role::firstOrCreate(['name' => 'super_admin'])->id);

    $calendarA = Calendar::factory()->create(['group_id' => $groupA->id]);
    $calendarB = Calendar::factory()->create(['group_id' => $groupB->id]);

    $start = now()->addDay()->setTime(10, 0);
    Event::factory()->create([
        'calendar_id' => $calendarA->id,
        'title' => 'Standup',
        'starts_at' => $start,
        'ends_at' => (clone $start)->addHour(),
    ]);
    Event::factory()->create([
        'calendar_id' => $calendarB->id,
        'title' => 'Client Call',
        'starts_at' => (clone $start)->addMinutes(30),
        'ends_at' => (clone $start)->addMinutes(90),
    ]);

    $this->actingAs($superAdmin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('upcoming_events.0.conflicts_with', [])
            ->where('upcoming_events.1.conflicts_with', [])
        );
});

test('an admin double-booked between their own calendar and their group is still flagged', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');

    $groupCalendar = Calendar::factory()->create(['group_id' => $group->id]);
    $personal = $admin->personalCalendar();

    $start = now()->addDay()->setTime(10, 0);
    Event::factory()->create([
        'calendar_id' => $groupCalendar->id,
        'title' => 'Team sync',
        'starts_at' => $start,
        'ends_at' => (clone $start)->addHour(),
    ]);
    Event::factory()->create([
        'calendar_id' => $personal->id,
        'title' => 'Dentist',
        'starts_at' => (clone $start)->addMinutes(30),
        'ends_at' => (clone $start)->addMinutes(90),
    ]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('upcoming_events.0.conflicts_with', ['Dentist'])
            ->where('upcoming_events.1.conflicts_with', ['Team sync'])
        );
});

test('team_busy is empty for a plain member', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');

    $this->actingAs($member)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->has('team_busy', 0));
});

test('team_busy has one entry per administered group for an admin, with the correct busy count', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    $teammate = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    asGroupRole($group, $teammate, 'member');

    $teammatePersonal = $teammate->personalCalendar();
    Event::factory()->create([
        'calendar_id' => $teammatePersonal->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('team_busy', 1)
            ->where('team_busy.0.group_id', $group->id)
            ->where('team_busy.0.group_name', $group->name)
            ->where('team_busy.0.busy_count', 1)
            ->where('team_busy.0.occurrences.0.owner_name', $teammate->name)
        );
});

test('team_busy covers every group on the platform for a Super Admin', function () {
    Group::factory()->count(2)->create();
    $superAdmin = User::factory()->create();
    $superAdmin->roles()->attach(Role::firstOrCreate(['name' => 'super_admin'])->id);

    $this->actingAs($superAdmin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->has('team_busy', 2));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:
```
php artisan test --filter=DashboardTest
```
Expected: FAIL - the false-conflict test fails because both events currently show a conflict; the `team_busy` tests fail with a missing Inertia prop error ("Property [team_busy] does not exist").

- [ ] **Step 3: Rewrite `DashboardController::index()`**

Read `app/Http/Controllers/DashboardController.php` first (already open from planning), then replace the entire `index()` method body:

```php
    public function index(Request $request): Response
    {
        $user = $request->user();
        $windowDays = (int) config('calendar.conflicts.window_days');

        $month = $this->month($request);
        [$gridFrom, $gridTo] = $this->gridRange($month);

        $scheduleIds = $user->scheduleCalendarIds();

        // Two windows, two queries. The grid covers the month on screen; the
        // agenda beside it covers the next few days, which is a different
        // question and almost never the same set of events.
        $monthOccurrences = $this->occurrences->forCalendars($scheduleIds, $gridFrom, $gridTo, $user);

        $upcoming = $this->occurrences->forCalendars($scheduleIds, now(), now()->addDays($windowDays), $user);

        // Conflicts are checked over a narrower set than the grid displays:
        // personalCalendarIds() has no Super Admin escalation, so two
        // unrelated groups' events no longer read as "conflicting" just
        // because a platform role can see both of them.
        $conflictCandidates = $this->occurrences->forCalendars(
            $user->personalCalendarIds(),
            now(),
            now()->addDays($windowDays),
            $user,
        );
        $conflicts = $this->conflicts->detect($conflictCandidates);

        return Inertia::render('Dashboard', [
            'month' => $month->toDateString(),
            'occurrences' => $monthOccurrences
                ->map(fn (Occurrence $occurrence) => $occurrence->toArray())
                ->values(),
            'upcoming_events' => $upcoming->map(function (Occurrence $occurrence) use ($conflicts) {
                $clashes = $conflicts[$occurrence->key()] ?? [];

                return [
                    ...$occurrence->toArray(),
                    // Titles come off the redacted occurrence, so a clash with
                    // someone's private appointment reads as "Busy" rather
                    // than naming it.
                    'conflicts_with' => array_map(
                        fn (Occurrence $other) => $other->event->title,
                        $clashes,
                    ),
                ];
            })->values(),
            'window_days' => $windowDays,
            'writable_calendars' => $this->writable->options($user),
            'team_busy' => $this->teamBusyPayload($user, $windowDays),
        ]);
    }

    /**
     * @return list<array{group_id: int, group_name: string, busy_count: int, occurrences: list<array<string, mixed>>}>
     */
    private function teamBusyPayload(\App\Models\User $user, int $windowDays): array
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
                'occurrences' => $occurrences->map(fn (Occurrence $o) => $o->toArray())->values()->all(),
            ];
        })->values()->all();
    }
```

Add `use App\Models\User;` to the top of the file (with the other `use` statements) and change the inline `\App\Models\User` type-hint in `teamBusyPayload()` to the plain `User` once imported, matching the file's existing style of importing rather than fully-qualifying.

- [ ] **Step 4: Run the tests to verify they pass**

Run:
```
php artisan test --filter=DashboardTest
```
Expected: PASS, all tests (the 5 pre-existing ones plus the 5 new ones in this task).

- [ ] **Step 5: Create the dedicated team-busy visibility test file**

Create `tests/Feature/TeamBusyVisibilityTest.php`:

```php
<?php

use App\Enums\EventVisibility;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;

test('an admin sees a teammates private personal event as Busy with their name attached', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    $teammate = User::factory()->create(['name' => 'Jane Smith']);
    asGroupRole($group, $admin, 'admin');
    asGroupRole($group, $teammate, 'member');

    $teammatePersonal = $teammate->personalCalendar();
    Event::factory()->create([
        'calendar_id' => $teammatePersonal->id,
        'title' => 'Therapy appointment',
        'visibility' => EventVisibility::Private,
        'created_by' => $teammate->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('team_busy.0.occurrences.0.title', 'Busy')
            ->where('team_busy.0.occurrences.0.owner_name', 'Jane Smith')
        );
});

test('an admin does not see their own personal calendar in their own team-busy panel', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');

    $adminPersonal = $admin->personalCalendar();
    Event::factory()->create([
        'calendar_id' => $adminPersonal->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('team_busy.0.busy_count', 0));
});
```

- [ ] **Step 6: Run the new test file**

Run:
```
php artisan test --filter=TeamBusyVisibilityTest
```
Expected: PASS, both tests.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/DashboardController.php tests/Feature/DashboardTest.php tests/Feature/TeamBusyVisibilityTest.php
git commit -m "fix(dashboard): scope conflict detection to the viewer's own affiliations, add team-busy panel data"
```

---

### Task 4: Frontend - `owner_name` type and the Team busy panel

**Files:**
- Modify: `resources/js/types/calendar.ts`
- Modify: `resources/js/pages/Dashboard.tsx`

**Interfaces:**
- Consumes: `team_busy: TeamBusyGroup[]` Inertia prop (Task 3); `Occurrence.owner_name: string | null` (Task 2/3).
- Produces: `TeamBusyGroup` type, rendered "Team busy" `Card` in `Dashboard.tsx`'s aside.

- [ ] **Step 1: Add `owner_name` to the `Occurrence` type**

In `resources/js/types/calendar.ts`, in the `Occurrence` interface, add after `group_name`:

```ts
    /** Null for a personal calendar. */
    group_name: string | null;
    /** Set only for a redacted event on someone's personal calendar - "Busy - Jane Smith". */
    owner_name: string | null;
```

(Read the file first to confirm the exact current line so the insertion is placed correctly relative to `group_name`.)

- [ ] **Step 2: Add the `TeamBusyGroup` type and prop to `Dashboard.tsx`**

In `resources/js/pages/Dashboard.tsx`, after the existing `UpcomingEvent` interface, add:

```ts
interface TeamBusyGroup {
    group_id: number;
    group_name: string;
    busy_count: number;
    occurrences: Occurrence[];
}
```

In `DashboardProps`, add after `writable_calendars`:

```ts
    writable_calendars: WritableCalendar[];
    team_busy: TeamBusyGroup[];
```

In the destructuring inside `export default function Dashboard()`, add `team_busy: teamBusy,` alongside the other destructured props.

- [ ] **Step 3: Add expand/collapse state**

Near the other `useState` calls in `Dashboard()`, add:

```ts
    const [expandedTeams, setExpandedTeams] = useState<Set<number>>(
        new Set(),
    );

    const toggleTeam = (groupId: number) => {
        setExpandedTeams((prev) => {
            const next = new Set(prev);

            if (next.has(groupId)) {
                next.delete(groupId);
            } else {
                next.add(groupId);
            }

            return next;
        });
    };
```

- [ ] **Step 4: Render the panel**

In the `<aside>`, after the closing tag of the existing "Next N days" `<Card>` and before the aside's own closing tag, add:

```tsx
                        {teamBusy.length > 0 && (
                            <Card material="thin" padded={false}>
                                <h3 className="border-hairline text-headline text-content border-b px-4 py-3">
                                    Team busy
                                </h3>
                                <div className="divide-hairline divide-y">
                                    {teamBusy.map((team) => {
                                        const expanded = expandedTeams.has(
                                            team.group_id,
                                        );

                                        return (
                                            <div key={team.group_id}>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        toggleTeam(
                                                            team.group_id,
                                                        )
                                                    }
                                                    className="hover:bg-surface-raised flex w-full items-center justify-between px-4 py-3 text-start"
                                                >
                                                    <span className="text-content text-footnote font-medium">
                                                        {team.group_name} -{' '}
                                                        {team.busy_count} busy
                                                    </span>
                                                    <ChevronRight
                                                        className={`text-content-tertiary size-4 shrink-0 transition-transform ${
                                                            expanded
                                                                ? 'rotate-90'
                                                                : ''
                                                        }`}
                                                    />
                                                </button>
                                                {expanded && (
                                                    <ul className="space-y-2 px-4 pb-3">
                                                        {team.occurrences.length ===
                                                        0 ? (
                                                            <li className="text-content-tertiary text-caption1">
                                                                Nothing
                                                                scheduled.
                                                            </li>
                                                        ) : (
                                                            team.occurrences.map(
                                                                (
                                                                    occurrence,
                                                                ) => (
                                                                    <li
                                                                        key={
                                                                            occurrence.key
                                                                        }
                                                                    >
                                                                        <p className="text-content text-footnote">
                                                                            Busy
                                                                            {occurrence.owner_name
                                                                                ? ` - ${occurrence.owner_name}`
                                                                                : ''}
                                                                        </p>
                                                                        <p className="text-content-tertiary text-caption1">
                                                                            {formatTimeRange(
                                                                                occurrence,
                                                                            )}
                                                                        </p>
                                                                    </li>
                                                                ),
                                                            )
                                                        )}
                                                    </ul>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </Card>
                        )}
```

`ChevronRight` is already imported from `lucide-react` at the top of the file (used elsewhere for month navigation) - reuse it, do not add a duplicate import.

- [ ] **Step 5: Build and eyeball**

Run:
```
npm run build
```
Expected: build succeeds with no TypeScript errors (this confirms `Occurrence`, `TeamBusyGroup`, and the new prop line up).

- [ ] **Step 6: Commit**

```bash
git add resources/js/types/calendar.ts resources/js/pages/Dashboard.tsx
git commit -m "feat(dashboard): render the collapsed-by-default team-busy panel"
```

---

### Task 5: `event_messages` table, model, and enum

**Files:**
- Create: `database/migrations/2026_09_14_120000_create_event_messages_table.php`
- Create: `app/Enums/EventMessageType.php`
- Create: `app/Models/EventMessage.php`
- Test: `tests/Feature/EventMessageModelTest.php` (create)

**Interfaces:**
- Produces: `EventMessage` Eloquent model with `event()`, `sender()` relations and casts `type => EventMessageType`, `occurrence_start => datetime`, `conflicting_titles => array`; `EventMessageType::Conflict`, `EventMessageType::General`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/EventMessageModelTest.php`:

```php
<?php

use App\Enums\EventMessageType;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\EventMessage;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\QueryException;

test('an event message casts its type and stores conflicting titles as an array', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);
    $sender = User::factory()->create();

    $message = EventMessage::create([
        'event_id' => $event->id,
        'sender_id' => $sender->id,
        'type' => EventMessageType::Conflict->value,
        'occurrence_start' => now(),
        'body' => 'Reported a scheduling conflict.',
        'conflicting_titles' => ['Client Call'],
    ]);

    $message->refresh();

    expect($message->type)->toBe(EventMessageType::Conflict);
    expect($message->conflicting_titles)->toBe(['Client Call']);
    expect($message->event->id)->toBe($event->id);
    expect($message->sender->id)->toBe($sender->id);
});

test('the same sender cannot message about the same event and occurrence twice', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);
    $sender = User::factory()->create();
    $occurrenceStart = now();

    EventMessage::create([
        'event_id' => $event->id,
        'sender_id' => $sender->id,
        'type' => EventMessageType::General->value,
        'occurrence_start' => $occurrenceStart,
        'body' => 'First message.',
    ]);

    expect(fn () => EventMessage::create([
        'event_id' => $event->id,
        'sender_id' => $sender->id,
        'type' => EventMessageType::Conflict->value,
        'occurrence_start' => $occurrenceStart,
        'body' => 'Reported a scheduling conflict.',
    ]))->toThrow(QueryException::class);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run:
```
php artisan test --filter=EventMessageModelTest
```
Expected: FAIL - `Class "App\Models\EventMessage" not found`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_09_14_120000_create_event_messages_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16);
            // Always the resolved occurrence's actual start instant, never
            // null - even for a one-off event, this stores that event's own
            // starts_at. MySQL/MariaDB unique indexes treat NULL as distinct
            // from every other NULL, which would silently defeat the dedup
            // constraint below for every non-recurring event if this were
            // nullable.
            $table->dateTime('occurrence_start');
            $table->text('body');
            // Only populated for type='conflict'; null for a general message.
            $table->json('conflicting_titles')->nullable();
            $table->timestamps();

            // One message per sender per occurrence, regardless of type - a
            // member who already reported a conflict on this instance is
            // blocked from also sending a general message about it, and vice
            // versa.
            $table->unique(['event_id', 'sender_id', 'occurrence_start'], 'event_messages_dedup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_messages');
    }
};
```

- [ ] **Step 4: Create the enum**

Create `app/Enums/EventMessageType.php`:

```php
<?php

namespace App\Enums;

/**
 * Whether an event_messages row came from the "Report conflict" action or
 * the free-text "Message admin" action. Both share one dedup rule; this
 * only distinguishes how the row is displayed and emailed.
 */
enum EventMessageType: string
{
    case Conflict = 'conflict';
    case General = 'general';
}
```

- [ ] **Step 5: Create the model**

Create `app/Models/EventMessage.php`:

```php
<?php

namespace App\Models;

use App\Enums\EventMessageType;
use Database\Factories\EventMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property int $sender_id
 * @property EventMessageType $type
 * @property Carbon $occurrence_start
 * @property string $body
 * @property array<int, string>|null $conflicting_titles
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read User $sender
 */
class EventMessage extends Model
{
    /** @use HasFactory<EventMessageFactory> */
    use HasFactory;

    protected $fillable = ['event_id', 'sender_id', 'type', 'occurrence_start', 'body', 'conflicting_titles'];

    protected function casts(): array
    {
        return [
            'type' => EventMessageType::class,
            'occurrence_start' => 'datetime',
            'conflicting_titles' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
```

- [ ] **Step 6: Create a minimal factory (needed by `HasFactory`, not by the tests above, but required for the trait to resolve)**

Create `database/factories/EventMessageFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\EventMessageType;
use App\Models\Event;
use App\Models\EventMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventMessage>
 */
class EventMessageFactory extends Factory
{
    protected $model = EventMessage::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'sender_id' => User::factory(),
            'type' => EventMessageType::General->value,
            'occurrence_start' => now(),
            'body' => fake()->sentence(),
        ];
    }
}
```

- [ ] **Step 7: Run the migration and the tests**

Run:
```
php artisan migrate
php artisan test --filter=EventMessageModelTest
```
Expected: PASS, both tests. (`php artisan test` runs its own `RefreshDatabase` migrations against the testing connection, but running `php artisan migrate` locally first catches a syntax error immediately rather than inside the test runner.)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_14_120000_create_event_messages_table.php app/Enums/EventMessageType.php app/Models/EventMessage.php database/factories/EventMessageFactory.php tests/Feature/EventMessageModelTest.php
git commit -m "feat(calendar): add the event_messages table backing report-conflict and message-admin"
```

---

### Task 6: `GroupAdminLookup` extraction and `reportConflict()` dedup + persistence

**Files:**
- Create: `app/Support/Calendar/GroupAdminLookup.php`
- Modify: `app/Http/Controllers/EventController.php`
- Test: `tests/Feature/ConflictReportTest.php` (extend)

**Interfaces:**
- Consumes: `EventMessage` model (Task 5), `EventMessageType` enum (Task 5).
- Produces: `GroupAdminLookup::forGroup(Group $group): Collection<int, User>`; `EventController::alreadyMessaged(Event $event, User $user, CarbonImmutable $occurrenceStart): bool` (private).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/ConflictReportTest.php`:

```php
test('reporting a conflict persists an event message and a second report is blocked', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    asGroupRole($group, $member, 'member');

    $calendarA = Calendar::factory()->create(['group_id' => $group->id]);
    $calendarB = Calendar::factory()->create(['group_id' => $group->id]);

    $start = now()->addDay()->setTime(10, 0);
    $eventA = Event::factory()->create([
        'calendar_id' => $calendarA->id,
        'title' => 'Standup',
        'starts_at' => $start,
        'ends_at' => (clone $start)->addHour(),
    ]);
    Event::factory()->create([
        'calendar_id' => $calendarB->id,
        'title' => 'Client Call',
        'starts_at' => (clone $start)->addMinutes(30),
        'ends_at' => (clone $start)->addMinutes(90),
    ]);

    $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendarA->id}/events/{$eventA->id}/report-conflict",
    );

    expect(App\Models\EventMessage::count())->toBe(1);
    expect(App\Models\EventMessage::first()->type)->toBe(App\Enums\EventMessageType::Conflict);

    Mail::fake();

    $response = $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendarA->id}/events/{$eventA->id}/report-conflict",
    );

    $response->assertSessionHas('error');
    expect(App\Models\EventMessage::count())->toBe(1);
    Mail::assertNothingQueued();
});
```

Add `use App\Models\EventMessage;` and `use App\Enums\EventMessageType;` to the top of `tests/Feature/ConflictReportTest.php` if fully-qualifying inline (as above) is not preferred - either is acceptable Pest style; keep consistent with the file's existing imports (it currently imports `Mail`, `Calendar`, `Event`, `Group`, `User`, `ConflictReportedMail`).

- [ ] **Step 2: Run the test to verify it fails**

Run:
```
php artisan test --filter=ConflictReportTest
```
Expected: FAIL - `EventMessage::count()` is `0` after the first request (no row is persisted yet), and the second request does not yet return an `error` session key.

- [ ] **Step 3: Extract `GroupAdminLookup`**

Create `app/Support/Calendar/GroupAdminLookup.php`:

```php
<?php

namespace App\Support\Calendar;

use App\Enums\RoleName;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who to notify about a group's event: its admins.
 *
 * Extracted from EventController so both the existing "report conflict"
 * action and the new "message admin" action look this up the same way,
 * rather than one drifting from the other.
 */
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

- [ ] **Step 4: Wire it into `EventController` and add the dedup helper**

In `app/Http/Controllers/EventController.php`:

1. Add imports: `use App\Enums\EventMessageType;`, `use App\Models\EventMessage;`, `use App\Support\Calendar\GroupAdminLookup;`, `use Carbon\CarbonImmutable;`.
2. Remove the now-unused `use App\Models\GroupUser;` and `use App\Models\Role;` imports (both were only used by the private `groupAdmins()` method being deleted) - but first grep the file to confirm neither `GroupUser` nor `Role` is referenced anywhere else in it; if either is, keep that import.
3. Add a fourth constructor-promoted property:

```php
    public function __construct(
        private readonly OccurrenceQuery $occurrences,
        private readonly ConflictDetector $conflicts,
        private readonly RecurrenceEditor $recurrence,
        private readonly GroupAdminLookup $groupAdminLookup,
    ) {}
```

4. Delete the private `groupAdmins()` method entirely.
5. Replace `reportConflict()`'s body:

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

- [ ] **Step 5: Run the tests to verify they pass**

Run:
```
php artisan test --filter=ConflictReportTest
```
Expected: PASS - all 4 tests (the 3 pre-existing plus the new one).

- [ ] **Step 6: Commit**

```bash
git add app/Support/Calendar/GroupAdminLookup.php app/Http/Controllers/EventController.php tests/Feature/ConflictReportTest.php
git commit -m "refactor(events): extract GroupAdminLookup, persist and dedup conflict reports"
```

---

### Task 7: `sendMessage()` endpoint, route, and mail

**Files:**
- Modify: `app/Http/Controllers/EventController.php`
- Modify: `routes/web.php`
- Create: `app/Mail/EventMessageMail.php`
- Create: `resources/views/emails/event-message.blade.php`
- Test: `tests/Feature/EventMessageTest.php` (create)

**Interfaces:**
- Consumes: `GroupAdminLookup::forGroup()`, `EventMessage`, `EventMessageType::General` (Task 5/6).
- Produces: route `events.message` (`POST .../events/{event}/message`), `EventController::sendMessage()`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/EventMessageTest.php`:

```php
<?php

use App\Enums\EventMessageType;
use App\Mail\EventMessageMail;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\EventMessage;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('a member can send a message to the group admins about a team event', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    asGroupRole($group, $member, 'member');

    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $response = $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}/message",
        ['body' => 'This slot never works for me.'],
    );

    $response->assertRedirect();
    expect(EventMessage::count())->toBe(1);
    expect(EventMessage::first()->type)->toBe(EventMessageType::General);
    expect(EventMessage::first()->body)->toBe('This slot never works for me.');

    Mail::assertQueued(EventMessageMail::class, function ($mail) use ($admin) {
        return $mail->hasTo($admin->email) && $mail->body === 'This slot never works for me.';
    });
});

test('a second message about the same event and occurrence is blocked', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}/message",
        ['body' => 'First message.'],
    );

    Mail::fake();

    $response = $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}/message",
        ['body' => 'Second message.'],
    );

    $response->assertSessionHas('error');
    expect(EventMessage::count())->toBe(1);
    Mail::assertNothingQueued();
});

test('a body is required', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $this->actingAs($member)
        ->post("/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}/message", ['body' => ''])
        ->assertSessionHasErrors('body');
});

test('a non-member cannot message about an event', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $outsider = User::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $this->actingAs($outsider)
        ->post("/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}/message", ['body' => 'Hi'])
        ->assertForbidden();

    Mail::assertNothingQueued();
});

test('a message and a conflict report about the same occurrence block each other', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    asGroupRole($group, $member, 'member');

    $calendarA = Calendar::factory()->create(['group_id' => $group->id]);
    $calendarB = Calendar::factory()->create(['group_id' => $group->id]);

    $start = now()->addDay()->setTime(10, 0);
    $eventA = Event::factory()->create([
        'calendar_id' => $calendarA->id,
        'title' => 'Standup',
        'starts_at' => $start,
        'ends_at' => (clone $start)->addHour(),
    ]);
    Event::factory()->create([
        'calendar_id' => $calendarB->id,
        'title' => 'Client Call',
        'starts_at' => (clone $start)->addMinutes(30),
        'ends_at' => (clone $start)->addMinutes(90),
    ]);

    // Message first.
    $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendarA->id}/events/{$eventA->id}/message",
        ['body' => 'This clashes with something.'],
    );

    Mail::fake();

    // Reporting the same occurrence's conflict afterward is blocked.
    $response = $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendarA->id}/events/{$eventA->id}/report-conflict",
    );

    $response->assertSessionHas('error');
    expect(EventMessage::count())->toBe(1);
    Mail::assertNothingQueued();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:
```
php artisan test --filter=EventMessageTest
```
Expected: FAIL - `sendMessage` route/action doesn't exist yet (404 or route-not-found errors).

- [ ] **Step 3: Add the route**

In `routes/web.php`, immediately after the existing `events.report-conflict` route:

```php
            Route::post('events/{event}/report-conflict', [EventController::class, 'reportConflict'])
                ->name('events.report-conflict');
            Route::post('events/{event}/message', [EventController::class, 'sendMessage'])
                ->name('events.message');
```

- [ ] **Step 4: Create the mail class**

Create `app/Mail/EventMessageMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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

- [ ] **Step 5: Create the email view**

Create `resources/views/emails/event-message.blade.php`:

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

- [ ] **Step 6: Add `sendMessage()` to `EventController`**

Add the following method to `app/Http/Controllers/EventController.php`, immediately after `reportConflict()`:

```php
    /**
     * A member tells the group's admins about a problem event - a
     * scheduling clash they'd rather describe in their own words, or
     * anything else. Shares the same dedup row as reportConflict(): once
     * either action has been used for this sender+event+occurrence, the
     * other is blocked too.
     */
    public function sendMessage(Request $request, Group $group, Calendar $calendar, Event $event): RedirectResponse
    {
        $this->authorize('view', $event);

        $data = $request->validate([
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
            'body' => $data['body'],
        ]);

        foreach ($this->groupAdminLookup->forGroup($group) as $admin) {
            Mail::to($admin->email)->send(new EventMessageMail($user, $event, $data['body']));
        }

        return back()->with('success', 'Message sent to the group admin(s).');
    }
```

Add `use App\Mail\EventMessageMail;` to the file's imports.

- [ ] **Step 7: Run the tests to verify they pass**

Run:
```
php artisan test --filter=EventMessageTest
```
Expected: PASS, all 5 tests.

- [ ] **Step 8: Run the full backend test suite**

Run:
```
php artisan test
```
Expected: PASS in full - this confirms nothing in Tasks 1-6 regressed.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/EventController.php routes/web.php app/Mail/EventMessageMail.php resources/views/emails/event-message.blade.php tests/Feature/EventMessageTest.php
git commit -m "feat(events): add the message-admin action, sharing its dedup with report-conflict"
```

---

### Task 8: Frontend - `EventMessageDialog` and the Dashboard's Message action

**Files:**
- Create: `resources/js/components/Calendar/EventMessageDialog.tsx`
- Modify: `resources/js/pages/Dashboard.tsx`

**Interfaces:**
- Consumes: `Modal`, `Button`, `Textarea` from `@/components/ui`; posts to `events.message` route (Task 7).
- Produces: `EventMessageDialog` component with props `{ open: boolean; onClose: () => void; onSent: () => void; groupId: number; calendarId: number; eventId: number; occurrenceStart: string }`.

- [ ] **Step 1: Read `EventDialog.tsx` for the `Modal` usage pattern**

Run (read-only, to match its exact `Modal` props and structure before writing a new component):
```
Get-Content resources/js/components/Calendar/EventDialog.tsx -TotalCount 60
```

- [ ] **Step 2: Create `EventMessageDialog.tsx`**

```tsx
import { Button, Modal, Textarea } from '@/components/ui';
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
                    onSent();
                    onClose();
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Modal open={open} onClose={onClose} title="Message the group admins">
            <div className="space-y-3">
                <Textarea
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                    rows={4}
                    placeholder="What's the problem with this event?"
                />
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={processing || body.trim() === ''}
                    >
                        Send
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
```

(If Step 1's read shows `Modal`'s actual prop names differ from `open`/`onClose`/`title` - e.g. it takes `isOpen` or renders its own header slot instead of a `title` prop - adjust this component to match `Modal`'s real signature; the props above are this plan's best inference from `ModalProps` being exported without having read the file, and must be reconciled against the real file before this step is considered done.)

- [ ] **Step 3: Wire it into `Dashboard.tsx`**

Add the import at the top:

```ts
import EventMessageDialog from '@/components/Calendar/EventMessageDialog';
```

Add state near the other dialog state:

```ts
    const [messagingEvent, setMessagingEvent] = useState<UpcomingEvent | null>(
        null,
    );
    const [messagedIds, setMessagedIds] = useState<number[]>([]);
```

Replace the agenda list's action area - currently:

```tsx
                                            <div className="shrink-0">
                                                {event.can_edit &&
                                                !event.is_redacted ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            openEdit(
                                                                event,
                                                            )
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                ) : (
                                                    event
                                                        .conflicts_with
                                                        .length >
                                                        0 &&
                                                    event.group_id !==
                                                        null &&
                                                    (reportedIds.includes(
                                                        event.event_id,
                                                    ) ? (
                                                        <span className="text-content-tertiary text-caption1">
                                                            Reported
                                                        </span>
                                                    ) : (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                reportConflict(
                                                                    event,
                                                                )
                                                            }
                                                        >
                                                            Report
                                                        </Button>
                                                    ))
                                                )}
                                            </div>
```

with:

```tsx
                                            <div className="flex shrink-0 items-center gap-2">
                                                {event.can_edit &&
                                                !event.is_redacted ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            openEdit(event)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                ) : (
                                                    <>
                                                        {event.group_id !==
                                                            null &&
                                                            (messagedIds.includes(
                                                                event.event_id,
                                                            ) ? (
                                                                <span className="text-content-tertiary text-caption1">
                                                                    Messaged
                                                                </span>
                                                            ) : (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setMessagingEvent(
                                                                            event,
                                                                        )
                                                                    }
                                                                >
                                                                    Message
                                                                </Button>
                                                            ))}
                                                        {event.conflicts_with
                                                            .length > 0 &&
                                                            event.group_id !==
                                                                null &&
                                                            (reportedIds.includes(
                                                                event.event_id,
                                                            ) ? (
                                                                <span className="text-content-tertiary text-caption1">
                                                                    Reported
                                                                </span>
                                                            ) : (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        reportConflict(
                                                                            event,
                                                                        )
                                                                    }
                                                                >
                                                                    Report
                                                                </Button>
                                                            ))}
                                                    </>
                                                )}
                                            </div>
```

Add the dialog itself near the page's other dialogs (after the existing `<RecurrenceScopeDialog />`):

```tsx
            {messagingEvent && (
                <EventMessageDialog
                    open={messagingEvent !== null}
                    onClose={() => setMessagingEvent(null)}
                    onSent={() =>
                        setMessagedIds((ids) => [
                            ...ids,
                            messagingEvent.event_id,
                        ])
                    }
                    groupId={messagingEvent.group_id!}
                    calendarId={messagingEvent.calendar_id}
                    eventId={messagingEvent.event_id}
                    occurrenceStart={messagingEvent.starts_at}
                />
            )}
```

(`messagingEvent.group_id!` - the non-null assertion is safe here because the "Message" button only ever sets `messagingEvent` when `event.group_id !== null`, per the condition guarding the button itself.)

- [ ] **Step 4: Build**

Run:
```
npm run build
```
Expected: build succeeds with no TypeScript errors.

- [ ] **Step 5: Manual browser check**

Start the dev server if not already running (`composer dev` or `npm run dev` alongside `php artisan serve`, per this repo's existing dev workflow) and, logged in as a member of a group with a group event they cannot edit:
1. Confirm a "Message" button appears next to (or in place of) "Report" on such an agenda row.
2. Click it, type a message, send it - confirm the dialog closes and the button becomes "Messaged".
3. Reload the dashboard - confirm it still reads "Messaged" (i.e. `messagedIds` would reset on reload since it's local state; if the row should stay "Messaged" after a reload, that requires the server to tell the client which occurrences this user has already messaged - note this as a known gap in the commit message below and in `PROGRESS.md`, rather than silently shipping a false "Messaged" indicator that reverts on refresh).

Do this by hand once a browser is available in-session; if none is available, note it as an outstanding manual check in `PROGRESS.md` (Task 9) rather than skipping the note.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/Calendar/EventMessageDialog.tsx resources/js/pages/Dashboard.tsx
git commit -m "feat(dashboard): add the message-admin dialog and button to the agenda list"
```

---

### Task 9: Final verification and `PROGRESS.md` update

**Files:**
- Modify: `docs/superpowers/plans/PROGRESS.md`

- [ ] **Step 1: Run all four gates**

Run each separately (PowerShell, not Git Bash, per this project's environment notes):
```
composer test
npm run check
npm run types:check
npm run build
```
Expected: all four green. Before trusting the result, confirm `public/hot` does not exist in the project root (`Test-Path public/hot` should print `False`) - its presence would mask a broken production build by serving from the Vite dev server instead of the manifest.

- [ ] **Step 2: If `composer test` fails on PHPStan, re-run with the memory limit this repo needs**

```
vendor/bin/phpstan analyse --memory-limit=1G
```

Fix anything it reports (most likely candidates: the new `EventMessage` model's `@property` docblock needing to match its casts exactly, or a missing return-type hint on `teamBusyPayload()`/`administeredGroups()`) before proceeding.

- [ ] **Step 3: Confirm the migration is applied to the local dev database**

`composer dev` does not auto-migrate (a known, previously-hit trap in this project). Run:
```
php artisan migrate
```
and confirm `event_messages` is reported as already run or newly migrated, not missing.

- [ ] **Step 4: Update `docs/superpowers/plans/PROGRESS.md`**

Replace the "Resume here" section's pointer to this feature (currently pointing at the spec review gate) with a note that it is implemented, tested, and gated, and restore the pointer to Task 7 of the paper UI conversion plan as the next work. Also add a row to the "Done and merged to `dev`" table once this branch is actually merged (not before - that table lists work already on `dev`, and this plan's branch merges only after the user reviews and confirms, per this project's git workflow of never pushing to `main` and always PR-ing to `dev`).

Read the current file first, then apply an edit similar to:

```markdown
## Resume here

> **Next:** Task 7 of the paper UI conversion — the landing page.
>
> Team busy-visibility, conflict scoping, the team-busy panel, and
> member-to-admin messaging (spec:
> `docs/superpowers/specs/2026-09-14-team-busy-visibility-and-messaging-design.md`,
> plan: `docs/superpowers/plans/2026-09-14-team-busy-visibility-and-messaging.md`)
> are implemented and passing all four gates on branch `<branch-name>`, not
> yet merged to `dev`. One manual browser check outstanding: confirming the
> "Message" button's UX on an actual agenda row (see that plan's Task 8,
> Step 5) - also flagging a known gap found there: `messagedIds` is local
> React state, so a "Messaged" button reverts to "Message" on page reload
> even though the server-side dedup still blocks a second send. A future
> pass could have the server tell the client which occurrences this viewer
> has already messaged.
>
> [... preserve the existing two-manual-browser-check paragraph and the
> Task 6 recurrence-editing note below this, unchanged ...]
```

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/plans/PROGRESS.md
git commit -m "docs: record the team busy-visibility and messaging feature in PROGRESS.md"
```

---

## Self-review notes

**Spec coverage:** Section 1 (calendar-id sets) → Task 1. Section 2 (conflict scoping) → Task 3. Section 3 (team-busy panel, `ownerName`) → Tasks 2-4. Section 4 (messaging) → Tasks 5-8. Testing strategy's every named test file → Tasks 1, 2, 3, 6, 7. Verification checkpoints → Task 9.

**Placeholder scan:** no TBD/TODO; the one deliberately-flagged uncertainty (Task 8's `Modal` prop names) is called out explicitly as something to reconcile against the real file, not left vague - it names exactly what to check and what "done" looks like once checked.

**Type consistency:** `personalCalendarIds()`, `scheduleCalendarIds()`, `administeredGroups()`, `teamBusyByGroup()` (Task 1) are used with identical names and return shapes in Tasks 3 and 4. `RedactedEvent::$ownerName` / `Occurrence::toArray()['owner_name']` / TS `Occurrence.owner_name` (Task 2) match through Tasks 3-4. `EventMessage`, `EventMessageType::Conflict`/`General`, `GroupAdminLookup::forGroup()` (Tasks 5-6) are used identically in Tasks 6-7. The `events.message` route name and its URL shape are identical between Task 7 (backend route + tests) and Task 8 (frontend `router.post` call).
