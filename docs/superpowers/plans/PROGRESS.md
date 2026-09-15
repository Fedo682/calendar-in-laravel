# Progress

Live execution state. **Read this first when resuming** — sessions have been
cut off by rate limits three times, and this is the only place the current task
position is recorded.

Update the checkbox and the "Resume here" line as part of each task's commit,
so the state ships with the work rather than trailing it.

---

## Resume here

> **The ICS subscription feed** (spec: `docs/superpowers/specs/2026-09-15-ics-feed-design.md`,
> plan: `docs/superpowers/plans/2026-09-15-ics-feed.md`) **is implemented,
> all 7 tasks done, all four gates green (341 tests).** Branch:
> `feature/ics-feed`, cut from `dev`. Not yet merged or PR'd.
>
> A `calendar_feed_tokens` table + `CalendarFeedTokenService` back a
> `GET /feed/{token}.ics` route (the app's first unauthenticated route -
> stripped of session/CSRF/Inertia middleware) serving a `VCALENDAR` built by
> `IcsFeedBuilder` from `OccurrenceQuery::mastersFor()` - already redacted,
> already unexpanded. An ETag fingerprint (`IcsFeedCache`) 304s a repeat poll
> without touching vobject. `Settings/Integrations.tsx` (new, linked from the
> avatar menu) lets a user generate/copy/revoke their feed URL.
>
> **Two things corrected while building this:**
> 1. The original roadmap assumed `sabre/vobject`'s `TimeZoneUtil` generates
>    VTIMEZONE blocks - it doesn't, it only parses ones a client already
>    produced. This feed uses bare IANA `TZID` references instead (no
>    embedded VTIMEZONE) for recurring events; a real generator is deferred.
> 2. `RedactedEvent::uid()` used to always embed the row's own id - for an
>    override row that's a different id than its series, which would have
>    broken a calendar client's ability to associate the override with its
>    master. Fixed to use `recurrenceParentId ?? id`.
>
> **Manual check outstanding, not performed - no device access this
> session:** subscribe from an actual iPhone (Settings → Calendar →
> Accounts → Add Subscribed Calendar, or tap the `webcal://` link from
> Integrations) and confirm events land at the correct hour and another
> user's private event shows as an untitled "Busy" block.
>
> **Once merged:** Google two-way sync is the only roadmap item left - see
> "Still to come".
>
> A project README also landed on its own branch (`docs/readme`, pushed,
> not yet merged) - unrelated to any phase, just repo documentation.

---

## Manual browser/device checks still outstanding

None of these can be performed in this session (no browser or device
access). Covered at the HTTP/component level by existing tests and
verified by static review, but nobody has clicked through any of them
since the change landed - do all of these by hand before treating `dev`
as production-ready:

1. **Recurrence scope prompt** (paper UI conversion, Task 5): edit one
   occurrence of a recurring event, choose "This event," confirm only that
   occurrence changed.
2. **Visual check of the three converted calendar pages** (Task 6): month
   grid, day view, create dialog on Events/Index, Personal, Dashboard, in
   both appearances - event pills take their calendar's colour, a redacted
   event reads as a flat "Busy" block.
3. **Viewer-timezone check** (Task 8): set your profile timezone to
   `Pacific/Auckland`, create an event, confirm it appears at the hour you
   typed. Switch to `America/Los_Angeles`, confirm the same event moves in
   the grid without its stored time changing. This is the one that proves
   the create/edit write-path fix works outside a `tinker` check.
4. **Full visual sweep** (Task 9, legacy bridge deletion): every page, both
   appearances - nothing should look different now that the bridge is gone.
5. **Group Messages inbox**: click "Message" on a dashboard agenda row for
   a group event you can't edit, send it, confirm it becomes "Messaged."
6. **ICS feed**: see "Resume here" above.

## Known gaps, deliberately deferred

- The dashboard's "Message" button state (`messagedIds`) is local React
  state, so a "Messaged" row reverts to "Message" on page reload even
  though the server-side dedup still blocks a second send either way. Not
  blocking, worth fixing.
- **The month grid shows an event only on its start day** — no multi-day
  spanning. A functional change, not a conversion - needs its own plan.
- `GroupController@show` ships raw Eloquent models to Inertia.
- `types/auth.ts`'s `User` has an index signature that quietly defeats
  type-checking on user fields.
- A full generated VTIMEZONE component for the ICS feed (see "Resume
  here").
- **Open question for the user:** whether to add `@php artisan migrate
  --graceful` to the `dev` composer script. `composer dev` does not
  migrate, and that has broken the local app twice after a merge.

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
| —     | Team busy-visibility, scoped conflict detection, team-busy panel, member-to-admin messaging (PR #11). |
| 7-9   | Landing page, viewer-timezone rendering (grid + write path), legacy bridge + primitives deleted (PR #12). |
| —     | Group admin Messages/Reports inbox, `resolved_at` toggle (PR #13).                                  |

Test count at the last green run on `dev`: **318 passing**. (`feature/ics-feed` adds 23 more, not yet merged.)

---

## Still to come

- **Google two-way sync** — six incremental steps, OAuth through to the
  conflict audit UI. Against the REST API via the `Http` facade, because
  neither `google/apiclient` nor `laravel/socialite` supports Guzzle 8.
  Google's calendar scopes are _sensitive_, so OAuth verification takes weeks —
  worth starting the application early.

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
