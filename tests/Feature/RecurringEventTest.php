<?php

use App\Enums\EventVisibility;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

/**
 * Recurrence through the HTTP surface, rather than against the services.
 *
 * @return array{0: User, 1: Group, 2: Calendar}
 */
function recurringFixture(): array
{
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    return [$admin, $group, $calendar];
}

function eventsUrl(Group $group, Calendar $calendar): string
{
    return "/groups/{$group->id}/calendars/{$calendar->id}/events";
}

test('an admin can create a weekly series', function () {
    [$admin, $group, $calendar] = recurringFixture();

    $this->actingAs($admin)
        ->post(eventsUrl($group, $calendar), [
            'title' => 'Standup',
            'starts_at' => '2026-03-02 09:00:00',
            'ends_at' => '2026-03-02 09:30:00',
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
            'recurrence_timezone' => 'Europe/Berlin',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $event = Event::where('title', 'Standup')->firstOrFail();

    expect($event->recurrence_rule)->toBe('FREQ=WEEKLY;BYDAY=MO')
        ->and($event->recurrence_timezone)->toBe('Europe/Berlin')
        // Denormalised on save; null because the series is open-ended.
        ->and($event->recurrence_until)->toBeNull();
});

test('a recurring event expands into many occurrences on the index', function () {
    [$admin, $group, $calendar] = recurringFixture();

    Event::create([
        'calendar_id' => $calendar->id,
        'title' => 'Standup',
        'starts_at' => CarbonImmutable::parse('2026-03-02 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-03-02 09:30:00', 'UTC'),
        'visibility' => EventVisibility::Public,
        'created_by' => $admin->id,
        'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
        'recurrence_timezone' => 'UTC',
    ]);

    // One stored row; March 2026 has five Mondays.
    $this->actingAs($admin)
        ->get(eventsUrl($group, $calendar).'?from=2026-03-01&to=2026-04-01')
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Events/Index')
                ->has('occurrences', 5)
        );

    expect(Event::count())->toBe(1);
});

test('occurrences of one series carry distinct keys but the same event id', function () {
    [$admin, $group, $calendar] = recurringFixture();

    Event::create([
        'calendar_id' => $calendar->id,
        'title' => 'Standup',
        'starts_at' => CarbonImmutable::parse('2026-03-02 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-03-02 09:30:00', 'UTC'),
        'visibility' => EventVisibility::Public,
        'created_by' => $admin->id,
        'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
        'recurrence_timezone' => 'UTC',
    ]);

    $response = $this->actingAs($admin)
        ->get(eventsUrl($group, $calendar).'?from=2026-03-01&to=2026-04-01')
        ->assertOk();

    $occurrences = $response->original->getData()['page']['props']['occurrences'];
    $keys = array_column($occurrences, 'key');
    $ids = array_unique(array_column($occurrences, 'event_id'));

    // The key is what React must render on; the id is not unique here.
    expect($keys)->toHaveCount(5)
        ->and(array_unique($keys))->toHaveCount(5)
        ->and($ids)->toHaveCount(1);
});

test('editing one occurrence leaves the rest of the series alone', function () {
    [$admin, $group, $calendar] = recurringFixture();

    $master = Event::create([
        'calendar_id' => $calendar->id,
        'title' => 'Standup',
        'starts_at' => CarbonImmutable::parse('2026-03-02 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-03-02 09:30:00', 'UTC'),
        'visibility' => EventVisibility::Public,
        'created_by' => $admin->id,
        'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
        'recurrence_timezone' => 'UTC',
    ]);

    $this->actingAs($admin)
        ->put(eventsUrl($group, $calendar)."/{$master->id}", [
            'title' => 'Standup with guest',
            'starts_at' => '2026-03-16 09:00:00',
            'ends_at' => '2026-03-16 09:30:00',
            'scope' => 'this',
            'occurrence_start' => '2026-03-16T09:00:00+00:00',
        ])
        ->assertSessionHasNoErrors();

    expect($master->fresh()->title)->toBe('Standup')
        ->and(Event::where('recurrence_parent_id', $master->id)->count())->toBe(1);

    $response = $this->actingAs($admin)
        ->get(eventsUrl($group, $calendar).'?from=2026-03-01&to=2026-04-01');

    $titles = array_column(
        $response->original->getData()['page']['props']['occurrences'],
        'title',
    );

    expect($titles)->toContain('Standup with guest')
        ->and(array_count_values($titles)['Standup'])->toBe(4);
});

test('a scoped edit is rejected without an occurrence to scope it to', function () {
    [$admin, $group, $calendar] = recurringFixture();

    $master = Event::create([
        'calendar_id' => $calendar->id,
        'title' => 'Standup',
        'starts_at' => CarbonImmutable::parse('2026-03-02 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-03-02 09:30:00', 'UTC'),
        'visibility' => EventVisibility::Public,
        'created_by' => $admin->id,
        'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
        'recurrence_timezone' => 'UTC',
    ]);

    // "This occurrence" is unanswerable without saying which one.
    $this->actingAs($admin)
        ->put(eventsUrl($group, $calendar)."/{$master->id}", [
            'title' => 'Nope',
            'starts_at' => '2026-03-16 09:00:00',
            'ends_at' => '2026-03-16 09:30:00',
            'scope' => 'this',
        ])
        ->assertSessionHasErrors('occurrence_start');
});

test('deleting this and following trims the tail of the series', function () {
    [$admin, $group, $calendar] = recurringFixture();

    $master = Event::create([
        'calendar_id' => $calendar->id,
        'title' => 'Standup',
        'starts_at' => CarbonImmutable::parse('2026-03-02 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-03-02 09:30:00', 'UTC'),
        'visibility' => EventVisibility::Public,
        'created_by' => $admin->id,
        'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
        'recurrence_timezone' => 'UTC',
    ]);

    $this->actingAs($admin)
        ->delete(eventsUrl($group, $calendar)."/{$master->id}", [
            'scope' => 'following',
            'occurrence_start' => '2026-03-23T09:00:00+00:00',
        ])
        ->assertSessionHasNoErrors();

    // The row survives, capped - 03-02, 03-09 and 03-16 remain.
    expect($master->fresh())->not->toBeNull()
        ->and($master->fresh()->recurrence_rule)->toContain('UNTIL=');

    $response = $this->actingAs($admin)
        ->get(eventsUrl($group, $calendar).'?from=2026-03-01&to=2026-04-01');

    expect($response->original->getData()['page']['props']['occurrences'])->toHaveCount(3);
});

test('deleting the whole series removes it', function () {
    [$admin, $group, $calendar] = recurringFixture();

    $master = Event::create([
        'calendar_id' => $calendar->id,
        'title' => 'Standup',
        'starts_at' => CarbonImmutable::parse('2026-03-02 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-03-02 09:30:00', 'UTC'),
        'visibility' => EventVisibility::Public,
        'created_by' => $admin->id,
        'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
        'recurrence_timezone' => 'UTC',
    ]);

    $this->actingAs($admin)
        ->delete(eventsUrl($group, $calendar)."/{$master->id}", ['scope' => 'all'])
        ->assertSessionHasNoErrors();

    expect(Event::count())->toBe(0);
});

test('a rule without a timezone is rejected', function () {
    [$admin, $group, $calendar] = recurringFixture();

    // A rule anchored at 09:00 local has to know which local. Defaulting the
    // zone would silently produce the DST bug the column exists to prevent.
    $this->actingAs($admin)
        ->post(eventsUrl($group, $calendar), [
            'title' => 'Standup',
            'starts_at' => '2026-03-02 09:00:00',
            'ends_at' => '2026-03-02 09:30:00',
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
        ])
        ->assertSessionHasErrors('recurrence_timezone');
});

test('an expansion bomb is refused at the door', function () {
    [$admin, $group, $calendar] = recurringFixture();

    $this->actingAs($admin)
        ->post(eventsUrl($group, $calendar), [
            'title' => 'Nope',
            'starts_at' => '2026-03-02 09:00:00',
            'ends_at' => '2026-03-02 09:30:00',
            'recurrence_rule' => 'FREQ=SECONDLY',
            'recurrence_timezone' => 'UTC',
        ])
        ->assertSessionHasErrors('recurrence_rule');

    expect(Event::count())->toBe(0);
});

test('a member can create a recurring event on their own calendar', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->post('/calendars/personal/events', [
            'title' => 'Gym',
            'starts_at' => '2026-03-02 07:00:00',
            'ends_at' => '2026-03-02 08:00:00',
            'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR',
            'recurrence_timezone' => 'Europe/Berlin',
        ])
        ->assertSessionHasNoErrors();

    $event = Event::where('title', 'Gym')->firstOrFail();

    expect($event->calendar->isPersonal())->toBeTrue()
        ->and($event->recurrence_rule)->toBe('FREQ=WEEKLY;BYDAY=MO,WE,FR')
        // Personal calendars still default their events to private.
        ->and($event->visibility)->toBe(EventVisibility::Private);
});

test('a recurring private event shows as Busy to everyone else, on every occurrence', function () {
    [$admin, $group, $calendar] = recurringFixture();
    $other = User::factory()->create();
    asGroupRole($group, $other, 'admin');

    Event::create([
        'calendar_id' => $calendar->id,
        'title' => 'Weekly therapy',
        'starts_at' => CarbonImmutable::parse('2026-03-02 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-03-02 10:00:00', 'UTC'),
        'visibility' => EventVisibility::Private,
        'created_by' => $admin->id,
        'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
        'recurrence_timezone' => 'UTC',
    ]);

    $response = $this->actingAs($other)
        ->get(eventsUrl($group, $calendar).'?from=2026-03-01&to=2026-04-01')
        ->assertOk();

    $occurrences = $response->original->getData()['page']['props']['occurrences'];

    // Redaction has to survive expansion - every instance, not just the first.
    expect($occurrences)->toHaveCount(5);

    foreach ($occurrences as $occurrence) {
        expect($occurrence['title'])->toBe('Busy')
            ->and($occurrence['description'])->toBeNull()
            ->and($occurrence['is_redacted'])->toBeTrue();
    }

    expect($response->getContent())->not->toContain('Weekly therapy');
});
