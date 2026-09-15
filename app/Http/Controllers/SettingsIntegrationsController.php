<?php

namespace App\Http\Controllers;

use App\Models\CalendarFeedToken;
use App\Support\Calendar\CalendarFeedTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsIntegrationsController extends Controller
{
    public function __construct(
        private readonly CalendarFeedTokenService $tokens,
    ) {}

    public function edit(Request $request): Response
    {
        $tokens = $request->user()->feedTokens()->whereNull('revoked_at')->latest()->get();

        return Inertia::render('Settings/Integrations', [
            'tokens' => $tokens->map(fn (CalendarFeedToken $t) => [
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

        ['plaintext' => $plaintext] = $this->tokens->issue($request->user(), $data['label'] ?? null);

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
