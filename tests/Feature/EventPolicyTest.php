<?php

use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use App\Policies\EventPolicy;

function makeCalendarWithEventRole(?string $role): array
{
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $user = User::factory()->create();

    if ($role !== null) {
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role_id' => Role::firstOrCreate(['name' => $role])->id,
        ]);
    }

    return [$calendar, $user];
}

test('admin can view, create, update and delete events on calendars in their group', function () {
    [$calendar, $admin] = makeCalendarWithEventRole('admin');
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);
    $policy = new EventPolicy();

    expect($policy->viewAny($admin, $calendar))->toBeTrue();
    expect($policy->view($admin, $event))->toBeTrue();
    expect($policy->create($admin, $calendar))->toBeTrue();
    expect($policy->update($admin, $event))->toBeTrue();
    expect($policy->delete($admin, $event))->toBeTrue();
});

test('member can view but not create, update or delete events', function () {
    [$calendar, $member] = makeCalendarWithEventRole('member');
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);
    $policy = new EventPolicy();

    expect($policy->viewAny($member, $calendar))->toBeTrue();
    expect($policy->view($member, $event))->toBeTrue();
    expect($policy->create($member, $calendar))->toBeFalse();
    expect($policy->update($member, $event))->toBeFalse();
    expect($policy->delete($member, $event))->toBeFalse();
});

test('non-member cannot view, create, update or delete events', function () {
    [$calendar, $outsider] = makeCalendarWithEventRole(null);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);
    $policy = new EventPolicy();

    expect($policy->viewAny($outsider, $calendar))->toBeFalse();
    expect($policy->view($outsider, $event))->toBeFalse();
    expect($policy->create($outsider, $calendar))->toBeFalse();
    expect($policy->update($outsider, $event))->toBeFalse();
    expect($policy->delete($outsider, $event))->toBeFalse();
});
