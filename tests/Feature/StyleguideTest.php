<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('the styleguide renders every primitive without error', function () {
    // The point of rendering it in a test: a primitive whose props drift out
    // from under this page fails here rather than at visual-QA time.
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/styleguide')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Styleguide'));
});

test('the styleguide is absent in production', function () {
    // A 404 rather than a 403: in production the route should not exist at
    // all, so there is nothing to secure and nothing to advertise.
    app()['env'] = 'production';

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/styleguide')
        ->assertNotFound();
});

test('the styleguide still requires authentication', function () {
    $this->get('/styleguide')->assertRedirect('/login');
});
