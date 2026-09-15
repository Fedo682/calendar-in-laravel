<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Every shared-prop key the roadmap needs is declared here in one place.
     * Later phases populate them; they are not added incrementally, because
     * parallel branches would otherwise all collide on this one method.
     *
     * The `viewer` columns (timezone/theme/week_starts_on) land in the
     * per-user preferences migration. The null-safe reads below keep this
     * working both before and after that migration runs.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'is_super_admin' => $user?->isSuperAdmin() ?? false,
            ],
            // Closures are evaluated lazily, so the session is only read when
            // a response actually renders. Without this key the `flash.success`
            // toasts on the Groups and Calendars pages never receive anything -
            // controllers redirect ->with('success', ...) into a void.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // The one time a freshly issued feed token's plaintext is
                // ever available - shown once on the Integrations page,
                // then gone; only its hash persists.
                'new_feed_url' => fn () => $request->session()->get('new_feed_url'),
            ],
            // Property reads use -> rather than ?->, because ?? already
            // suppresses reading a property off null. A method call would
            // still need ?-> (see is_super_admin above), which is why the
            // two lines differ.
            'viewer' => [
                'timezone' => $user->timezone ?? config('app.timezone'),
                'theme' => $user->theme ?? 'system',
                'week_starts_on' => $user->week_starts_on ?? 0,
                'time_format' => $user->time_format ?? '12h',
            ],
        ];
    }
}
