# Paper UI Conversion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the 17 remaining pages from hard-coded Tailwind palette classes onto the paper design system, then delete the legacy bridge that is currently holding them together.

**Architecture:** The token layer, the 22 primitives and `AppShell` already exist and are merged. Nothing in `resources/css/app.css` or `resources/js/components/ui/` changes in this plan — pages consume tokens, they never define them. Each task converts one coherent group of pages, ending with the whole suite green. The last task removes the compatibility shim, which is only safe once no page depends on it.

**Tech Stack:** Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, Pest 5, Headless UI 2, lucide-react.

**Spec:** No separate spec document. The design was agreed in brainstorming and is already implemented in `resources/css/app.css` (the token layer), `resources/js/components/ui/` (the primitives) and `resources/js/pages/Dashboard.tsx` (the reference conversion). Read those three before starting.

## Global Constraints

- **Never create `public/hot`.** It makes Laravel skip the Vite manifest and masks real page-render failures. Confirm it is absent before running tests.
- **`php`, `composer` and `npm` are not on the Git Bash PATH.** Run them from PowerShell.
- **PHPStan needs `--memory-limit=1G`** on this project.
- **After adding any new page under `resources/js/pages`, run `npm run build`**, or its feature test 500s with `ViteException: Unable to locate file in Vite manifest`.
- **Do not modify `resources/css/app.css` or anything in `resources/js/components/ui/`.** If a page needs a token or a variant that does not exist, that is a finding to report, not an inline addition.
- **Do not change any server-side behaviour.** No controller, policy, request or migration changes. Props in, markup out. The one exception is Task 8, which is explicitly a behaviour change and is scoped to date rendering.
- **Every task ends green on all four gates:** `composer test`, `npm run check`, `npm run types:check`, `npm run build`.
- **Commit messages** end with `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`.

## Conversion vocabulary

Every task applies the same substitutions. They are listed once here rather than repeated per task.

| Old | New |
|---|---|
| `bg-white` on a card | `<Card>` (or `bg-surface` + `border-hairline` where a bare div is right) |
| `bg-gray-50` page background | nothing — `AppShell` already paints `bg-canvas` |
| `bg-gray-800 hover:bg-gray-700` button | `<Button>` |
| `border-red-300 text-red-600` button | `<Button variant="destructive">` |
| `border-gray-300` outline button | `<Button variant="secondary">` |
| `text-gray-900` / `text-gray-800` | `text-content` |
| `text-gray-600` / `text-gray-500` | `text-content-secondary` |
| `text-gray-400` | `text-content-tertiary` |
| `border-gray-100/200/300` | `border-hairline` |
| `text-xs` | `text-caption1` |
| `text-sm` | `text-footnote` |
| `text-base` | `text-body` |
| `text-lg` / `text-xl` heading | `text-headline` / `text-title3` |
| `rounded-md` / `rounded-lg` | `rounded-control` / `rounded-card` |
| label + input + error triple | `<Field label error>` wrapping `<Input>` |
| inline Headless UI `Dialog` | `<Modal>` |
| `window.confirm(...)` | `<Modal>` with a `destructive` confirm button |
| hand-rolled flash toast block | delete it — `AppShell` already mounts `<Toaster>` via `useFlashToasts` |
| `@/components/PrimaryButton` etc. | `@/components/ui` |

Import primitives from the barrel: `import { Button, Card, Field, Input } from '@/components/ui';`

---

### Task 1: Auth pages and the guest layout

Six small pages plus the shell they sit in. They share one structure, so converting them together is one reviewable unit. This is also the first screen anyone ever sees.

**Files:**
- Modify: `resources/js/layouts/GuestLayout.tsx`
- Modify: `resources/js/pages/Auth/Login.tsx` (106 lines)
- Modify: `resources/js/pages/Auth/Register.tsx` (121)
- Modify: `resources/js/pages/Auth/ForgotPassword.tsx` (60)
- Modify: `resources/js/pages/Auth/ResetPassword.tsx` (100)
- Modify: `resources/js/pages/Auth/ConfirmPassword.tsx` (56)
- Modify: `resources/js/pages/Auth/VerifyEmail.tsx` (55)
- Test: `tests/Feature/Auth/AuthenticationTest.php`, `RegistrationTest.php`, `PasswordResetTest.php`, `PasswordConfirmationTest.php`, `EmailVerificationTest.php` (all existing — do not rewrite them)

**Interfaces:**
- Consumes: `Button`, `Card`, `Field`, `Input`, `Checkbox` from `@/components/ui`.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Run the auth tests and record the baseline**

Run: `php artisan test --filter=Auth`
Expected: PASS. These assert redirects and session state, not markup, so they must stay green through the conversion. If any fail before you start, stop and report — you are not looking at a clean base.

- [ ] **Step 2: Convert `GuestLayout.tsx`**

It currently centres a white card on `bg-gray-100`. The paper equivalent:

```tsx
export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="bg-canvas flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <Link href="/" className="mb-6">
                <ApplicationLogo className="text-content h-14 w-14 fill-current" />
            </Link>
            <Card material="thin" className="w-full sm:max-w-md">
                {children}
            </Card>
        </div>
    );
}
```

- [ ] **Step 3: Convert `Login.tsx`**

Replace the `InputLabel` + `TextInput` + `InputError` triples with `Field` + `Input`, and `PrimaryButton` with `Button`. The email field becomes:

```tsx
<Field label="Email" error={errors.email} required>
    <Input
        type="email"
        value={data.email}
        onChange={(e) => setData('email', e.target.value)}
        invalid={Boolean(errors.email)}
        autoComplete="username"
        required
        autoFocus
    />
</Field>
```

Keep every `name`, `autoComplete`, `required` and `autoFocus` attribute exactly as it is — the auth tests post real credentials and browsers rely on these.

The "Remember me" checkbox becomes `<Checkbox checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} label="Remember me" />`.

The submit becomes `<Button type="submit" loading={processing}>Log in</Button>`.

- [ ] **Step 4: Convert the remaining five auth pages**

Same `Field` + `Input` + `Button` substitution as Step 3. What differs:

- **`Register.tsx`** — four fields (name, email, password, confirmation) and a "Already registered?" `Link`. Keep `autoComplete="new-password"` on both password fields.
- **`ForgotPassword.tsx`** — one email field, plus a status paragraph above the form when `status` is set: `<p className="text-success text-footnote">{status}</p>`.
- **`ResetPassword.tsx`** — email (readonly, populated from the token), password, confirmation. The hidden `token` input stays exactly as it is.
- **`ConfirmPassword.tsx`** — one password field with `autoComplete="current-password"`.
- **`VerifyEmail.tsx`** — no fields. A paragraph, a `<Button>` to resend, and a log-out `Link`. The "A new verification link has been sent" message becomes `<p className="text-success text-footnote">`.

- [ ] **Step 5: Run the auth tests**

Run: `php artisan test --filter=Auth`
Expected: PASS, same count as Step 1.

- [ ] **Step 6: Run all four gates**

```
composer test
npm run check
npm run types:check
npm run build
```
Expected: all pass.

- [ ] **Step 7: Visual check**

Open `/login`, `/register`, `/forgot-password` in both appearances (switch from the avatar menu once logged in, or toggle your OS setting). Confirm: cream canvas, no stray white-on-cream cards, no cool grey text.

- [ ] **Step 8: Commit**

```bash
git add resources/js/layouts/GuestLayout.tsx resources/js/pages/Auth
git commit -m "$(cat <<'EOF'
refactor(ui): convert the auth pages to the paper design system

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Profile

**Files:**
- Modify: `resources/js/pages/Profile/Edit.tsx` (58)
- Modify: `resources/js/pages/Profile/Partials/UpdateProfileInformationForm.tsx` (280)
- Modify: `resources/js/pages/Profile/Partials/UpdatePasswordForm.tsx` (147)
- Modify: `resources/js/pages/Profile/Partials/DeleteUserForm.tsx` (127)
- Test: `tests/Feature/ProfileTest.php`, `tests/Feature/UserPreferencesTest.php` (existing)

**Interfaces:**
- Consumes: `Button`, `Card`, `Field`, `Input`, `Select`, `Modal`, `PageHeader` from `@/components/ui`.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Run the profile tests**

Run: `php artisan test --filter="Profile|UserPreferences"`
Expected: PASS. `UserPreferencesTest` asserts the timezone options prop shape and the appearance endpoint — do not change either.

- [ ] **Step 2: Convert `Edit.tsx` to three cards**

Each partial gets its own `<Card>`; the page keeps its `.layout` assignment unchanged.

- [ ] **Step 3: Convert `UpdateProfileInformationForm.tsx`**

The grouped timezone `<select>` becomes:

```tsx
<Field
    label="Timezone"
    hint="Times are stored in UTC and shown in this zone, including in calendar feeds you subscribe to."
    error={errors.timezone}
>
    <Select
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
    </Select>
</Field>
```

Keep the browser-zone mismatch banner and its one-click update — it is real behaviour, not decoration. Restyle it to `bg-accent-soft text-content rounded-card p-3 text-footnote`.

Delete the local `SELECT_CLASS` constant; `Select` owns that styling now.

- [ ] **Step 4: Convert `UpdatePasswordForm.tsx`**

Three `Field` + `Input type="password"` blocks and a `Button`.

- [ ] **Step 5: Convert `DeleteUserForm.tsx`**

It already uses the orphaned `@/components/Modal`. Swap to `@/components/ui`'s `Modal`, and make the confirm `<Button variant="destructive">`.

- [ ] **Step 6: Run the profile tests, then all four gates**

Run: `php artisan test --filter="Profile|UserPreferences"` then the four gates.
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/Profile
git commit -m "$(cat <<'EOF'
refactor(ui): convert the profile pages to the paper design system

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Groups

`Groups/Show.tsx` is 476 lines and carries two dialogs (add member, bulk add) plus a members table.

**Files:**
- Modify: `resources/js/pages/Groups/Index.tsx` (122)
- Modify: `resources/js/pages/Groups/Create.tsx` (105)
- Modify: `resources/js/pages/Groups/Show.tsx` (476)
- Test: `tests/Feature/GroupTest.php`, `tests/Feature/BulkGroupMembershipTest.php` (existing)

**Interfaces:**
- Consumes: `Badge`, `Button`, `Card`, `EmptyState`, `Field`, `Input`, `Modal`, `PageHeader`, `Textarea`, `Avatar` from `@/components/ui`.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Run the group tests**

Run: `php artisan test --filter="Group"`
Expected: PASS.

- [ ] **Step 2: Convert `Groups/Index.tsx`**

The dashed-border empty state becomes `<EmptyState icon={Users} title="No groups yet" description="..." action={<Button icon={Plus}>New group</Button>} />`.

**Delete the hand-rolled toast block** — the `showToast` state, its `useEffect`, and the fixed-position div. `AppShell` mounts `<Toaster>` and `useFlashToasts` already reads the same `flash` prop.

- [ ] **Step 3: Convert `Groups/Create.tsx`**

One `Card` with `Field` + `Input` for name and `Field` + `Textarea` for description.

- [ ] **Step 4: Convert `Groups/Show.tsx`**

- Calendars list and members list each become a `<Card padded={false}>` with a `divide-hairline divide-y` body.
- The `bg-amber-50 text-amber-700` role pill becomes `<Badge tone="warning">Admin</Badge>`; member becomes `<Badge>Member</Badge>`.
- Member rows gain `<Avatar name={member.name} size="sm" />`.
- Both dialogs become `<Modal>`.
- Replace `window.confirm` on member removal with a `<Modal>` holding a `destructive` confirm.
- Delete the toast block here too.

Note the loose local `Calendar` interface with `name?`, `title?` and an index signature. Tighten it to `{ id: number; name: string; color: string | null }` — the controller sends full models, and `calendar.title` is dead code referencing a column that does not exist. If that breaks the build, report it rather than reinstating the index signature.

- [ ] **Step 5: Run the group tests, then all four gates**

Expected: all pass.

- [ ] **Step 6: Visual check**

Open a group page. Confirm the members table, both dialogs and the role badges read correctly in both appearances.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/Groups
git commit -m "$(cat <<'EOF'
refactor(ui): convert the group pages to the paper design system

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Calendar list pages

**Files:**
- Modify: `resources/js/pages/Calendars/Index.tsx` (356)
- Modify: `resources/js/pages/Calendars/Overview.tsx` (104)
- Test: `tests/Feature/CalendarControllerTest.php`, `tests/Feature/CalendarsOverviewTest.php` (existing)

**Interfaces:**
- Consumes: `Button`, `Card`, `ColorSwatchPicker`, `EmptyState`, `Field`, `Input`, `Modal`, `Textarea` from `@/components/ui`.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Run the calendar tests**

Run: `php artisan test --filter="Calendar"`
Expected: PASS. `CalendarsOverviewTest` pins that a personal calendar arrives with a null group — the page must keep handling that.

- [ ] **Step 2: Convert `Calendars/Index.tsx`**

The create/edit dialog becomes `<Modal>`. **Replace the raw hex text input with `<ColorSwatchPicker value={data.color} onChange={(v) => setData('color', v)} label="Calendar colour" />`** — the primitive exists and offers the twelve system colours, which is what the event pills derive their gradients from.

Delete the toast block.

- [ ] **Step 3: Convert `Calendars/Overview.tsx`**

Small. Keep `calendarHref()` and the `calendar.group?.name ?? 'Personal'` badge exactly as they are — that is a crash fix, not styling. The badge becomes `<Badge>`.

- [ ] **Step 4: Run the calendar tests, then all four gates**

Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/Calendars/Index.tsx resources/js/pages/Calendars/Overview.tsx
git commit -m "$(cat <<'EOF'
refactor(ui): convert the calendar list pages to the paper design system

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Extract the shared event dialog

`Events/Index.tsx` (604) and `Calendars/Personal.tsx` (532) contain near-identical event create/edit dialogs — the same fields, the same recurrence editor, the same scope prompt, the same `toLocalInputValue` helper. `Dashboard.tsx` has a third copy of the fields. Converting all three separately would triple the work and leave three copies to drift.

Extract first, convert once.

**Files:**
- Create: `resources/js/components/Calendar/EventDialog.tsx`
- Create: `resources/js/components/Calendar/useEventForm.ts`
- Modify: `resources/js/types/calendar.ts` (move `WritableCalendar` here)
- Modify: `resources/js/pages/Events/Index.tsx`
- Modify: `resources/js/pages/Calendars/Personal.tsx`
- Test: `tests/Feature/EventControllerTest.php`, `tests/Feature/RecurringEventTest.php`, `tests/Feature/PersonalCalendarTest.php` (existing)

**Interfaces:**
- Consumes: `Button`, `Field`, `Input`, `Modal`, `Select`, `Textarea`, `Checkbox` from `@/components/ui`; `RecurrenceEditor` and `RecurrenceScopeDialog` from `@/components/Calendar`.
- Produces:
  ```ts
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
      scope: RecurrenceScope;
      occurrence_start: string;
  }

  export function useEventForm(defaults?: Partial<EventFormData>): {
      form: InertiaFormProps<EventFormData>;
      openCreate: (date: Date, visibility?: Visibility) => void;
      openEdit: (occurrence: Occurrence) => void;
      reset: () => void;
  };

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
  export default function EventDialog(props: EventDialogProps): JSX.Element;

  /** Exported so Dashboard can drop its private copy. */
  export function toLocalInputValue(date: Date): string;
  ```

- [ ] **Step 1: Run the event tests and record the counts**

Run: `php artisan test --filter="Event|Recurring|PersonalCalendar"`
Expected: PASS. Write the numbers down — this task must not change them.

- [ ] **Step 2: Move `WritableCalendar` into the shared types**

It is currently a local interface in `Dashboard.tsx`, and `EventDialogProps`
below refers to it. Cut it to `resources/js/types/calendar.ts` verbatim and
import it in both places:

```ts
/** One calendar the viewer may create an event on, as sent by WritableCalendars. */
export interface WritableCalendar {
    id: number;
    name: string;
    color: string | null;
    type: string;
    group_id: number | null;
    group_name: string | null;
    /** Which existing endpoint a create posts to. */
    create_url: string;
}
```

`RecurrenceScope` is already exported from
`resources/js/components/Calendar/RecurrenceScopeDialog.tsx` — import it from
there rather than redeclaring it.

- [ ] **Step 3: Create `useEventForm.ts`**

Move `toLocalInputValue` and the form-shaping logic out of `Events/Index.tsx` verbatim. Keep the midnight-to-09:00 defaulting and the `occurrence_start: full.recurrence_id ?? full.starts_at` line — both carry real behaviour and comments explaining why.

- [ ] **Step 4: Create `EventDialog.tsx`**

Lift the dialog markup out of `Events/Index.tsx`, converting it to `Modal` + `Field` + `Input` as you go. It renders the calendar picker only when `calendars` is passed. Keep `RecurrenceEditor` and `RecurrenceScopeDialog` mounted exactly as they are today.

- [ ] **Step 5: Rewire `Events/Index.tsx` to use both**

The page keeps its month/day state, its grid, its toolbar and its submit handlers. It loses ~300 lines of dialog markup.

- [ ] **Step 6: Run the event tests**

Run: `php artisan test --filter="Event|Recurring|PersonalCalendar"`
Expected: PASS with the same counts as Step 1.

- [ ] **Step 7: Rewire `Calendars/Personal.tsx` to use both**

It passes no `calendars` prop, so no picker renders — its target is always the viewer's own calendar. Default visibility stays `private`.

- [ ] **Step 8: Run all four gates**

Expected: all pass.

- [ ] **Step 9: Manual check of the recurrence flow**

Create a weekly event, edit one occurrence, choose "This event" in the scope prompt, confirm only that occurrence changed. This path has no automated frontend coverage, so it must be exercised by hand.

- [ ] **Step 10: Commit**

```bash
git add resources/js/types/calendar.ts resources/js/components/Calendar resources/js/pages/Events/Index.tsx resources/js/pages/Calendars/Personal.tsx
git commit -m "$(cat <<'EOF'
refactor(ui): extract the shared event dialog and convert the calendar pages

Events/Index and Calendars/Personal carried near-identical dialogs - the
same fields, recurrence editor and scope prompt - and Dashboard a third
copy of the fields. Converting each separately would have tripled the
work and left three copies to drift.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Calendar page chrome

With the dialogs extracted, what remains on `Events/Index.tsx` and `Calendars/Personal.tsx` is the toolbar, the grid and the day view. Convert those, and make `Dashboard.tsx` use the shared dialog too.

**Files:**
- Modify: `resources/js/pages/Events/Index.tsx`
- Modify: `resources/js/pages/Calendars/Personal.tsx`
- Modify: `resources/js/pages/Dashboard.tsx`
- Modify: `resources/js/components/Calendar/DayView.tsx` (234)
- Test: `tests/Feature/DashboardCalendarTest.php`, `tests/Feature/EventControllerTest.php` (existing)

**Interfaces:**
- Consumes: `Button`, `SegmentedControl`, `Card` from `@/components/ui`; `EventDialog` and `useEventForm` from Task 5.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Convert the month/prev/next toolbars**

Both pages hand-roll three bordered buttons. Replace with `<Button variant="ghost" size="icon">` carrying `ChevronLeft` / `ChevronRight` from lucide, matching what `Dashboard.tsx` already does. Copy that block rather than inventing a second arrangement.

- [ ] **Step 2: Convert `DayView.tsx` to tokens**

It still uses `bg-gray-50`, `border-red-300/bg-red-100` for conflicts and `border-indigo-200/bg-indigo-100` otherwise. Replace with `bg-surface`, `border-danger`, and `EventPill` for the blocks themselves — `EventPill` already handles per-calendar colour and the redacted case, and `DayView` passes absolute positioning through its `style` prop.

**Leave `layoutDayEvents()` alone.** The overlap algorithm is correct and is not a styling concern.

- [ ] **Step 3: Make the now-line tick**

It currently computes `nowOffset` once at render and then freezes. Add:

```tsx
const [now, setNow] = useState(() => new Date());

useEffect(() => {
    const id = setInterval(() => setNow(new Date()), 60_000);

    return () => clearInterval(id);
}, []);
```

and derive the offset from `now`. Colour it `bg-today` — on a paper calendar the red line and the red date badge are the same idea.

- [ ] **Step 4: Point `Dashboard.tsx` at the shared dialog**

Delete its private `EventFields` component and `toLocalInputValue`, and use `EventDialog` + `useEventForm` instead. Its calendar picker is passed through as the `calendars` prop.

- [ ] **Step 5: Run all four gates**

Expected: all pass, `DashboardCalendarTest` included.

- [ ] **Step 6: Visual check**

Month grid, day view and the create dialog from all three pages, in both appearances. Confirm event pills take their calendar's colour and a redacted event reads as a flat "Busy" block.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages resources/js/components/Calendar
git commit -m "$(cat <<'EOF'
refactor(ui): convert the calendar views to the paper design system

Also makes the day view's now-line tick; it previously computed its
offset once at render and then froze.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: The landing page

**Files:**
- Modify: `resources/js/pages/welcome.tsx` (144)
- Test: none exists; add one.

**Interfaces:**
- Consumes: `Button`, `LinkButton`, `Card` from `@/components/ui`.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Write the failing test**

`tests/Feature/WelcomeTest.php`:

```php
<?php

use Inertia\Testing\AssertableInertia;

test('the landing page renders for a guest', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('welcome')
            ->has('canLogin')
            ->has('canRegister')
        );
});
```

- [ ] **Step 2: Run it**

Run: `php artisan test --filter=Welcome`
Expected: PASS — the route already exists. This is a regression guard for the conversion, not a new feature.

- [ ] **Step 3: Convert the page**

Drop the `bg-gradient-to-br from-blue-50 to-indigo-100` hero — it predates the design system and fights the paper canvas. Use `bg-canvas` with a `<Card>` for the feature grid and `LinkButton` for the log-in / register calls to action.

- [ ] **Step 4: Run the test, then all four gates**

Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/welcome.tsx tests/Feature/WelcomeTest.php
git commit -m "$(cat <<'EOF'
refactor(ui): convert the landing page to the paper design system

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 8: Render dates in the viewer's timezone

Not styling — a correctness gap, and a prerequisite for the ICS feed phase. `users.timezone` has been stored and shared since the timezone phase, but only the profile form reads it. Every grid still does its date maths in raw browser-local `new Date()`, and `resources/js/lib/datetime.ts` was built for this and is imported by nothing.

**Files:**
- Modify: `resources/js/lib/datetime.ts` (add what is missing; do not rewrite what is there)
- Modify: `resources/js/components/Calendar/MonthGrid.tsx`
- Modify: `resources/js/components/Calendar/DayView.tsx`
- Modify: `resources/js/pages/Dashboard.tsx`
- Test: `tests/Feature/DashboardCalendarTest.php` (existing)

**Interfaces:**
- Consumes: `viewer.timezone` and `viewer.week_starts_on` from `usePageProps()`; `TZDate` from `@date-fns/tz`.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Read `lib/datetime.ts` and confirm what it already exports**

It was written for exactly this and never wired up. Extend it rather than starting over.

- [ ] **Step 2: Add a `useViewerZone()` hook**

```ts
export function useViewerZone(): string {
    const { viewer } = usePageProps();

    return viewer.timezone;
}
```

- [ ] **Step 3: Thread the zone into `MonthGrid`**

Take `timezone` and `weekStartsOn` as props rather than reading the page inside a presentational component. `startOfCalendar()` and `isSameDay()` must compare in the viewer's zone, using `TZDate`, not in browser-local time.

`weekStartsOn` also means `WEEKDAY_LABELS` can no longer be a hardcoded Sunday-first array — rotate it.

- [ ] **Step 4: Thread the zone into `DayView`**

`hoursSinceMidnight()` is the one that matters: "midnight" must mean midnight in the viewer's zone.

- [ ] **Step 5: Pass the zone from all three calendar pages**

Dashboard, Events/Index, Calendars/Personal.

- [ ] **Step 6: Manual verification — this is the one that proves it**

Set your profile timezone to `Pacific/Auckland`, create an event, and confirm it appears at the hour you typed rather than shifted by your browser's offset. Then set it to `America/Los_Angeles` and confirm the same event moves in the grid without its stored time changing.

- [ ] **Step 7: Run all four gates**

Expected: all pass.

- [ ] **Step 8: Commit**

```bash
git add resources/js/lib/datetime.ts resources/js/components/Calendar resources/js/pages
git commit -m "$(cat <<'EOF'
feat(calendar): render dates in the viewer's timezone

users.timezone has been stored and shared since the preferences phase,
but only the profile form read it - every grid did its date maths in raw
browser-local time, so a user whose profile zone differed from their
device saw events at the wrong hour. lib/datetime.ts was built for this
and had never been wired up.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 9: Delete the legacy bridge and the old primitives

Only safe once nothing depends on them. This task is the proof that the conversion is actually complete: if deleting the shim changes how any page looks, a page was still leaning on it.

**Files:**
- Modify: `resources/css/app.css` (delete the `LEGACY BRIDGE` block only)
- Delete: `resources/js/components/PrimaryButton.tsx`, `SecondaryButton.tsx`, `DangerButton.tsx`, `TextInput.tsx`, `InputLabel.tsx`, `InputError.tsx`, `Checkbox.tsx`, `Modal.tsx`
- Keep: `ApplicationLogo.tsx`, `NavLink.tsx`, `ResponsiveNavLink.tsx`, `Dropdown.tsx` **only if still imported** — check first

**Interfaces:**
- Consumes: nothing.
- Produces: nothing.

- [ ] **Step 1: Prove nothing imports the old primitives**

Run:
```bash
grep -rn "from '@/components/\(PrimaryButton\|SecondaryButton\|DangerButton\|TextInput\|InputLabel\|InputError\|Checkbox\|Modal\)'" resources/js/
```
Expected: no output. If anything matches, that page was missed — go back and convert it.

- [ ] **Step 2: Prove no page uses the bridged classes**

Run:
```bash
grep -rnE "bg-(white|gray-[0-9]+|indigo-[0-9]+|red-[0-9]+)|text-(gray|indigo|red|amber|green)-[0-9]+|border-(gray|indigo|red)-[0-9]+" resources/js/pages resources/js/layouts
```
Expected: no output. Anything that matches is still relying on the shim.

- [ ] **Step 3: Delete the `LEGACY BRIDGE` block from `app.css`**

Everything from the `LEGACY BRIDGE - temporary, delete with the last unconverted page` banner to the end of that section. Nothing above it changes.

- [ ] **Step 4: Delete the old primitive files**

Only the eight listed. Check `ApplicationLogo`, `NavLink`, `ResponsiveNavLink` and `Dropdown` for remaining imports before touching them — `GuestLayout` still uses `ApplicationLogo`.

- [ ] **Step 5: Run all four gates**

Expected: all pass. `npm run build` is the important one here — an unresolved import fails it outright.

- [ ] **Step 6: Visual sweep of every page**

Dashboard, Events, Personal, Calendars, Groups, Profile, Login, Welcome, Styleguide — both appearances. **Nothing should have changed appearance when the shim was deleted.** If something did, that page was still depending on it and the deletion has just exposed it.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "$(cat <<'EOF'
chore(ui): delete the legacy bridge and the pre-design-system primitives

The shim remapped Tailwind's default palette onto the design tokens so
the unconverted pages stayed coherent. With every page converted it has
nothing left to remap, and deleting it is the proof: if any page changed
appearance, it was still leaning on the shim.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

## Verification

After the final task:

```
composer test          # pint + phpstan level 7 + pest
npm run check
npm run types:check
npm run build
```

Confirm `public/hot` is absent before running tests, or page-render failures will be masked.

**End-to-end manual script:**

1. Log out. `/login` and `/register` render on cream paper with no stray cool greys.
2. Log in as a group admin. The dashboard shows a month grid across every calendar, with the agenda beside it.
3. Create an event from the grid, choosing a group calendar in the picker. It appears in the grid in that calendar's colour.
4. Create a second event, choosing your personal calendar. It defaults to private.
5. Log in as another admin of the same group. The private event reads "Busy" with no title, in a flat colourless block.
6. As a Super Admin, confirm you cannot edit the other user's personal event.
7. Make a weekly recurring event. Edit one occurrence with scope "This event"; only that occurrence changes.
8. Switch appearance from the avatar menu. Every page holds up in night paper.
9. Change your profile timezone. Events move in the grid; their stored times do not.

## Out of scope

The ICS feed, Google sync, the week view, drag-and-drop, multi-day event spanning in the month grid, and any change to `app.css` tokens or the `ui/` primitives. Multi-day spanning in particular is a real gap — the month grid still shows an event only on its start day — but it is a functional change, not a conversion, and belongs in its own plan.
