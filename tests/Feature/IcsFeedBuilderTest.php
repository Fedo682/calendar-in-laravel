<?php

use App\Enums\EventVisibility;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use App\Support\Calendar\IcsFeedBuilder;
use Sabre\VObject\Reader;

test('the output parses as valid ICS', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    Event::factory()->create([
        'calendar_id' => $calendar->id,
        'title' => 'Sprint planning',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $ics = app(IcsFeedBuilder::class)->build($user);

    expect(fn () => Reader::read($ics))->not->toThrow(Exception::class);
    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->toContain('SUMMARY:Sprint planning')
        ->not->toContain('METHOD:');
});

test('PRODID appears exactly once', function () {
    // VCalendar's own constructor already defaults a PRODID in - adding a
    // second one via add() appends rather than replacing, which is invalid
    // per RFC 5545 (PRODID must appear exactly once) and was only caught by
    // fetching the feed over real HTTP and reading the raw output by hand.
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    Event::factory()->create(['calendar_id' => $calendar->id]);

    $ics = app(IcsFeedBuilder::class)->build($user);

    expect(substr_count($ics, 'PRODID:'))->toBe(1);
});

test('a private event is redacted in the feed', function () {
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
        'description' => 'Results review',
        'location' => 'St Thomas Hospital',
        'visibility' => EventVisibility::Private,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    $ics = app(IcsFeedBuilder::class)->build($other);

    expect($ics)->not->toContain('Oncology appointment')
        ->not->toContain('Results review')
        ->not->toContain('St Thomas Hospital')
        ->toContain('SUMMARY:Busy')
        ->toContain('CLASS:CONFIDENTIAL');
});

test('a recurring series is one VEVENT carrying an RRULE, not one per occurrence', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    Event::factory()->create([
        'calendar_id' => $calendar->id,
        'title' => 'Weekly standup',
        'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
        'recurrence_timezone' => 'Europe/Berlin',
        'starts_at' => now()->addWeek()->startOfWeek(),
        'ends_at' => now()->addWeek()->startOfWeek()->addMinutes(30),
    ]);

    $ics = app(IcsFeedBuilder::class)->build($user);
    $vcalendar = Reader::read($ics);

    expect($vcalendar->select('VEVENT'))->toHaveCount(1);
    expect($ics)->toContain('RRULE:FREQ=WEEKLY;BYDAY=MO')
        ->toContain('TZID=Europe/Berlin');
});

test('an all-day events DTEND is one day past its own last day', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    Event::factory()->create([
        'calendar_id' => $calendar->id,
        'title' => 'Company holiday',
        'all_day' => true,
        'starts_at' => '2026-12-25 00:00:00',
        'ends_at' => '2026-12-25 23:59:59',
    ]);

    $ics = app(IcsFeedBuilder::class)->build($user);

    expect($ics)->toContain('DTSTART;VALUE=DATE:20261225')
        ->toContain('DTEND;VALUE=DATE:20261226');
});

test('a one-off events instants are encoded in UTC', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    Event::factory()->create([
        'calendar_id' => $calendar->id,
        'title' => 'One-off sync',
        'starts_at' => '2026-09-20 09:00:00',
        'ends_at' => '2026-09-20 10:00:00',
    ]);

    $ics = app(IcsFeedBuilder::class)->build($user);

    expect($ics)->toContain('DTSTART:20260920T090000Z');
});

test('an override appears as its own VEVENT with a RECURRENCE-ID matching its masters UID', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    asGroupRole($group, $user, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $master = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'title' => 'Weekly standup',
        'recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO',
        'recurrence_timezone' => 'Europe/Berlin',
        'starts_at' => now()->addWeek()->startOfWeek(),
        'ends_at' => now()->addWeek()->startOfWeek()->addMinutes(30),
    ]);
    $overrideStart = $master->starts_at->copy();
    Event::factory()->create([
        'calendar_id' => $calendar->id,
        'title' => 'Weekly standup (moved)',
        'recurrence_parent_id' => $master->id,
        'recurrence_id' => $overrideStart,
        'starts_at' => $overrideStart->copy()->addHour(),
        'ends_at' => $overrideStart->copy()->addHour()->addMinutes(30),
    ]);

    $ics = app(IcsFeedBuilder::class)->build($user);
    $vcalendar = Reader::read($ics);
    $vevents = $vcalendar->select('VEVENT');

    expect($vevents)->toHaveCount(2);

    $uids = array_map(fn ($v) => (string) $v->UID, $vevents);
    expect($uids[0])->toBe($uids[1]);

    $override = array_values(array_filter($vevents, fn ($v) => isset($v->{'RECURRENCE-ID'})))[0];
    expect((string) $override->SUMMARY)->toBe('Weekly standup (moved)');
});
