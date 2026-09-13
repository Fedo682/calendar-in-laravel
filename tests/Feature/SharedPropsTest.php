<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('flash messages reach the frontend as shared props', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['success' => 'Event created successfully.'])
        ->get('/dashboard')
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('flash.success', 'Event created successfully.')
                ->where('flash.error', null)
        );
});

test('flash is present but empty when nothing was flashed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('flash.success', null)
                ->where('flash.error', null)
        );
});

test('a redirect carrying a success message surfaces it on the next page', function () {
    $user = asSuperAdmin();

    // GroupController@store redirects ->with('success', ...). Before the flash
    // key was shared, that message never reached the page it redirected to.
    $this->actingAs($user)
        ->post('/groups', ['name' => 'Engineering', 'description' => null])
        ->assertRedirect();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(
            fn (AssertableInertia $page) => $page->whereNot('flash.success', null)
        );
});

test('viewer preferences are shared with sensible defaults', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('viewer.timezone', config('app.timezone'))
                ->where('viewer.theme', 'system')
                ->where('viewer.week_starts_on', 0)
                ->where('viewer.time_format', '12h')
        );
});

test('auth props still expose the user and super admin flag', function () {
    $user = asSuperAdmin();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('auth.user.id', $user->id)
                ->where('auth.is_super_admin', true)
        );
});

test('shared props are safe for a guest with no authenticated user', function () {
    // The middleware runs on every web route, including the unauthenticated
    // landing page, so the viewer defaults must survive a null user.
    $this->get('/')
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('auth.user', null)
                ->where('auth.is_super_admin', false)
                ->where('viewer.timezone', config('app.timezone'))
                ->where('viewer.theme', 'system')
        );
});
