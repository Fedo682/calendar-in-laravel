<?php

namespace App\Support\Calendar;

use App\Models\CalendarFeedToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Feed tokens are looked up by the hash of what a request presents, never
 * by the plaintext - only the hash is ever persisted, so a stolen database
 * row is not a stolen credential.
 */
final class CalendarFeedTokenService
{
    /**
     * @return array{token: CalendarFeedToken, plaintext: string}
     */
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
