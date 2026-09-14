<?php

use App\Enums\EventVisibility;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a user gets exactly one personal calendar', function () {
    $user = User::factory()->create();

    $first = $user->personalCalendar();
    $second = $user->personalCalendar();

    expect($first->id)->toBe($second->id)
        ->and($first->isPersonal())->toBeTrue()
        ->and($first->group_id)->toBeNull()
        ->and($first->owner_id)->toBe($user->id);
});

test('the database refuses a second personal calendar for the same user', function () {
    $user = User::factory()->create();
    $user->personalCalendar();

    // The unique index on owner_id is the actual guarantee; firstOrCreate is
    // only the convenient path to it.
    expect(fn () => Calendar::create([
        'type' => Calendar::TYPE_PERSONAL,
        'owner_id' => $user->id,
        'name' => 'Second',
        'created_by' => $user->id,
    ]))->toThrow(QueryException::class);
});

test('a member can create an event on their own calendar', function () {
    // Members are read-only on every group calendar; this is the first place
    // the role can write anything at all.
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');

    $this->actingAs($member)
        ->post('/calendars/personal/events', [
            'title' => 'Dentist',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
        ])
        ->assertRedirect(route('calendars.personal'))
        ->assertSessionHasNoErrors();

    $event = Event::where('title', 'Dentist')->firstOrFail();

    expect($event->calendar->isPersonal())->toBeTrue()
        ->and($event->calendar->owner_id)->toBe($member->id)
        // Personal calendars default their events to private rather than
        // inheriting the column default of public.
        ->and($event->visibility)->toBe(EventVisibility::Private);
});

test('an explicit visibility is not overridden by the calendar default', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->post('/calendars/personal/events', [
            'title' => 'Open house',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
            'visibility' => 'public',
        ])
        ->assertSessionHasNoErrors();

    expect(Event::where('title', 'Open house')->firstOrFail()->visibility)
        ->toBe(EventVisibility::Public);
});

test('a member can update and delete their own personal event', function () {
    $member = User::factory()->create();
    $calendar = $member->personalCalendar();

    $event = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'created_by' => $member->id,
        'title' => 'Gym',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $this->actingAs($member)
        ->put("/calendars/personal/events/{$event->id}", [
            'title' => 'Gym, moved',
            'starts_at' => now()->addDays(2)->toDateTimeString(),
            'ends_at' => now()->addDays(2)->addHour()->toDateTimeString(),
        ])
        ->assertSessionHasNoErrors();

    expect($event->fresh()->title)->toBe('Gym, moved');

    $this->actingAs($member)
        ->delete("/calendars/personal/events/{$event->id}")
        ->assertSessionHasNoErrors();

    expect(Event::find($event->id))->toBeNull();
});

test('nobody else can write to another users personal calendar', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $event = Event::factory()->create([
        'calendar_id' => $owner->personalCalendar()->id,
        'created_by' => $owner->id,
        'title' => 'Private thing',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $this->actingAs($intruder)
        ->put("/calendars/personal/events/{$event->id}", [
            'title' => 'Hijacked',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete("/calendars/personal/events/{$event->id}")
        ->assertForbidden();

    expect($event->fresh()->title)->toBe('Private thing');
});

test('a personal calendar cannot be deleted', function () {
    // The app assumes every user has exactly one, and deleting it would take
    // its events with it through the cascade.
    $user = User::factory()->create();

    expect($user->can('delete', $user->personalCalendar()))->toBeFalse();
});

test('the personal calendar page renders for its owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/calendars/personal')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Calendars/Personal')
            ->has('calendar')
            ->has('occurrences')
        );
});

test('personal calendars show up in the calendars a user can reach', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'member');

    $groupCalendar = Calendar::factory()->create(['group_id' => $group->id]);
    $personal = $user->personalCalendar();

    $ids = $user->accessibleCalendarIds();

    expect($ids)->toContain($groupCalendar->id)
        ->and($ids)->toContain($personal->id);
});

test('one users personal calendar is not reachable by another', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $personal = $owner->personalCalendar();

    expect($other->accessibleCalendarIds())->not->toContain($personal->id)
        ->and($other->can('view', $personal))->toBeFalse();
});
