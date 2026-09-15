# Per-User ICS Subscription Feed

## Context

This is Phase 7 of the original roadmap: a per-user secret-token URL an
iPhone (or Google Calendar, or Outlook) can subscribe to, showing every
calendar the user can see - other people's private events already show as
opaque "Busy" blocks, because the feed reads through the same
`EventRedactor`/`OccurrenceQuery` chokepoint the web app does.

Most of the groundwork already exists from an earlier seams phase:
`sabre/vobject` is installed, `config('calendar.ics.*')` is seeded,
`RedactedEvent::uid()` exists, and `OccurrenceQuery::mastersFor()` already
returns exactly the right shape for a feed - redacted, unexpanded
`RedactedEvent`s (masters carrying their raw RRULE, plus any in-window
override rows carrying their own real start/end and `recurrenceInstanceId`).
This spec is mostly about the small number of genuinely new pieces: the
token model, the ICS serialization itself, and the subscription-management
page.

**Correction to the original roadmap while researching this:** the roadmap
assumed `Sabre\VObject\TimeZoneUtil` "emits real VTIMEZONE blocks with
correct DAYLIGHT/STANDARD sub-components." It doesn't - that class only
*parses/guesses* timezones from files a client already produced; sabre has
no VTIMEZONE generator. Building one properly (enumerating DST transition
history via `DateTimeZone::getTransitions()` and emitting RRULE-based
STANDARD/DAYLIGHT sub-components) is real, separate work, deferred - see
"Explicitly deferred" below. This spec uses bare IANA `TZID` references
instead (see "Timezone encoding").

## Decisions already made (approved in chat)

| Area | Decision |
|---|---|
| Token model | A `calendar_feed_tokens` **table**, not a `users` column - scales to multiple tokens per user without a future migration. UI ships with one token per user to start; the list rendering already supports N. |
| Subscription UI | New `Settings/Integrations.tsx` page + route, not a Profile section. |
| Timezone encoding | Bare IANA `TZID` (no embedded VTIMEZONE component) for recurring events; UTC for one-off events and overrides. See below. |
| Caching | A cheap ETag fingerprint, so a repeat poll with `If-None-Match` gets a `304` without touching vobject. |
| Deferred | Full generated VTIMEZONE components; the "add another device" UX beyond a single token; Google/Outlook-specific quirk handling. |

## Section 1: Token model

### Migration

```php
Schema::create('calendar_feed_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('token_hash', 64)->unique();
    $table->string('label')->nullable();
    $table->timestamp('last_used_at')->nullable();
    $table->string('last_ip', 45)->nullable();
    $table->string('last_user_agent')->nullable();
    $table->timestamp('revoked_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'revoked_at']);
});
```

Plaintext is `Str::random(48)`, shown exactly once at creation; only
`hash('sha256', $plaintext)` is ever stored. A revoked token is never
deleted (kept for audit - who had a URL that leaked and when it was cut off).

### `App\Models\CalendarFeedToken`

```php
protected $fillable = ['user_id', 'token_hash', 'label'];

protected function casts(): array
{
    return ['last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
}

public function user(): BelongsTo { return $this->belongsTo(User::class); }
public function isRevoked(): bool { return $this->revoked_at !== null; }
```

### `App\Support\Calendar\CalendarFeedTokenService`

```php
final class CalendarFeedTokenService
{
    /** @return array{token: CalendarFeedToken, plaintext: string} */
    public function issue(User $user, ?string $label = null): array
    {
        $plaintext = Str::random(48);

        $token = CalendarFeedToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plaintext),
            'label' => $label,
        ]);

        return ['token' => $token, 'plaintext' => $plaintext];
    }

    public function resolve(string $plaintext): ?CalendarFeedToken
    {
        return CalendarFeedToken::query()
            ->where('token_hash', hash('sha256', $plaintext))
            ->whereNull('revoked_at')
            ->first();
    }

    public function touch(CalendarFeedToken $token, Request $request): void
    {
        $token->update([
            'last_used_at' => now(),
            'last_ip' => $request->ip(),
            'last_user_agent' => (string) $request->userAgent(),
        ]);
    }

    public function revoke(CalendarFeedToken $token): void
    {
        $token->update(['revoked_at' => now()]);
    }
}
```

`resolve()` looks up by the hash's own unique index - no separate
`hash_equals` pass is needed on top of that, because what's being compared
is a SHA-256 digest via an indexed equality lookup, not a raw secret via a
branching string comparison; there is nothing timing-observable about
*which* 256-bit token produced a given hash.

## Section 2: `IcsFeedBuilder`

```php
final class IcsFeedBuilder
{
    public function __construct(
        private readonly OccurrenceQuery $occurrences,
    ) {}

    public function build(User $user): string
    {
        $from = now()->subMonths(config('calendar.ics.past_months'));
        $to = now()->addMonths(config('calendar.ics.future_months'));

        $redacted = $this->occurrences->mastersFor($user, $from, $to);

        $calendar = new VCalendar();
        $calendar->add('PRODID', '-//GroupSync Calendar//ICS Feed//EN');
        $calendar->add('VERSION', '2.0');
        $calendar->add('CALSCALE', 'GREGORIAN');
        $calendar->add('X-WR-CALNAME', $user->name.' - GroupSync Calendar');
        $calendar->add('X-PUBLISHED-TTL', config('calendar.ics.refresh_interval'));
        $calendar->add('REFRESH-INTERVAL', config('calendar.ics.refresh_interval'), ['VALUE' => 'DURATION']);

        foreach ($redacted as $event) {
            $this->addVEvent($calendar, $event);
        }

        return $calendar->serialize();
    }

    private function addVEvent(VCalendar $calendar, RedactedEvent $event): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

        // $calendar->add() is the correct construction path - it both
        // creates the VEvent as a child of this VCalendar (so it validates
        // and serializes against the right root) and returns it, rather
        // than building a VEvent against a throwaway parent document.
        $vevent = $calendar->add('VEVENT', [
            'UID' => $event->uid($host),
            'SUMMARY' => $event->title,
            'DTSTAMP' => new \DateTime('now', new \DateTimeZone('UTC')),
        ]);

        $this->setDates($vevent, $event);

        if ($event->description !== null) {
            $vevent->add('DESCRIPTION', $event->description);
        }
        if ($event->location !== null) {
            $vevent->add('LOCATION', $event->location);
        }

        if ($event->isRecurring()) {
            $vevent->add('RRULE', $event->recurrenceRule);

            foreach ($event->recurrenceExdates as $exdate) {
                $vevent->add('EXDATE', $exdate);
            }
        }

        if ($event->recurrenceInstanceId !== null) {
            $vevent->add('RECURRENCE-ID', $event->recurrenceInstanceId->format('Ymd\THis\Z'));
        }

        [$class, $transp] = match (true) {
            $event->isRedacted => ['CONFIDENTIAL', 'OPAQUE'],
            default => ['PUBLIC', 'OPAQUE'],
        };
        $vevent->add('CLASS', $class);
        $vevent->add('TRANSP', $transp);

        return $vevent;
    }
}
```

### Timezone encoding

- **One-off event** (`recurrenceRule === null` and not an override): UTC.
  `DTSTART:20260920T090000Z` / `DTEND:...Z`. Unambiguous, no DST concern.
- **Recurring master**: bare IANA `TZID`, no VTIMEZONE component.
  `DTSTART;TZID=Europe/Berlin:20260920T090000`. This is what preserves "9am
  Berlin stays 9am Berlin across DST" for a subscriber - a raw UTC RRULE
  would silently drift by the DST offset twice a year, exactly the bug this
  app's recurrence phase was built to prevent. Every mainstream client
  (Apple/Google/Outlook) resolves bare IANA identifiers correctly since they
  all ship the same tzdata; this is what Google Calendar's own ICS export
  does too. A step short of strict RFC 5545 (which wants either a
  VTIMEZONE or a UTC instant) - accepted, documented as a known deviation.
- **Override row** (`recurrenceParentId !== null`): UTC, same as a one-off
  - it has its own fixed real start/end and doesn't repeat.
  `RECURRENCE-ID` is expressed in UTC too
  (`$event->recurrenceInstanceId->format('Ymd\THis\Z')`); a UTC
  `RECURRENCE-ID` against a `TZID`-anchored master's DTSTART is fine -
  clients match on the absolute instant it names, not its representation.
- **All-day events**: `DTSTART;VALUE=DATE:20260920`, `DTEND` **exclusive**
  (the day after the event's own last day) - the single most common ICS
  bug is getting this off-by-one wrong.

### The `uid()` fix

`RedactedEvent::uid()` currently always uses `$this->id` - the row's own
database id. For an override row, that id belongs to the *override*, not
its master, so an override would get a UID that doesn't match its own
series - breaking a calendar app's ability to associate "this changed
instance" with "that recurring series." Fix:

```php
public function uid(string $host): string
{
    return sprintf('event-%d@%s', $this->recurrenceParentId ?? $this->id, $host);
}
```

## Section 3: Caching

```php
final class IcsFeedCache
{
    public function fingerprint(User $user): string
    {
        $ids = $user->accessibleCalendarIds();

        $agg = Event::query()
            ->whereIn('calendar_id', $ids)
            ->selectRaw('MAX(updated_at) as latest, COUNT(*) as total')
            ->first();

        return sha1(implode('|', [
            $agg->latest,
            $agg->total,
            $ids->sort()->implode(','),
            config('app.version', '1'),
        ]));
    }
}
```

The controller computes this fingerprint first (two indexed aggregates,
no vobject work), compares it against the request's `If-None-Match`, and
returns a bare `304` on a match. Only on a miss does it call
`IcsFeedBuilder::build()` and return the body with `ETag` set to the new
fingerprint and `Cache-Control: private, max-age={config('calendar.ics.cache_ttl')}`.

## Section 4: Routes and controller

```php
// Outside auth entirely - token-authenticated, not cookie-authenticated.
Route::get('feed/{token}.ics', [IcsFeedController::class, 'show'])
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
    ])
    ->name('ics.feed');

Route::middleware('auth')->group(function () {
    Route::get('settings/integrations', [SettingsIntegrationsController::class, 'edit'])
        ->name('settings.integrations');
    Route::post('settings/integrations/ics-tokens', [SettingsIntegrationsController::class, 'store'])
        ->name('ics.tokens.store');
    Route::delete('settings/integrations/ics-tokens/{token}', [SettingsIntegrationsController::class, 'destroy'])
        ->name('ics.tokens.destroy');
});
```

```php
class IcsFeedController extends Controller
{
    public function __construct(
        private readonly CalendarFeedTokenService $tokens,
        private readonly IcsFeedBuilder $builder,
        private readonly IcsFeedCache $cache,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $record = $this->tokens->resolve($token);

        // Invalid or revoked both 404 - never confirm a token ever existed.
        if ($record === null) {
            abort(404);
        }

        $this->tokens->touch($record, $request);

        $fingerprint = $this->cache->fingerprint($record->user);

        if ($request->header('If-None-Match') === '"'.$fingerprint.'"') {
            return response('', 304)->header('ETag', '"'.$fingerprint.'"');
        }

        return response($this->builder->build($record->user), 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename=calendar.ics')
            ->header('ETag', '"'.$fingerprint.'"')
            ->header('Cache-Control', 'private, max-age='.config('calendar.ics.cache_ttl'))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
```

```php
class SettingsIntegrationsController extends Controller
{
    public function __construct(private readonly CalendarFeedTokenService $tokens) {}

    public function edit(Request $request): Response
    {
        $tokens = $request->user()->feedTokens()->whereNull('revoked_at')->get();

        return Inertia::render('Settings/Integrations', [
            'tokens' => $tokens->map(fn ($t) => [
                'id' => $t->id,
                'label' => $t->label,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'created_at' => $t->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['label' => ['nullable', 'string', 'max:255']]);

        ['token' => $token, 'plaintext' => $plaintext] = $this->tokens->issue(
            $request->user(),
            $data['label'] ?? null,
        );

        // Flashed once - the only time the plaintext is ever available.
        return back()->with('new_feed_url', route('ics.feed', $plaintext));
    }

    public function destroy(Request $request, CalendarFeedToken $token): RedirectResponse
    {
        abort_unless($token->user_id === $request->user()->id, 404);

        $this->tokens->revoke($token);

        return back()->with('success', 'Feed token revoked.');
    }
}
```

`User` gains one relation: `public function feedTokens(): HasMany { return
$this->hasMany(CalendarFeedToken::class); }`.

## Section 5: Frontend

`Settings/Integrations.tsx` - new page, reachable from `AppShell`'s
sidebar (a new `SidebarItem` next to the existing nav entries). Shows:

- A "Your calendar feed" card: if `flash.new_feed_url` is present (only
  right after issuing a token), show the full URL with a copy button and a
  `webcal://` deep link, with a one-line warning that it won't be shown
  again.
- A list of active tokens (label, created date, last-used date or "Never
  used"), each with a "Revoke" button. Starts empty; a "Generate a feed
  URL" button posts to `ics.tokens.store` when there are none.
- Copy-to-clipboard via `navigator.clipboard.writeText`.

## Testing strategy

- **`CalendarFeedTokenServiceTest`**: `issue()` returns a working
  plaintext that `resolve()` finds; a revoked token's plaintext no longer
  resolves; `revoke()` doesn't delete the row.
- **`IcsFeedTest`** (the important one):
  - Valid token → `200`, `Content-Type: text/calendar`.
  - Invalid token → `404` (not `403`).
  - Revoked token → `404`.
  - A repeat request with a matching `If-None-Match` → `304`.
  - An edit to one of the user's events changes the fingerprint (next
    request is not a `304`).
  - Only calendars in `accessibleCalendarIds()` appear.
  - **Extends the existing private-event-leak dataset**: another user's
    private event appears as `SUMMARY:Busy` with no `DESCRIPTION`/
    `LOCATION` and `CLASS:CONFIDENTIAL`.
  - A recurring series appears as exactly one `VEVENT` carrying an
    `RRULE`, not one per occurrence.
  - An override appears as its own `VEVENT` with a `RECURRENCE-ID`, and
    its `UID` matches its master's `UID` (the `uid()` fix, tested
    directly).
  - An all-day event's `DTEND` is one day past its own last day.
  - Parse the response back with `Sabre\VObject\Reader::read()` and
    assert it parses without throwing - the cheapest possible check that
    the serialized output is actually valid ICS.
- **`SettingsIntegrationsTest`**: issuing a token flashes a working feed
  URL; revoking a token makes its old URL 404 on the next feed request;
  a user cannot revoke another user's token (404, not 403 - same
  non-confirmation principle).

## Explicitly deferred

- A real generated VTIMEZONE component with historically-accurate
  DAYLIGHT/STANDARD transition rules.
- Multiple named tokens' full "add another device" UX - the schema and
  list rendering support it; today's UI only offers "generate the first
  one."
- Google/Outlook-specific ICS quirks (e.g. Google's slow, uncontrollable
  poll cadence for "subscribe from URL").
- `METHOD:` is deliberately omitted from the calendar - `METHOD:PUBLISH`
  makes some clients treat entries as meeting invitations rather than a
  read-only calendar.
