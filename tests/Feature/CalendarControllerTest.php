<?php

use App\Models\Calendar;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('group admin can view the calendars index', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');

    $this->actingAs($admin)
        ->get("/groups/{$group->id}/calendars")
        ->assertOk();
});

test('group member can view the calendars index', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');

    $this->actingAs($member)
        ->get("/groups/{$group->id}/calendars")
        ->assertOk();
});

test('non-member is forbidden from viewing the calendars index', function () {
    $group = Group::factory()->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get("/groups/{$group->id}/calendars")
        ->assertForbidden();
});

test('group admin can create a calendar', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');

    $response = $this->actingAs($admin)->post("/groups/{$group->id}/calendars", [
        'name' => 'Engineering',
        'description' => 'Eng team calendar',
        'color' => '#4f46e5',
    ]);

    $response->assertRedirect("/groups/{$group->id}/calendars");

    $this->assertDatabaseHas('calendars', [
        'group_id' => $group->id,
        'name' => 'Engineering',
        'created_by' => $admin->id,
    ]);
});

test('group member cannot create a calendar', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');

    $response = $this->actingAs($member)->post("/groups/{$group->id}/calendars", [
        'name' => 'Engineering',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('calendars', ['name' => 'Engineering']);
});

test('group admin can update a calendar', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $response = $this->actingAs($admin)->put("/groups/{$group->id}/calendars/{$calendar->id}", [
        'name' => 'Renamed',
        'description' => null,
        'color' => null,
    ]);

    $response->assertRedirect("/groups/{$group->id}/calendars");
    $this->assertDatabaseHas('calendars', ['id' => $calendar->id, 'name' => 'Renamed']);
});

test('group member cannot update a calendar', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');
    $calendar = Calendar::factory()->create(['group_id' => $group->id, 'name' => 'Original']);

    $response = $this->actingAs($member)->put("/groups/{$group->id}/calendars/{$calendar->id}", [
        'name' => 'Renamed',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('calendars', ['id' => $calendar->id, 'name' => 'Original']);
});

test('group admin can delete a calendar', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $response = $this->actingAs($admin)->delete("/groups/{$group->id}/calendars/{$calendar->id}");

    $response->assertRedirect("/groups/{$group->id}/calendars");
    $this->assertDatabaseMissing('calendars', ['id' => $calendar->id]);
});

test('group member cannot delete a calendar', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $response = $this->actingAs($member)->delete("/groups/{$group->id}/calendars/{$calendar->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('calendars', ['id' => $calendar->id]);
});

test('a calendar from another group 404s via implicit scope binding', function () {
    $groupA = Group::factory()->create();
    $groupB = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($groupA, $admin, 'admin');
    asGroupRole($groupB, $admin, 'admin');
    $calendarInB = Calendar::factory()->create(['group_id' => $groupB->id]);

    $this->actingAs($admin)
        ->put("/groups/{$groupA->id}/calendars/{$calendarInB->id}", ['name' => 'x'])
        ->assertNotFound();
});

test('super admin can manage calendars without explicit group membership', function () {
    $group = Group::factory()->create();
    $superAdmin = User::factory()->create();

    $roleId = Role::firstOrCreate(['name' => 'super_admin'])->id;
    DB::table('user_role')->insert([
        'user_id' => $superAdmin->id,
        'role_id' => $roleId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($superAdmin)->post("/groups/{$group->id}/calendars", [
        'name' => 'Super Admin Calendar',
    ]);

    $response->assertRedirect("/groups/{$group->id}/calendars");
    $this->assertDatabaseHas('calendars', ['name' => 'Super Admin Calendar', 'group_id' => $group->id]);
});
