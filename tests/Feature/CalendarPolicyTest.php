<?php

use App\Models\Calendar;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use App\Policies\CalendarPolicy;

function makeGroupWithCalendarRole(?string $role): array
{
    $group = Group::factory()->create();
    $user = User::factory()->create();

    if ($role !== null) {
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role_id' => Role::firstOrCreate(['name' => $role])->id,
        ]);
    }

    return [$group, $user];
}

test('admin can view, create, update and delete calendars in their group', function () {
    [$group, $admin] = makeGroupWithCalendarRole('admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $policy = new CalendarPolicy();

    expect($policy->viewAny($admin, $group))->toBeTrue();
    expect($policy->view($admin, $calendar))->toBeTrue();
    expect($policy->create($admin, $group))->toBeTrue();
    expect($policy->update($admin, $calendar))->toBeTrue();
    expect($policy->delete($admin, $calendar))->toBeTrue();
});

test('member can view but not create, update or delete calendars', function () {
    [$group, $member] = makeGroupWithCalendarRole('member');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $policy = new CalendarPolicy();

    expect($policy->viewAny($member, $group))->toBeTrue();
    expect($policy->view($member, $calendar))->toBeTrue();
    expect($policy->create($member, $group))->toBeFalse();
    expect($policy->update($member, $calendar))->toBeFalse();
    expect($policy->delete($member, $calendar))->toBeFalse();
});

test('non-member cannot view, create, update or delete calendars', function () {
    [$group, $outsider] = makeGroupWithCalendarRole(null);
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $policy = new CalendarPolicy();

    expect($policy->viewAny($outsider, $group))->toBeFalse();
    expect($policy->view($outsider, $calendar))->toBeFalse();
    expect($policy->create($outsider, $group))->toBeFalse();
    expect($policy->update($outsider, $calendar))->toBeFalse();
    expect($policy->delete($outsider, $calendar))->toBeFalse();
});
