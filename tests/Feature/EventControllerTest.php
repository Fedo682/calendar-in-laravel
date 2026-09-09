<?php

use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function attachEventTestRole(Group $group, User $user, string $role): void
{
    GroupUser::updateOrCreate(
        ['group_id' => $group->id, 'user_id' => $user->id],
        ['role_id' => Role::firstOrCreate(['name' => $role])->id],
    );
}

test('group admin can view the events index', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $admin = User::factory()->create();
    attachEventTestRole($group, $admin, 'admin');

    $this->actingAs($admin)
        ->get("/groups/{$group->id}/calendars/{$calendar->id}/events")
        ->assertOk();
});

test('group member can view the events index', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $member = User::factory()->create();
    attachEventTestRole($group, $member, 'member');

    $this->actingAs($member)
        ->get("/groups/{$group->id}/calendars/{$calendar->id}/events")
        ->assertOk();
});

test('non-member is forbidden from viewing the events index', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get("/groups/{$group->id}/calendars/{$calendar->id}/events")
        ->assertForbidden();
});

test('group admin can create an event', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $admin = User::factory()->create();
    attachEventTestRole($group, $admin, 'admin');

    $response = $this->actingAs($admin)->post(
        "/groups/{$group->id}/calendars/{$calendar->id}/events",
        [
            'title' => 'Sprint Planning',
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ],
    );

    $response->assertRedirect("/groups/{$group->id}/calendars/{$calendar->id}/events");
    $this->assertDatabaseHas('events', [
        'calendar_id' => $calendar->id,
        'title' => 'Sprint Planning',
        'created_by' => $admin->id,
    ]);
});

test('event creation rejects an end time before the start time', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $admin = User::factory()->create();
    attachEventTestRole($group, $admin, 'admin');

    $this->actingAs($admin)->post(
        "/groups/{$group->id}/calendars/{$calendar->id}/events",
        [
            'title' => 'Bad Event',
            'starts_at' => '2026-10-01 10:00:00',
            'ends_at' => '2026-10-01 09:00:00',
        ],
    )->assertSessionHasErrors('ends_at');

    $this->assertDatabaseMissing('events', ['title' => 'Bad Event']);
});

test('group member cannot create an event', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $member = User::factory()->create();
    attachEventTestRole($group, $member, 'member');

    $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendar->id}/events",
        [
            'title' => 'Sprint Planning',
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ],
    )->assertForbidden();

    $this->assertDatabaseMissing('events', ['title' => 'Sprint Planning']);
});

test('group admin can update and delete an event', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $admin = User::factory()->create();
    attachEventTestRole($group, $admin, 'admin');
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $this->actingAs($admin)->put(
        "/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}",
        [
            'title' => 'Renamed',
            'starts_at' => $event->starts_at->toDateTimeString(),
            'ends_at' => $event->ends_at->toDateTimeString(),
        ],
    )->assertRedirect("/groups/{$group->id}/calendars/{$calendar->id}/events");
    $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Renamed']);

    $this->actingAs($admin)
        ->delete("/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}")
        ->assertRedirect("/groups/{$group->id}/calendars/{$calendar->id}/events");
    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

test('group member cannot update or delete an event', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $member = User::factory()->create();
    attachEventTestRole($group, $member, 'member');
    $event = Event::factory()->create(['calendar_id' => $calendar->id, 'title' => 'Original']);

    $this->actingAs($member)->put(
        "/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}",
        [
            'title' => 'Renamed',
            'starts_at' => $event->starts_at->toDateTimeString(),
            'ends_at' => $event->ends_at->toDateTimeString(),
        ],
    )->assertForbidden();

    $this->actingAs($member)
        ->delete("/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Original']);
});

test('an event from another calendar 404s via implicit scope binding', function () {
    $group = Group::factory()->create();
    $calendarA = Calendar::factory()->create(['group_id' => $group->id]);
    $calendarB = Calendar::factory()->create(['group_id' => $group->id]);
    $admin = User::factory()->create();
    attachEventTestRole($group, $admin, 'admin');
    $eventInB = Event::factory()->create(['calendar_id' => $calendarB->id]);

    $this->actingAs($admin)
        ->put(
            "/groups/{$group->id}/calendars/{$calendarA->id}/events/{$eventInB->id}",
            ['title' => 'x', 'starts_at' => now(), 'ends_at' => now()->addHour()],
        )
        ->assertNotFound();
});

test('super admin can manage events without explicit group membership', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $superAdmin = User::factory()->create();

    $roleId = Role::firstOrCreate(['name' => 'super_admin'])->id;
    DB::table('user_role')->insert([
        'user_id' => $superAdmin->id,
        'role_id' => $roleId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($superAdmin)->post(
        "/groups/{$group->id}/calendars/{$calendar->id}/events",
        [
            'title' => 'Super Admin Event',
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ],
    )->assertRedirect("/groups/{$group->id}/calendars/{$calendar->id}/events");

    $this->assertDatabaseHas('events', ['title' => 'Super Admin Event', 'calendar_id' => $calendar->id]);
});
