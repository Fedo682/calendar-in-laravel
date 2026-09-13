<?php

use App\Enums\RoleName;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
