<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use DateTimeZone;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'timezoneOptions' => $this->timezoneOptions(),
        ]);
    }

    /**
     * Persist the appearance preference on its own.
     *
     * Separate from update() so a theme toggle does not have to round-trip the
     * whole profile form (and trip its required name/email rules) to save one
     * preference.
     */
    public function updateAppearance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:system,light,dark'],
        ]);

        $request->user()->update($validated);

        return Redirect::back();
    }

    /**
     * Every IANA identifier PHP knows, grouped by region for <optgroup>.
     *
     * Built server-side because the frontend has no list of its own: `Intl`
     * can resolve the browser's current zone but cannot enumerate the rest.
     *
     * @return list<array{region: string, timezones: list<array{value: string, label: string}>}>
     */
    private function timezoneOptions(): array
    {
        /** @var array<string, list<array{value: string, label: string}>> $grouped */
        $grouped = [];

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            // 'UTC' and the other region-less identifiers carry no slash; they
            // get their own bucket rather than being dropped from the list.
            $hasRegion = str_contains($identifier, '/');

            $region = $hasRegion ? Str::before($identifier, '/') : 'Other';
            $city = $hasRegion ? Str::after($identifier, '/') : $identifier;

            $grouped[$region][] = [
                'value' => $identifier,
                // 'Argentina/Buenos_Aires' reads better as 'Argentina / Buenos Aires'.
                'label' => str_replace(['_', '/'], [' ', ' / '], $city),
            ];
        }

        ksort($grouped);

        $options = [];

        foreach ($grouped as $region => $timezones) {
            $options[] = ['region' => (string) $region, 'timezones' => $timezones];
        }

        return $options;
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
