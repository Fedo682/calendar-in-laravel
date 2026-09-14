<?php

use App\Enums\EventVisibility;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

/** The props the dashboard page renders from. */
function dashboardProps(User $user, array $query = []): array
{
    $url = '/dashboard'.($query === [] ? '' : '?'.http_build_query($query));

    return test()->actingAs($user)->get($url)->original->getData()['page']['props'];
}

test('the dashboard sends a month of events across every calendar', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'admin');

    $groupCalendar = Calendar::factory()->create(['group_id' => $group->id]);
    $personal = $user->personalCalendar();

    foreach ([$groupCalendar->id, $personal->id] as $calendarId) {
        Event::factory()->create([
            'calendar_id' => $calendarId,
            'created_by' => $user->id,
            'starts_at' => CarbonImmutable::parse('2026-04-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-04-15 10:00:00', 'UTC'),
        ]);
    }

    // One grid, both calendars - that is the point of the dashboard view.
    $props = dashboardProps($user, ['month' => '2026-04']);

    expect($props['occurrences'])->toHaveCount(2)
        ->and($props['month'])->toBe('2026-04-01');
});

test('the grid window covers the leading and trailing days the grid renders', function () {
    // MonthGrid draws 42 cells from the Sunday on or before the 1st, so an
    // event in that leading week must arrive with the month even though it
    // falls outside it.
    $user = User::factory()->create();
    $personal = $user->personalCalendar();

    // 2026-04-01 is a Wednesday, so the grid starts Sunday 2026-03-29.
    Event::factory()->create([
        'calendar_id' => $personal->id,
        'created_by' => $user->id,
        'starts_at' => CarbonImmutable::parse('2026-03-30 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-03-30 10:00:00', 'UTC'),
    ]);

    expect(dashboardProps($user, ['month' => '2026-04'])['occurrences'])->toHaveCount(1);
});

test('a malformed month falls back to this month rather than erroring', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard?month=not-a-month')->assertOk();

    expect(dashboardProps($user, ['month' => 'not-a-month'])['month'])
        ->toBe(CarbonImmutable::now()->startOfMonth()->toDateString());
});

test('the agenda still covers only the next few days', function () {
    $user = User::factory()->create();
    $personal = $user->personalCalendar();

    Event::factory()->create([
        'calendar_id' => $personal->id,
        'created_by' => $user->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);
    Event::factory()->create([
        'calendar_id' => $personal->id,
        'created_by' => $user->id,
        'starts_at' => now()->addMonths(2),
        'ends_at' => now()->addMonths(2)->addHour(),
    ]);

    // The grid and the agenda ask different questions; only one of these is
    // "coming up".
    expect(dashboardProps($user)['upcoming_events'])->toHaveCount(1);
});

test('a private event is redacted in the grid as well as the agenda', function () {
    $group = Group::factory()->create();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    asGroupRole($group, $owner, 'admin');
    asGroupRole($group, $other, 'admin');

    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    Event::factory()->create([
        'calendar_id' => $calendar->id,
        'created_by' => $owner->id,
        'title' => 'Oncology appointment',
        'visibility' => EventVisibility::Private,
        'starts_at' => CarbonImmutable::parse('2026-04-15 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-04-15 10:00:00', 'UTC'),
    ]);

    $props = dashboardProps($other, ['month' => '2026-04']);

    expect($props['occurrences'][0]['title'])->toBe('Busy')
        ->and($props['occurrences'][0]['is_redacted'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// The calendar picker
// ---------------------------------------------------------------------------

test('the picker offers a members own calendar but no group calendar', function () {
    // Members are read-only on group calendars, so offering one would produce
    // a create the server then refuses.
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');
    Calendar::factory()->create(['group_id' => $group->id]);

    $writable = collect(dashboardProps($member)['writable_calendars']);

    expect($writable)->toHaveCount(1)
        ->and($writable->first()['type'])->toBe(Calendar::TYPE_PERSONAL)
        ->and($writable->first()['create_url'])->toBe(route('calendars.personal.events.store'));
});

test('the picker offers an admin their group calendars and their own', function () {
    $group = Group::factory()->create(['name' => 'Engineering']);
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $writable = collect(dashboardProps($admin)['writable_calendars']);

    expect($writable)->toHaveCount(2)
        ->and($writable->pluck('type')->all())
        ->toContain(Calendar::TYPE_PERSONAL, Calendar::TYPE_GROUP);

    $groupOption = $writable->firstWhere('type', Calendar::TYPE_GROUP);

    expect($groupOption['group_name'])->toBe('Engineering')
        ->and($groupOption['create_url'])
        ->toBe(route('groups.calendars.events.store', [$group->id, $calendar->id]));
});

test('the picker never offers another users personal calendar to a super admin', function () {
    // A Super Admin may administer every group calendar, but a personal one
    // belongs to its owner - and the create would be refused anyway.
    $superAdmin = asSuperAdmin();
    $other = User::factory()->create();
    $othersPersonal = $other->personalCalendar();

    $ids = collect(dashboardProps($superAdmin)['writable_calendars'])->pluck('id');

    expect($ids)->not->toContain($othersPersonal->id);
});

test('every offered calendar actually accepts a create', function () {
    // The guarantee that matters: the picker and the endpoint agree.
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    Calendar::factory()->create(['group_id' => $group->id]);

    foreach (dashboardProps($admin)['writable_calendars'] as $option) {
        $this->actingAs($admin)
            ->post($option['create_url'], [
                'title' => 'Created via '.$option['name'],
                'starts_at' => now()->addDay()->toDateTimeString(),
                'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
            ])
            ->assertSessionHasNoErrors();
    }

    expect(Event::count())->toBe(count(dashboardProps($admin)['writable_calendars']));
});

test('the dashboard renders', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('month')
            ->has('occurrences')
            ->has('upcoming_events')
            ->has('writable_calendars')
        );
});
