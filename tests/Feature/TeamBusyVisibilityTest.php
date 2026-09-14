<?php

use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;

test('an admin sees a teammates private personal event as Busy with their name attached', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    $teammate = User::factory()->create(['name' => 'Jane Smith']);
    asGroupRole($group, $admin, 'admin');
    asGroupRole($group, $teammate, 'member');

    $teammatePersonal = $teammate->personalCalendar();
    Event::factory()->create([
        'calendar_id' => $teammatePersonal->id,
        'title' => 'Therapy appointment',
        'visibility' => EventVisibility::Private,
        'created_by' => $teammate->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('team_busy.0.occurrences.0.title', 'Busy')
            ->where('team_busy.0.occurrences.0.owner_name', 'Jane Smith')
        );
});

test('an admin does not see their own personal calendar in their own team-busy panel', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');

    $adminPersonal = $admin->personalCalendar();
    Event::factory()->create([
        'calendar_id' => $adminPersonal->id,
        'visibility' => EventVisibility::Private,
        'created_by' => $admin->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('team_busy.0.busy_count', 0));
});
