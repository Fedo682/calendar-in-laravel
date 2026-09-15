# GroupSync Calendar

A multi-tenant group calendar built on Laravel and Inertia. Groups have
calendars, calendars have events, and every user also gets a personal
calendar of their own — private by default, showing as an opaque "Busy"
block to everyone else. The whole app is styled as a paper desk calendar:
a cream canvas, navy chrome, pencil-grey grid lines, and a night-paper dark
mode, built from scratch on Tailwind 4 rather than a component kit.

## What it does

- **Groups and roles.** A Super Admin creates groups and assigns group
  admins; group admins manage members and calendars; members see what
  they're entitled to and nothing more.
- **Group calendars and personal calendars.** Every group calendar is
  shared; every user also has exactly one personal calendar, created the
  first time they need it.
- **Recurring events.** Stored as raw RRULE strings (not a normalized
  schema — a normalized shape can't express `BYSETPOS` or rule sets, and
  every external format this app speaks, from ICS to Google's
  `recurrence[]` array, wants a raw RRULE anyway). Anchored to an explicit
  IANA timezone, so a 9am weekly standup stays at 9am across a DST
  transition instead of drifting by an hour. Editing or deleting supports
  the usual three scopes: this occurrence, this and following, or the
  whole series.
- **Visibility and redaction.** An event is `public`, `private`, or
  `busy`. Everything that turns an `Event` model into something a browser
  or a calendar client can see goes through one chokepoint
  (`EventRedactor`) — there is no query scope or policy check that can be
  forgotten on some other code path, because the leaky type never reaches
  a serializer in the first place.
- **Team busy-visibility.** A group admin sees their team's personal
  calendars as collapsed "Busy — Jane Smith" blocks, without ever seeing
  the real title of a private appointment. The Super Admin sees this across
  every group on the platform, grouped by team.
- **Conflict detection, scoped correctly.** The dashboard flags a real
  double-booking on your own calendars — not "any two events a Super Admin
  happens to be able to see," which is a materially different (and far
  noisier) question once one role can see the whole platform.
- **Member-to-admin messaging.** A member can report a scheduling conflict
  or send a free-text note about a team event; a group admin reviews both
  in a per-group Messages inbox, with a resolved/unresolved toggle and a
  link straight to the event.
- **Per-user timezone and appearance.** Every grid, agenda, and create/edit
  dialog renders — and writes — in the viewer's own IANA timezone, not the
  browser's. Theme (light/dark/system) and week-start day are per-user too.

## Tech stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | Inertia 3, React 19, TypeScript |
| Styling | Tailwind CSS 4 (a hand-built token/material design system — no component kit) |
| Database | MySQL/MariaDB in production, SQLite for the test suite |
| Recurrence | Raw RRULE strings, expanded with `rlanvin/php-rrule` |
| Calendar interop | `sabre/vobject` (parsing/serialization primitives for the ICS feed) |
| Testing | Pest 5, Larastan (PHPStan) at level 7+, Pint |

## Getting started

```bash
composer setup   # composer install, .env, app key, migrate, npm install, npm build
```

That's the one-shot path (defined in `composer.json`). Manually, the
equivalent is:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Then, day to day:

```bash
composer dev     # php artisan serve + queue:listen + pail + vite, concurrently
```

**`composer dev` does not run migrations.** After pulling a merge that
includes one, run `php artisan migrate` yourself — this has bitten real
sessions more than once.

By default the app runs against SQLite (`DB_CONNECTION=sqlite` in
`.env.example`); point `DB_CONNECTION`/`DB_*` at MySQL or MariaDB for
anything closer to production.

## Verifying a change

Four gates, all of which must be green before anything merges:

```bash
composer test        # Pint (style) + Larastan level 7 (static analysis) + Pest (tests)
npm run check        # ESLint/Prettier via the project's vp wrapper
npm run types:check  # tsc --noEmit
npm run build         # production Vite build — the only thing that catches an unresolved import
```

`vendor/bin/phpstan` needs `--memory-limit=1G` if you run it directly
rather than through `composer test`.

**If a `public/hot` file exists, delete it before trusting any test run.**
Laravel serves from the Vite dev server while that file is present and
never reads the build manifest — meaning a genuinely broken production
build passes every test silently.

## Architecture notes worth knowing before you dig in

- **`App\Support\Calendar\EventRedactor`** is the only code allowed to turn
  an `Event` Eloquent model into something serializable. It returns a
  `RedactedEvent` DTO, never the model — this is what makes a private
  event leaking to the wrong viewer a structurally impossible mistake
  rather than merely an unlikely one.
- **`App\Support\Calendar\Occurrence`** wraps a `RedactedEvent` with one
  instance's actual start/end. A single recurring row can produce many
  occurrences, so this — not the event id — is what a React list keys on.
- **`App\Support\Calendar\OccurrenceQuery`** is the only class permitted to
  query the `events` table for display. Every controller goes through it;
  nothing queries `Event::` directly for anything a user will see.
- **The Super Admin is not universally privileged.** `Gate::before`
  bypasses read/write authorization checks for a Super Admin, but is
  deliberately narrowed for writes against another user's *personal*
  calendar, and `EventRedactor` never treats a Super Admin as entitled to
  read redacted content either. "Can administer the platform" and "may
  read the contents of someone's private appointments" are different
  questions.
- **The design system is two-layer Tailwind 4**: `@theme` for static
  design constants (type scale, radii, motion curves), `@theme inline` for
  semantic colors that need to swap between light and dark. Materials
  (`material-ultrathin` → `material-thick`) are the elevation scale; two of
  the same thickness should never nest.

## Project structure (the non-obvious parts)

```
app/Support/Calendar/     # EventRedactor, OccurrenceQuery, Occurrence, RedactedEvent,
                           # ConflictDetector, RecurrenceExpander/Editor, GroupAdminLookup
app/Enums/                 # RoleName, EventVisibility, EventMessageType — comparisons
                           # go through these, never raw strings
resources/js/components/ui/  # The design system's primitives (Button, Card, Field, Modal, ...)
resources/js/lib/datetime.ts  # Viewer-timezone-aware date helpers (TZDate-based)
docs/superpowers/plans/PROGRESS.md   # Live execution log — read this first when resuming
                                       # work after a break; it tracks what's done, what's
                                       # merged, and what's still outstanding
docs/superpowers/specs/    # Written design docs for each architectural phase
```

## Roadmap

- **ICS subscription feed** — a per-user secret-token URL so an iPhone (or
  Google Calendar, or Outlook) can subscribe and see redacted events on a
  normal recurring poll. Design spec:
  `docs/superpowers/specs/2026-09-15-ics-feed-design.md`.
- **Google Calendar two-way sync** — planned as a later phase, against the
  REST API directly via Laravel's `Http` facade (neither `google/apiclient`
  nor `laravel/socialite` is compatible with this project's Guzzle
  version).

See `docs/superpowers/plans/PROGRESS.md` for the authoritative, up-to-date
state of every phase — this README describes what the app is, that file
describes what's actually been shipped versus what's still in flight.

## Contributing / workflow

- `dev` is the integration branch. Every phase/feature is its own branch
  cut from `dev`, PR'd back into `dev` once all four gates are green.
- `main` is updated only by the project owner, merging from `dev` when
  ready — never target `main` directly.
- Commits and PRs in this repo are frequently co-authored with Claude Code;
  attribution trailers on those commits reflect that.
