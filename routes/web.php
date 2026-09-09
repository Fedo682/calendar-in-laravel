<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupMembershipController;
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
});

require __DIR__.'/auth.php';
