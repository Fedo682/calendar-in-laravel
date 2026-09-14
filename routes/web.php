<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupMembershipController;
use App\Http\Controllers\PersonalCalendarController;
use App\Http\Controllers\PersonalEventController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Cross-group calendar access ("My Calendars" - every calendar the user can reach)
    Route::get('/calendars', [CalendarController::class, 'all'])->name('calendars.index');

    // Groups (Super Admin manages create/update/delete; members can view groups they belong to)
    Route::resource('groups', GroupController::class)->except(['edit']);

    Route::prefix('groups/{group}')->name('groups.')->group(function () {
        // Group membership (Admin manages Members; Super Admin assigns Admins).
        // Not wrapped in scopeBindings(): Group has no users() relation (only
        // members(), which scopeBindings won't match by convention), and the
        // controller explicitly verifies {user} belongs to {group} itself.
        Route::get('members', [GroupMembershipController::class, 'index'])->name('members.index');
        Route::post('members', [GroupMembershipController::class, 'store'])->name('members.store');
        Route::post('members/bulk', [GroupMembershipController::class, 'bulkStore'])->name('members.bulk');
        Route::put('members/{user}', [GroupMembershipController::class, 'update'])->name('members.update');
        Route::delete('members/{user}', [GroupMembershipController::class, 'destroy'])->name('members.destroy');
    });

    Route::prefix('groups/{group}')->name('groups.')->scopeBindings()->group(function () {
        // Calendars (Admin manages; Members view)
        Route::resource('calendars', CalendarController::class)->except(['create', 'edit']);

        Route::prefix('calendars/{calendar}')->name('calendars.')->scopeBindings()->group(function () {
            // Events (Admin manages; Members view)
            Route::resource('events', EventController::class)->except(['create', 'edit']);
            Route::post('events/{event}/report-conflict', [EventController::class, 'reportConflict'])
                ->name('events.report-conflict');
        });
    });

    // ===================================================================
    // PERSONAL CALENDARS & EVENT VISIBILITY
    // ===================================================================

    // Not nested under a group: a personal calendar belongs to one user and
    // has no group_id, so there is no {group} segment to bind.
    Route::get('calendars/personal', [PersonalCalendarController::class, 'show'])
        ->name('calendars.personal');

    // No {calendar} segment either: the calendar is whichever one belongs to
    // the caller, and the policy is what ties {event} back to it.
    Route::post('calendars/personal/events', [PersonalEventController::class, 'store'])
        ->name('calendars.personal.events.store');
    Route::put('calendars/personal/events/{event}', [PersonalEventController::class, 'update'])
        ->name('calendars.personal.events.update');
    Route::delete('calendars/personal/events/{event}', [PersonalEventController::class, 'destroy'])
        ->name('calendars.personal.events.destroy');

    // ===================================================================
    // DESIGN SYSTEM
    // ===================================================================

    // Every primitive in every variant, for visual QA while the pages are
    // converted. A development surface: abort() rather than a middleware, so
    // the route is simply absent in production rather than being something to
    // secure. 'testing' is included so the page is covered by a test that
    // renders it, which is what catches a primitive whose props have drifted.
    Route::get('styleguide', function () {
        abort_unless(app()->environment(['local', 'testing']), 404);

        return Inertia::render('Styleguide');
    })->name('styleguide');

    // ===================================================================
    // USER PREFERENCES (timezone, appearance)
    // ===================================================================

    // Timezone, week start and time format ride along on profile.update.
    // The theme is separate so a toggle can persist it without resubmitting
    // (and revalidating) the whole profile form.
    Route::patch('/profile/appearance', [ProfileController::class, 'updateAppearance'])
        ->name('profile.appearance');

    // ===================================================================
    // SETTINGS: ICS FEED TOKENS
    // ===================================================================

    // ===================================================================
    // SETTINGS: GOOGLE CALENDAR SYNC
    // ===================================================================
});

// =======================================================================
// UNAUTHENTICATED MACHINE ENDPOINTS
//
// Routes below are reached by external clients rather than by a browser
// session: subscribing calendar apps and Google's push notifications.
// They authenticate on their own credentials and must be registered with
// the session middleware stripped, so no cookie is ever issued to them.
// =======================================================================

// ---- ICS subscription feed ----

// ---- Google push notification webhook ----

require __DIR__.'/auth.php';
