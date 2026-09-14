<?php

use App\Models\Calendar;
use App\Models\Group;
use App\Models\User;

test('personalCalendarIds includes the own personal calendar and member-group calendars only', function () {
    $myGroup = Group::factory()->create();
    $otherGroup = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($myGroup, $user, 'member');

    $myGroupCalendar = Calendar::factory()->create(['group_id' => $myGroup->id]);
    $otherGroupCalendar = Calendar::factory()->create(['group_id' => $otherGroup->id]);
    $personal = $user->personalCalendar();

    $ids = $user->personalCalendarIds();

    expect($ids->sort()->values()->all())->toBe(
        collect([$myGroupCalendar->id, $personal->id])->sort()->values()->all(),
    );
    expect($ids->contains($otherGroupCalendar->id))->toBeFalse();
});

test('personalCalendarIds has no Super Admin escalation', function () {
    $group = Group::factory()->create();
    $superAdmin = asSuperAdmin();
    $groupCalendar = Calendar::factory()->create(['group_id' => $group->id]);
    $personal = $superAdmin->personalCalendar();

    $ids = $superAdmin->personalCalendarIds();

    expect($ids->contains($groupCalendar->id))->toBeFalse();
    expect($ids->all())->toBe([$personal->id]);
});

test('scheduleCalendarIds equals personalCalendarIds for a non-Super-Admin', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'admin');
    Calendar::factory()->create(['group_id' => $group->id]);
    $user->personalCalendar();

    expect($user->scheduleCalendarIds()->sort()->values()->all())
        ->toBe($user->personalCalendarIds()->sort()->values()->all());
});

test('scheduleCalendarIds includes every group calendar for a Super Admin but no other personal calendar', function () {
    $groupA = Group::factory()->create();
    $groupB = Group::factory()->create();
    $superAdmin = asSuperAdmin();
    $calendarA = Calendar::factory()->create(['group_id' => $groupA->id]);
    $calendarB = Calendar::factory()->create(['group_id' => $groupB->id]);

    $otherUser = User::factory()->create();
    $otherPersonal = $otherUser->personalCalendar();

    $ids = $superAdmin->scheduleCalendarIds();

    expect($ids->contains($calendarA->id))->toBeTrue();
    expect($ids->contains($calendarB->id))->toBeTrue();
    expect($ids->contains($otherPersonal->id))->toBeFalse();
});

test('administeredGroups returns only groups the user administers', function () {
    $administered = Group::factory()->create();
    $notAdministered = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($administered, $admin, 'admin');
    asGroupRole($notAdministered, $admin, 'member');

    $groups = $admin->administeredGroups();

    expect($groups->pluck('id')->all())->toBe([$administered->id]);
});

test('administeredGroups returns every group for a Super Admin', function () {
    Group::factory()->count(3)->create();
    $superAdmin = asSuperAdmin();

    expect($superAdmin->administeredGroups())->toHaveCount(3);
});

test('teamBusyByGroup groups teammates personal calendars by administered group, excluding self', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    $memberOne = User::factory()->create();
    $memberTwo = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    asGroupRole($group, $memberOne, 'member');
    asGroupRole($group, $memberTwo, 'member');

    $adminPersonal = $admin->personalCalendar();
    $memberOnePersonal = $memberOne->personalCalendar();
    $memberTwoPersonal = $memberTwo->personalCalendar();

    $result = $admin->teamBusyByGroup();

    expect($result)->toHaveCount(1);
    expect($result[0]['group']->id)->toBe($group->id);

    $calendarIds = $result[0]['calendar_ids']->sort()->values()->all();
    expect($calendarIds)->toBe(
        collect([$memberOnePersonal->id, $memberTwoPersonal->id])->sort()->values()->all(),
    );
    expect($calendarIds)->not->toContain($adminPersonal->id);
});

test('teamBusyByGroup is empty for a member who administers nothing', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');

    expect($member->teamBusyByGroup())->toBeEmpty();
});
