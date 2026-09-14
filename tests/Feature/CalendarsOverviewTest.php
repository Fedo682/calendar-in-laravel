<?php

use App\Models\Calendar;
use App\Models\Group;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The cross-group calendar list.
 *
 * This page crashed the moment personal calendars landed: it linked to
 * `/groups/{calendar.group.id}/...` for every row, and a personal calendar
 * has no group, so the whole page unmounted with "Cannot read properties of
 * null". The TypeScript interface had declared `group` non-null, so nothing
 * caught it - the type was simply wrong.
 *
 * These pin the server side of that contract. The client side is now held by
 * the shared CalendarSummary type, where `group` is nullable, so the old
 * dereference no longer compiles.
 */
test('the overview lists group calendars and the viewer own calendar', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'member');

    $groupCalendar = Calendar::factory()->create(['group_id' => $group->id]);
    $personal = $user->personalCalendar();

    $this->actingAs($user)
        ->get('/calendars')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Calendars/Overview'));

    $ids = collect(
        $this->actingAs($user)->get('/calendars')->original->getData()['page']['props']['calendars']
    )->pluck('id');

    expect($ids)->toContain($groupCalendar->id)
        ->and($ids)->toContain($personal->id);
});

test('a personal calendar is sent with a null group', function () {
    // The shape the page has to cope with. Asserted explicitly because a
    // regression here is invisible server-side - it only surfaces as a blank
    // screen in the browser.
    $user = User::factory()->create();
    $user->personalCalendar();

    $calendars = collect(
        $this->actingAs($user)->get('/calendars')->original->getData()['page']['props']['calendars']
    );

    $personal = $calendars->firstWhere('type', Calendar::TYPE_PERSONAL);

    expect($personal)->not->toBeNull()
        ->and($personal['group'])->toBeNull()
        ->and($personal['group_id'])->toBeNull();
});

test('a group calendar is sent with its group loaded', function () {
    $group = Group::factory()->create(['name' => 'Engineering']);
    $user = User::factory()->create();
    asGroupRole($group, $user, 'member');

    Calendar::factory()->create(['group_id' => $group->id]);

    $calendars = collect(
        $this->actingAs($user)->get('/calendars')->original->getData()['page']['props']['calendars']
    );

    $groupCalendar = $calendars->firstWhere('type', Calendar::TYPE_GROUP);

    expect($groupCalendar['group'])->not->toBeNull()
        ->and($groupCalendar['group']['name'])->toBe('Engineering');
});

test('the overview never exposes another user personal calendar', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $ownersPersonal = $owner->personalCalendar();
    $other->personalCalendar();

    $ids = collect(
        $this->actingAs($other)->get('/calendars')->original->getData()['page']['props']['calendars']
    )->pluck('id');

    expect($ids)->not->toContain($ownersPersonal->id);
});
