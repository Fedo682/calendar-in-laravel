<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('existing users come out of the migration with usable defaults', function () {
    // The columns are NOT NULL with defaults, so a row created without
    // mentioning them - as every row predating the migration was - still
    // renders correctly rather than needing a backfill.
    // fresh() because Eloquent does not re-read a row after inserting it, so
    // the in-memory model has no idea the database filled these in.
    $user = User::factory()->create()->fresh();

    expect($user->timezone)->toBe('UTC')
        ->and($user->theme)->toBe('system')
        ->and($user->week_starts_on)->toBe(0)
        ->and($user->time_format)->toBe('12h');
});

test('week_starts_on is cast to an integer', function () {
    // SQLite hands this back as a string without the cast, which breaks
    // strict comparisons against a 0-6 day index on the frontend.
    $user = User::factory()->create(['week_starts_on' => 3]);

    expect($user->fresh()->week_starts_on)->toBe(3);
});

test('viewerZone resolves the stored identifier', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Berlin']);

    expect($user->viewerZone()->getName())->toBe('Europe/Berlin');
});

test('viewerZone falls back to UTC rather than throwing on a bad identifier', function () {
    // Validation prevents this, but a row edited outside the app must not
    // take every page down.
    $user = User::factory()->create();
    $user->forceFill(['timezone' => 'Mars/Olympus'])->saveQuietly();

    expect($user->fresh()->viewerZone()->getName())->toBe('UTC');
});

test('timezone can be updated through the profile form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => 'Asia/Hebron',
            'week_starts_on' => 1,
            'time_format' => '24h',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->timezone)->toBe('Asia/Hebron')
        ->and($user->week_starts_on)->toBe(1)
        ->and($user->time_format)->toBe('24h');
});

test('a partial profile update leaves preferences untouched', function () {
    // Timezone is 'sometimes' validated: omitting it must not wipe or reject.
    $user = User::factory()->create(['timezone' => 'Europe/Berlin']);

    $this->actingAs($user)
        ->patch('/profile', ['name' => 'Renamed', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->timezone)->toBe('Europe/Berlin');
});

test('an unrecognised timezone identifier is rejected', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Berlin']);

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => 'Mars/Olympus',
        ])
        ->assertSessionHasErrors('timezone');

    expect($user->fresh()->timezone)->toBe('Europe/Berlin');
});

test('an explicitly blank timezone is rejected', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Berlin']);

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => '',
        ])
        ->assertSessionHasErrors('timezone');
});

test('week_starts_on outside the 0-6 range is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => 'UTC',
            'week_starts_on' => 9,
        ])
        ->assertSessionHasErrors('week_starts_on');
});

test('an unknown time format is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => 'UTC',
            'time_format' => 'sundial',
        ])
        ->assertSessionHasErrors('time_format');
});

test('the appearance endpoint persists a theme on its own', function () {
    // Deliberately sends no name/email - the whole point of the separate
    // endpoint is that a toggle does not resubmit the profile form.
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile/appearance', ['theme' => 'dark'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->theme)->toBe('dark');
});

test('the appearance endpoint rejects an unknown theme', function () {
    $user = User::factory()->create(['theme' => 'light']);

    $this->actingAs($user)
        ->patch('/profile/appearance', ['theme' => 'neon'])
        ->assertSessionHasErrors('theme');

    expect($user->fresh()->theme)->toBe('light');
});

test('the appearance endpoint requires authentication', function () {
    $this->patch('/profile/appearance', ['theme' => 'dark'])
        ->assertRedirect('/login');
});

test('viewer shared props reflect the stored preferences, not the app defaults', function () {
    $user = User::factory()->create([
        'timezone' => 'Asia/Hebron',
        'theme' => 'dark',
        'week_starts_on' => 1,
        'time_format' => '24h',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('viewer.timezone', 'Asia/Hebron')
                ->where('viewer.theme', 'dark')
                ->where('viewer.week_starts_on', 1)
                ->where('viewer.time_format', '24h')
        );
});

test('the profile page ships grouped timezone options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Profile/Edit')
                ->has('timezoneOptions')
                ->has('timezoneOptions.0.region')
                ->has('timezoneOptions.0.timezones.0.value')
                ->has('timezoneOptions.0.timezones.0.label')
        );
});

test('region-less identifiers such as UTC are still offered', function () {
    $user = User::factory()->create();

    // Read the page prop off the rendered view rather than asserting a path,
    // because the identifier of interest sits somewhere inside a long list.
    $response = $this->actingAs($user)->get('/profile')->assertOk();

    $groups = $response->original->getData()['page']['props']['timezoneOptions'];

    $values = collect($groups)->flatMap(
        fn (array $group) => array_column($group['timezones'], 'value')
    );

    expect($values->all())->toContain('UTC')            // region-less
        ->and($values->all())->toContain('Europe/Berlin'); // region-qualified
});
