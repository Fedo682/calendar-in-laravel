<?php

use App\Models\CalendarFeedToken;
use App\Models\User;
use App\Support\Calendar\CalendarFeedTokenService;
use Illuminate\Http\Request;

test('issuing a token returns a plaintext that resolve() can find', function () {
    $user = User::factory()->create();
    $service = app(CalendarFeedTokenService::class);

    ['token' => $token, 'plaintext' => $plaintext] = $service->issue($user, 'iPhone');

    expect($token->user_id)->toBe($user->id)
        ->and($token->label)->toBe('iPhone')
        ->and($token->token_hash)->toBe(hash('sha256', $plaintext))
        ->and($token->isRevoked())->toBeFalse();

    $resolved = $service->resolve($plaintext);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($token->id);
});

test('an unknown plaintext does not resolve', function () {
    $service = app(CalendarFeedTokenService::class);

    expect($service->resolve('not-a-real-token'))->toBeNull();
});

test('a revoked token no longer resolves but the row survives', function () {
    $user = User::factory()->create();
    $service = app(CalendarFeedTokenService::class);

    ['token' => $token, 'plaintext' => $plaintext] = $service->issue($user);

    $service->revoke($token);

    expect($service->resolve($plaintext))->toBeNull();
    expect(CalendarFeedToken::find($token->id))->not->toBeNull();
    expect($token->fresh()->isRevoked())->toBeTrue();
});

test('touch records last_used_at, ip, and user agent', function () {
    $user = User::factory()->create();
    $service = app(CalendarFeedTokenService::class);
    ['token' => $token] = $service->issue($user);

    $request = Request::create('/feed/x.ics', 'GET', server: ['REMOTE_ADDR' => '203.0.113.5']);
    $request->headers->set('User-Agent', 'TestClient/1.0');

    $service->touch($token, $request);

    $token->refresh();
    expect($token->last_used_at)->not->toBeNull()
        ->and($token->last_ip)->toBe('203.0.113.5')
        ->and($token->last_user_agent)->toBe('TestClient/1.0');
});
