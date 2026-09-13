<?php

use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;

test('dashboard only shows events from calendars the user can see', function () {
    $myGroup = Group::factory()->create();
    $otherGroup = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($myGroup, $user, 'member');

    $myCalendar = Calendar::factory()->create(['group_id' => $myGroup->id]);
    $otherCalendar = Calendar::factory()->create(['group_id' => $otherGroup->id]);

    Event::factory()->create([
        'calendar_id' => $myCalendar->id,
        'title' => 'Mine',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);
    Event::factory()->create([
        'calendar_id' => $otherCalendar->id,
        'title' => 'Not Mine',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('upcoming_events', 1)
        ->where('upcoming_events.0.title', 'Mine')
    );
});

test('super admin sees events across every group', function () {
    $groupA = Group::factory()->create();
    $groupB = Group::factory()->create();
    $superAdmin = User::factory()->create();
    $superAdmin->roles()->attach(Role::firstOrCreate(['name' => 'super_admin'])->id);

    $calendarA = Calendar::factory()->create(['group_id' => $groupA->id]);
    $calendarB = Calendar::factory()->create(['group_id' => $groupB->id]);

    Event::factory()->create([
        'calendar_id' => $calendarA->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);
    Event::factory()->create([
        'calendar_id' => $calendarB->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
    ]);

    $this->actingAs($superAdmin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->has('upcoming_events', 2));
});

test('overlapping events across different calendars are flagged as conflicts', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'member');

    // Two calendars in the same group the user can see.
    $calendarA = Calendar::factory()->create(['group_id' => $group->id]);
    $calendarB = Calendar::factory()->create(['group_id' => $group->id]);

    $start = now()->addDay()->setTime(10, 0);

    Event::factory()->create([
        'calendar_id' => $calendarA->id,
        'title' => 'Standup',
        'starts_at' => $start,
        'ends_at' => (clone $start)->addHour(),
    ]);
    Event::factory()->create([
        'calendar_id' => $calendarB->id,
        'title' => 'Client Call',
        'starts_at' => (clone $start)->addMinutes(30),
        'ends_at' => (clone $start)->addMinutes(90),
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->has('upcoming_events', 2)
        ->where('upcoming_events.0.conflicts_with', ['Client Call'])
        ->where('upcoming_events.1.conflicts_with', ['Standup'])
    );
});

test('non-overlapping events are not flagged', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'member');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $start = now()->addDay()->setTime(9, 0);

    Event::factory()->create([
        'calendar_id' => $calendar->id,
        'starts_at' => $start,
        'ends_at' => (clone $start)->addHour(),
    ]);
    Event::factory()->create([
        'calendar_id' => $calendar->id,
        'starts_at' => (clone $start)->addHours(2),
        'ends_at' => (clone $start)->addHours(3),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('upcoming_events.0.conflicts_with', [])
            ->where('upcoming_events.1.conflicts_with', [])
        );
});

test('events outside the upcoming window are excluded', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'member');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    Event::factory()->create([
        'calendar_id' => $calendar->id,
        'title' => 'Too far out',
        'starts_at' => now()->addDays(30),
        'ends_at' => now()->addDays(30)->addHour(),
    ]);
    Event::factory()->create([
        'calendar_id' => $calendar->id,
        'title' => 'In the past',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->has('upcoming_events', 0));
});
