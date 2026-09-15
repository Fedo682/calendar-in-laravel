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

test('a user cannot revoke another users token', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $token = CalendarFeedToken::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->delete("/settings/integrations/ics-tokens/{$token->id}")
        ->assertNotFound();

    expect($token->fresh()->isRevoked())->toBeFalse();
});
