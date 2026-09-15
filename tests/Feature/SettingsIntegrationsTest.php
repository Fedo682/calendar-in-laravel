<?php

use App\Models\CalendarFeedToken;
use App\Models\User;

test('the integrations page lists the users own tokens', function () {
    $user = User::factory()->create();
    CalendarFeedToken::factory()->create(['user_id' => $user->id, 'label' => 'iPhone']);

    $this->actingAs($user)
        ->get('/settings/integrations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Integrations')
            ->has('tokens', 1)
            ->where('tokens.0.label', 'iPhone')
        );
});

test('issuing a token flashes a working feed url', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/settings/integrations/ics-tokens', ['label' => 'Work laptop']);

    $response->assertRedirect();
    $url = session('new_feed_url');
    expect($url)->not->toBeNull();

    $this->get($url)->assertOk();
});

test('revoking a token makes its url 404 on the next request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/integrations/ics-tokens', []);
    $url = session('new_feed_url');
    $token = CalendarFeedToken::first();

    $this->actingAs($user)
        ->delete("/settings/integrations/ics-tokens/{$token->id}")
        ->assertRedirect();

    $this->get($url)->assertNotFound();
});

test('the flashed feed url always uses APP_URL, regardless of the request Host header', function () {
    // A subscribe link is meant to be stable and shareable - if it echoed
    // back whatever Host header the browser happened to send, visiting the
    // app via a different hostname (localhost vs a LAN IP vs a future
    // custom domain) would silently hand out a broken URL. This is exactly
    // what happened during manual testing: generating the token by
    // visiting the app at http://localhost:8000 produced a feed URL with
    // "localhost" in it, which is meaningless on any other device.
    $user = User::factory()->create();

    // An absolute URI (rather than a path) is what actually forces Symfony's
    // Request::create() to derive its Host from the URI instead of from
    // TestCase's own base URL - a plain withServerVariables() override gets
    // clobbered the same way, so it wouldn't have caught this bug either.
    $response = $this->actingAs($user)
        ->post('http://some-other-host.example:9999/settings/integrations/ics-tokens', ['label' => 'Work laptop']);

    $response->assertRedirect();
    $url = session('new_feed_url');

    expect($url)->toStartWith(rtrim(config('app.url'), '/').'/feed/');
});

test('a user cannot revoke another users token', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $token = CalendarFeedToken::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->delete("/settings/integrations/ics-tokens/{$token->id}")
        ->assertNotFound();

    expect($token->fresh()->isRevoked())->toBeFalse();
});
