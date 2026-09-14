# Progress

Live execution state. **Read this first when resuming** — sessions have been
cut off by rate limits three times, and this is the only place the current task
position is recorded.

Update the checkbox and the "Resume here" line as part of each task's commit,
so the state ships with the work rather than trailing it.

---

## Resume here

> **Next:** Task 6 of the paper UI conversion — calendar page chrome
> (toolbar/grid conversion for Events/Index, Personal, and pointing Dashboard
> at the shared EventDialog).
>
> **Task 5's Step 9 (manual browser check of the recurrence scope flow) was
> NOT performed** — no browser automation is available in this session. The
> HTTP-level behaviour it would exercise is covered by RecurringEventTest
> (14/14) and RecurrenceEditorTest, and the wiring was verified by static
> review, but nobody has actually clicked through "edit one occurrence ->
> choose 'This event' -> confirm only that occurrence changed" in a real
> browser since the extraction. Do this by hand before treating Task 5 as
> fully closed.
>
> Branch: `phase/6-ui-conversion`, **pushed to origin** (not merged - just
> backed up, since this session was stopped proactively at 96% context rather
> than cut off by a rate limit). **Stacked on `feat/paper-theme-and-dashboard`,
> not on `dev`** — the conversion needs that branch's tokens and its Dashboard
> reference conversion, and it was still unmerged when this started. Rebase
> onto `dev` once the paper theme lands - check whether
> `feat/paper-theme-and-dashboard` has been merged first; if so, rebase this
> branch onto `dev` directly instead of staying stacked on a now-dead branch.

---

## Plan being executed

`docs/superpowers/plans/2026-09-14-paper-ui-conversion.md`

| #   | Task                                            | State           |
| --- | ----------------------------------------------- | --------------- |
| 1   | Auth pages and the guest layout                 | [x] done        |
| 2   | Profile                                         | [x] done        |
| 3   | Groups                                          | [x] done        |
| 4   | Calendar list pages                             | [x] done        |
| 5   | Extract the shared event dialog                 | [x] done*        |
| 6   | Calendar page chrome                            | [ ] not started |
| 7   | The landing page                                | [ ] not started |
| 8   | Render dates in the viewer's timezone           | [ ] not started |
| 9   | Delete the legacy bridge and the old primitives | [ ] not started |

\* Task 5: manual recurrence-flow check (its Step 9) still outstanding - see above.

**Checkpoint already passed:** the pause after Task 4 (before Task 5 rebuilt
the calendar views) happened and the user said to continue. The next natural
pause is after Task 6, once both calendar pages and Dashboard share one
converted chrome.

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
