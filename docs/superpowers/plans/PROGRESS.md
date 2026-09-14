# Progress

Live execution state. **Read this first when resuming** — sessions have been
cut off by rate limits three times, and this is the only place the current task
position is recorded.

Update the checkbox and the "Resume here" line as part of each task's commit,
so the state ships with the work rather than trailing it.

---

## Resume here

> **The paper UI conversion plan is fully done - all 9 tasks complete, the
> legacy bridge and pre-design-system primitives deleted.** This was the
> last plan queued up; there is no automatic next task. See "Still to come"
> below for what's next (ICS feed is now unblocked - Task 8 landed).
>
> Branch: `phase/7-landing-page` (name is stale - it ended up carrying
> Tasks 7, 8 and 9). Not yet merged or PR'd. All four gates green
> (312 tests, pint, phpstan clean, build succeeds).
>
> **Task 8 turned out to need more than its own file list said.** Its
> plan only listed `lib/datetime.ts`, `MonthGrid.tsx`, `DayView.tsx`,
> `Dashboard.tsx` - but `MonthGrid`/`DayView` now hand back viewer-zone-aware
> `TZDate` instances through their click callbacks, and `useEventForm.ts`
> was re-wrapping those in a plain `new Date(...)` before use, which would
> have silently discarded the zone. Fixed `useEventForm`, and while there,
> found and fixed a deeper pre-existing bug: create/edit submissions sent a
> bare "2026-09-20T09:00" string the backend parsed in the app's own UTC
> timezone rather than the viewer's, so two people creating an event at
> "9am" in different zones both landed on 9am UTC. Both are fixed now
> (`localInputValueToUtcIso()` / `withUtcOffsets()` in `lib/datetime.ts` /
> `useEventForm.ts`), verified against Carbon directly (see that commit),
> but not yet clicked through in an actual browser.
>
> **Task 9's own grep for bridged classes only checked `pages/`and
> `layouts/`, missing `RecurrenceEditor.tsx` and `RecurrenceScopeDialog.tsx`**
> under `components/Calendar/` - both still had raw indigo/gray Tailwind
> classes. Converted both to the design system before deleting the bridge,
> or they'd have reverted to stock Tailwind colors. Also deleted
> `NavLink.tsx`, `ResponsiveNavLink.tsx`, `Dropdown.tsx` (the plan's "keep
> only if imported" list) - none had any remaining imports.
>
> **Manual browser checks outstanding, none performed - no browser
> automation is available in this session:**
> 1. Task 5's Step 9: edit one occurrence of a recurring event, choose
>    "This event" in the scope prompt, confirm only that occurrence changed.
> 2. Task 6's Step 6: month grid, day view and the create dialog on all
>    three pages (Events/Index, Personal, Dashboard), in both appearances -
>    confirm event pills take their calendar's colour and a redacted event
>    reads as a flat "Busy" block.
> 3. Task 8's Step 6: set your profile timezone to `Pacific/Auckland`,
>    create an event, confirm it appears at the hour you typed. Then switch
>    to `America/Los_Angeles` and confirm the same event moves in the grid
>    without its stored time changing. This is the one that actually proves
>    the write-path fix above works outside a `tinker` check.
> 4. Task 9's Step 6: visual sweep of every page (Dashboard, Events,
>    Personal, Calendars, Groups, Profile, Login, Welcome, Styleguide) in
>    both appearances - nothing should look different now that the legacy
>    bridge is gone. If something does, that page was still leaning on it.
>
> All four are covered at the HTTP/component level by existing tests and
> were verified by static code review, but nobody has clicked through any
> of them in a real browser since these changes landed. Do all four by hand
> before treating this branch as production-ready.
>
> **Separately, from the earlier team-busy-visibility/messaging work
> (merged via PR #11):** the "Message" button's `messagedIds` is local
> React state, so a "Messaged" row reverts to "Message" on page reload even
> though the server-side dedup still silently blocks a second send. Not
> blocking - the dedup is enforced either way - but worth fixing.
>
> **Task 6 also gave Dashboard's dialog recurrence editing and a scope
> prompt it never had before** - its old hand-rolled fields had no
> RecurrenceEditor at all, so switching to the shared EventDialog is a real
> (intentional, but beyond that plan's literal wording) capability change:
> editing a recurring event from the dashboard agenda can now change the
> whole series, and now asks which occurrences first, matching the other
> two pages. Flagged in case that capability change wasn't wanted on the
> dashboard specifically.

---

## Plan being executed

`docs/superpowers/plans/2026-09-14-paper-ui-conversion.md` - **complete.**

| #   | Task                                            | State     |
| --- | ------------------------------------------------ | --------- |
| 1   | Auth pages and the guest layout                 | [x] done  |
| 2   | Profile                                         | [x] done  |
| 3   | Groups                                          | [x] done  |
| 4   | Calendar list pages                             | [x] done  |
| 5   | Extract the shared event dialog                 | [x] done* |
| 6   | Calendar page chrome                            | [x] done* |
| 7   | The landing page                                | [x] done  |
| 8   | Render dates in the viewer's timezone           | [x] done* |
| 9   | Delete the legacy bridge and the old primitives | [x] done* |

\* Task 5, 6, 8 and 9: manual browser checks still outstanding - see above.

---

## Done and merged to `dev`

| Phase | What                                                                                               |
| ----- | -------------------------------------------------------------------------------------------------- |
| 0     | Seams — enums, config, observer, shared props. Fixed dead `flash` toasts and duplicate middleware. |
| 1     | Vite/TS consolidation. Fixed a genuinely broken production build (clean checkout was 67/92).       |
| 2     | Per-user timezone, theme, week start, time format + `PATCH /profile/appearance`.                   |
| 3     | `events.visibility`, personal calendars, the `EventRedactor` chokepoint. Closed two real leaks.    |
| 4     | Recurrence — RRULE storage, DST-correct expansion, overrides, this/following/all editing.          |
| 5     | Design system foundation — tokens, materials, 22 primitives, `AppShell`, `/styleguide`.            |
| —     | `fix/npm-lockfile`, `fix/calendars-overview-null-group`.                                           |
| —     | Paper theme, dashboard month calendar + side agenda, calendar picker, Super Admin write fix.       |
| 6     | Calendar page chrome — `Events/Index`, `Calendars/Personal`, `Dashboard`, `DayView` converted.     |
| —     | Team busy-visibility, scoped conflict detection, team-busy panel, member-to-admin messaging.       |

Test count at the last green run: **311 passing**.

---

## Still to come

- **ICS feed** — per-user secret-token URL, `sabre/vobject` with real
  VTIMEZONE, ETag/304 caching, a "Subscribed devices" revoke list. Was
  blocked on viewer timezone rendering; unblocked once `phase/7-landing-page`
  (Task 8) merges to `dev`.
- **Google two-way sync** — six incremental steps, OAuth through to the
  conflict audit UI. Against the REST API via the `Http` facade, because
  neither `google/apiclient` nor `laravel/socialite` supports Guzzle 8.
  Google's calendar scopes are _sensitive_, so OAuth verification takes weeks —
  worth starting the application early.

## Known gaps, deliberately deferred

- **The month grid shows an event only on its start day** — no multi-day
  spanning. A functional change rather than a conversion, so it needs its own
  plan.
- `GroupController@show` ships raw Eloquent models to Inertia.
- `types/auth.ts`'s `User` has an index signature that quietly defeats
  type-checking on user fields.
- **Open question for the user:** whether to add `@php artisan migrate
--graceful` to the `dev` composer script. `composer dev` does not migrate,
  and that has broken their local app twice after a merge.

---

## Before trusting any test run

```
composer test          # pint + phpstan (needs --memory-limit=1G) + pest
npm run check
npm run types:check
npm run build
```

`public/hot` must be **absent** — while it exists Laravel serves from the Vite
dev server and never reads the build manifest, which hides page-render
failures. Run `php`, `composer` and `npm` from PowerShell, not Git Bash.
