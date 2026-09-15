<?php

use App\Enums\EventVisibility;
use App\Enums\RoleName;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Calendar\CalendarFeedTokenService;
use App\Support\Calendar\Occurrence;
use App\Support\Calendar\RecurrenceExpander;
use App\Support\Calendar\RedactedEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Pest exposes functions declared here globally to every test file. The role
| helpers below live here rather than in individual test files because the
| suite previously carried four near-identical copies of the same "attach a
| role to a user in a group" helper, one per file, which every new test file
| then had to either duplicate or reach across to.
|
*/

/**
 * Seed the three roles the application authorizes against.
 *
 * RolePermissionSeeder also seeds the permissions catalogue, but nothing
 * reads it at runtime, so tests only need the roles.
 */
function seedRoles(): void
{
    foreach (RoleName::cases() as $role) {
        Role::firstOrCreate(['name' => $role->value]);
    }
}

/**
 * Give a user a role inside a group, creating the role if the test has not
 * seeded it. Returns the user so it can be used inline with actingAs().
 */
function asGroupRole(Group $group, User $user, RoleName|string $role): User
{
    $name = $role instanceof RoleName ? $role->value : $role;

    // Seed all three roles, not just the one being attached. Controllers
    // look roles up by name at runtime (bulk member invites resolve 'member'
    // themselves), so a database holding only the actor's role makes them
    // fail on a NOT NULL role_id rather than on anything the test is about.
    seedRoles();

    GroupUser::updateOrCreate(
        ['group_id' => $group->id, 'user_id' => $user->id],
        ['role_id' => Role::firstOrCreate(['name' => $name])->id],
    );

    return $user->refresh();
}

/**
 * Grant the platform-wide Super Admin role, creating a user if none is given.
 *
 * Super Admin is not a group membership - it is granted through user_role and
 * short-circuits every policy via the Gate::before hook in AppServiceProvider.
 */
function asSuperAdmin(?User $user = null): User
{
    seedRoles();

    $user ??= User::factory()->create();
    $user->roles()->syncWithoutDetaching([
        Role::where('name', RoleName::SuperAdmin->value)->value('id'),
    ]);

    return $user->refresh();
}

/**
 * A recurring master as a value object, anchored by a wall-clock time in a
 * named zone.
 *
 * $localStart is written the way a person would say it - "09:00 in Berlin" -
 * and converted here, so a test asserting 09:00 local never hand-computes a
 * UTC offset and so cannot quietly encode the very mistake it is checking
 * for.
 *
 * Lives here rather than in one of the recurrence test files because three of
 * them need it, and a test file that declares a global function cannot be
 * loaded alongside another declaring the same one.
 *
 * @param  list<string>  $exdates
 * @param  list<string>  $rdates
 */
function recurringMaster(
    string $localStart,
    string $timezone,
    ?string $rule,
    int $durationMinutes = 60,
    array $exdates = [],
    array $rdates = [],
    int $id = 1,
    string $title = 'Standup',
): RedactedEvent {
    $startsAt = CarbonImmutable::parse($localStart, $timezone)->utc();

    return new RedactedEvent(
        id: $id,
        calendarId: 1,
        groupId: 1,
        title: $title,
        description: null,
        location: null,
        startsAt: $startsAt,
        endsAt: $startsAt->addMinutes($durationMinutes),
        allDay: false,
        visibility: EventVisibility::Public,
        isRedacted: false,
        canEdit: true,
        createdBy: 1,
        calendarName: 'Team',
        calendarColor: null,
        groupName: 'Acme',
        recurrenceRule: $rule,
        recurrenceTimezone: $rule === null ? null : $timezone,
        recurrenceExdates: $exdates,
        recurrenceRdates: $rdates,
    );
}

/**
 * Expand a series over a window expressed in $timezone.
 *
 * @param  Collection<int, RedactedEvent>|null  $overrides
 * @return list<Occurrence>
 */
function expandSeries(
    RedactedEvent $master,
    string $from,
    string $to,
    string $timezone = 'UTC',
    ?Collection $overrides = null,
): array {
    return app(RecurrenceExpander::class)->expand(
        $master,
        CarbonImmutable::parse($from, $timezone)->utc(),
        CarbonImmutable::parse($to, $timezone)->utc(),
        $overrides ?? collect(),
    );
}

/**
 * The local wall-clock start of each occurrence, which is the form these
 * assertions are actually about.
 *
 * @param  list<Occurrence>  $occurrences
 * @return list<string>
 */
function localStarts(array $occurrences, string $timezone): array
{
    return array_map(
        fn (Occurrence $o) => $o->startsAt->setTimezone($timezone)->format('Y-m-d H:i'),
        $occurrences,
    );
}

/**
 * Issue a fresh ICS feed token for a user, returning only the plaintext -
 * the shape every feed test actually needs to hit the route with.
 */
function issueFeedToken(User $user): string
{
    ['plaintext' => $plaintext] = app(CalendarFeedTokenService::class)->issue($user);

    return $plaintext;
}
