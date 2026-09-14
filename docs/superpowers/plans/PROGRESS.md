# Progress

Live execution state. **Read this first when resuming** — sessions have been
cut off by rate limits three times, and this is the only place the current task
position is recorded.

Update the checkbox and the "Resume here" line as part of each task's commit,
so the state ships with the work rather than trailing it.

---

## Resume here

> **Next:** Task 7 of the paper UI conversion — the landing page.
>
> **Two manual browser checks are still outstanding, neither performed** - no
> browser automation is available in this session:
> 1. Task 5's Step 9: edit one occurrence of a recurring event, choose "This
>    event" in the scope prompt, confirm only that occurrence changed.
> 2. Task 6's Step 6: month grid, day view and the create dialog on all three
>    pages (Events/Index, Personal, Dashboard), in both appearances - confirm
>    event pills take their calendar's colour and a redacted event reads as a
>    flat "Busy" block.
>
> Both are covered at the HTTP/component level by existing tests and were
> verified by static code review, but nobody has clicked through either in an
> actual browser since these changes landed. Do both by hand before trusting
> this phase in production.
>
> **Task 6 also gave Dashboard's dialog recurrence editing and a scope prompt
> it never had before** - its old hand-rolled fields had no RecurrenceEditor
> at all, so switching to the shared EventDialog is a real (intentional, but
> beyond the plan's literal Step 4 wording) capability change: editing a
> recurring event from the dashboard agenda can now change the whole series,
> and now asks which occurrences first, matching the other two pages. Flagged
> here in case that capability change wasn't wanted on the dashboard
> specifically.
>
> Branch: `phase/6-ui-conversion`, cut fresh from `dev` (Tasks 1-5 and the
> paper theme are merged, so this branch no longer needs to stack on anything).

---

## Plan being executed

`docs/superpowers/plans/2026-09-14-paper-ui-conversion.md`

| #   | Task                                            | State           |
| --- | ----------------------------------------------- | --------------- |
| 1   | Auth pages and the guest layout                 | [x] done        |
| 2   | Profile                                         | [x] done        |
| 3   | Groups                                          | [x] done        |
| 4   | Calendar list pages                             | [x] done        |
| 5   | Extract the shared event dialog                 | [x] done*       |
| 6   | Calendar page chrome                             | [x] done*       |
| 7   | The landing page                                | [ ] not started |
| 8   | Render dates in the viewer's timezone           | [ ] not started |
| 9   | Delete the legacy bridge and the old primitives | [ ] not started |

\* Task 5 and Task 6: manual browser checks (Step 9 and Step 6 respectively) still outstanding - see above.

**Checkpoints already passed:** the pause after Task 4 (before Task 5 rebuilt
the calendar views), and the pause after Task 6 (both calendar pages and
Dashboard now share one converted chrome and one dialog) - the user said to
continue each time. The next natural pause is after Task 9, when the legacy
bridge is deleted and the conversion is fully done.

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

Test count at the last green run: **285 passing**.

---

## Still to come after this plan

- **ICS feed** — per-user secret-token URL, `sabre/vobject` with real
  VTIMEZONE, ETag/304 caching, a "Subscribed devices" revoke list.
  **Depends on Task 8** of the current plan (viewer timezone), or subscribers
  see events at the wrong hour.
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
