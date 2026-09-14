<?php

use App\Enums\EventVisibility;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use App\Support\Calendar\Occurrence;
use App\Support\Calendar\OccurrenceQuery;
use App\Support\Calendar\RecurrenceEditor;
use Carbon\CarbonImmutable;

/**
 * The six ways a series can be changed.
 *
 * These hit the database rather than value objects, because the interesting
 * behaviour is in what rows exist afterwards - the split in particular writes
 * a second master and reparents overrides across it.
 */

/**
 * A Monday-weekly series at 09:00 UTC starting 2026-03-02, one hour long.
 */
function weeklySeries(?string $rule = null, ?string $startsAt = null): Event
{
    $group = Group::factory()->create();
    $owner = User::factory()->create();
    asGroupRole($group, $owner, 'admin');

    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $start = CarbonImmutable::parse($startsAt ?? '2026-03-02 09:00:00', 'UTC');

    return Event::create([
        'calendar_id' => $calendar->id,
        'title' => 'Standup',
        'description' => 'Daily sync',
        'location' => 'Room 1',
        'starts_at' => $start,
        'ends_at' => $start->addHour(),
        'all_day' => false,
        'visibility' => EventVisibility::Public,
        'created_by' => $owner->id,
        'recurrence_rule' => $rule ?? 'FREQ=WEEKLY;BYDAY=MO',
        'recurrence_timezone' => 'UTC',
    ]);
}

function editor(): RecurrenceEditor
{
    return app(RecurrenceEditor::class);
}

/**
 * How many occurrences actually surface for a viewer across the window.
 *
 * Deliberately routed through OccurrenceQuery rather than the expander's
 * countBefore(), which counts instances the *rule* generates - it is there
 * for COUNT and UNTIL arithmetic during a split and does not subtract
 * EXDATEs. This asks the question the user would ask.
 */
function occurrenceCount(Event $master, string $from, string $to): int
{
    $viewer = User::findOrFail($master->created_by);

    // Scoped to this series: after a split the calendar holds two masters,
    // and counting the whole calendar would answer a different question.
    // An override belongs to the series whose parent it names.
    return app(OccurrenceQuery::class)->forCalendar(
        $master->calendar,
        CarbonImmutable::parse($from, 'UTC'),
        CarbonImmutable::parse($to, 'UTC'),
        $viewer,
    )->filter(fn (Occurrence $o) => $o->event->id === $master->id
        || $o->event->recurrenceParentId === $master->id
    )->count();
}

test('updateAll writes the master and leaves the series shape alone', function () {
    $master = weeklySeries();

    editor()->updateAll($master, ['title' => 'Renamed standup']);

    expect($master->fresh()->title)->toBe('Renamed standup')
        ->and($master->fresh()->recurrence_rule)->toBe('FREQ=WEEKLY;BYDAY=MO')
        ->and(Event::count())->toBe(1);
});

test('updateThisOccurrence writes a child row naming the instance it replaces', function () {
    $master = weeklySeries();
    $instance = CarbonImmutable::parse('2026-03-16 09:00:00', 'UTC');

    $override = editor()->updateThisOccurrence($master, $instance, ['title' => 'Guest speaker']);

    expect($override->recurrence_parent_id)->toBe($master->id)
        ->and(CarbonImmutable::parse($override->recurrence_id)->utc()->toDateTimeString())
        ->toBe('2026-03-16 09:00:00')
        ->and($override->title)->toBe('Guest speaker')
        // The master is untouched - the series still generates that instance,
        // the child just stands in front of it.
        ->and($master->fresh()->title)->toBe('Standup')
        ->and($master->fresh()->recurrence_rule)->toBe('FREQ=WEEKLY;BYDAY=MO');
});

test('editing the same occurrence twice updates the override rather than stacking them', function () {
    $master = weeklySeries();
    $instance = CarbonImmutable::parse('2026-03-16 09:00:00', 'UTC');

    editor()->updateThisOccurrence($master, $instance, ['title' => 'First edit']);
    editor()->updateThisOccurrence($master, $instance, ['title' => 'Second edit']);

    $overrides = Event::where('recurrence_parent_id', $master->id)->get();

    expect($overrides)->toHaveCount(1)
        ->and($overrides->first()->title)->toBe('Second edit');
});

test('deleteThisOccurrence appends an EXDATE instead of deleting the series', function () {
    $master = weeklySeries();
    $instance = CarbonImmutable::parse('2026-03-16 09:00:00', 'UTC');

    $before = occurrenceCount($master, '2026-03-01', '2026-04-01');

    editor()->deleteThisOccurrence($master, $instance);

    $master->refresh();

    expect($master->recurrence_exdates)->toHaveCount(1)
        ->and($master->exists)->toBeTrue()
        ->and(occurrenceCount($master, '2026-03-01', '2026-04-01'))->toBe($before - 1);
});

test('deleting an occurrence that has an override removes the override too', function () {
    $master = weeklySeries();
    $instance = CarbonImmutable::parse('2026-03-16 09:00:00', 'UTC');

    editor()->updateThisOccurrence($master, $instance, ['title' => 'Guest speaker']);
    editor()->deleteThisOccurrence($master, $instance);

    // Leaving the child behind would resurrect the instance the user just
    // removed, because the expander renders overrides in their own right.
    expect(Event::where('recurrence_parent_id', $master->id)->count())->toBe(0);
});

test('deleteThisAndFollowing caps the series with UNTIL rather than deleting rows', function () {
    $master = weeklySeries();
    $splitAt = CarbonImmutable::parse('2026-03-23 09:00:00', 'UTC');

    editor()->deleteThisAndFollowing($master, $splitAt);

    $master->refresh();

    expect($master->recurrence_rule)->toContain('UNTIL=')
        // 2026-03-02, 03-09 and 03-16 survive; 03-23 onward is gone.
        ->and(occurrenceCount($master, '2026-03-01', '2026-04-01'))->toBe(3)
        ->and(occurrenceCount($master, '2026-03-23', '2026-06-01'))->toBe(0);
});

test('deleteAll removes the master and its overrides', function () {
    $master = weeklySeries();
    editor()->updateThisOccurrence(
        $master,
        CarbonImmutable::parse('2026-03-16 09:00:00', 'UTC'),
        ['title' => 'Guest speaker'],
    );

    expect(Event::count())->toBe(2);

    editor()->deleteAll($master);

    expect(Event::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// The split. This is the hard one.
// ---------------------------------------------------------------------------

test('updateThisAndFollowing caps the head and starts a second series at the split', function () {
    $master = weeklySeries();
    $splitAt = CarbonImmutable::parse('2026-03-23 09:00:00', 'UTC');

    $tail = editor()->updateThisAndFollowing($master, $splitAt, ['title' => 'New format']);
    $master->refresh();

    expect($tail->id)->not->toBe($master->id)
        ->and($tail->title)->toBe('New format')
        ->and($tail->recurrence_parent_id)->toBeNull()
        ->and($tail->recurrence_id)->toBeNull()
        ->and(CarbonImmutable::parse($tail->starts_at)->utc()->toDateTimeString())
        ->toBe('2026-03-23 09:00:00')
        // The head keeps its original title and stops before the split.
        ->and($master->title)->toBe('Standup')
        ->and($master->recurrence_rule)->toContain('UNTIL=');

    expect(occurrenceCount($master, '2026-03-01', '2026-06-01'))->toBe(3)
        ->and(occurrenceCount($tail, '2026-03-23', '2026-04-20'))->toBeGreaterThan(0);
});

test('the tail preserves the duration of the original', function () {
    $master = weeklySeries();

    $tail = editor()->updateThisAndFollowing(
        $master,
        CarbonImmutable::parse('2026-03-23 09:00:00', 'UTC'),
        ['title' => 'New format'],
    );

    $length = CarbonImmutable::parse($tail->starts_at)->diffInMinutes(CarbonImmutable::parse($tail->ends_at));

    expect((int) $length)->toBe(60);
});

test('splitting at the first instance rewrites the master instead of leaving a dead head', function () {
    $master = weeklySeries();

    // "This and following" from the very start means "all events"; a head
    // series capped before its own first instance would generate nothing.
    $result = editor()->updateThisAndFollowing(
        $master,
        CarbonImmutable::parse('2026-03-02 09:00:00', 'UTC'),
        ['title' => 'Renamed from the start'],
    );

    expect($result->id)->toBe($master->id)
        ->and(Event::count())->toBe(1)
        ->and($master->fresh()->title)->toBe('Renamed from the start');
});

test('overrides move to whichever side of the split generated them', function () {
    $master = weeklySeries();

    $before = editor()->updateThisOccurrence(
        $master,
        CarbonImmutable::parse('2026-03-09 09:00:00', 'UTC'),
        ['title' => 'Before the split'],
    );
    $after = editor()->updateThisOccurrence(
        $master,
        CarbonImmutable::parse('2026-03-30 09:00:00', 'UTC'),
        ['title' => 'After the split'],
    );

    $tail = editor()->updateThisAndFollowing(
        $master,
        CarbonImmutable::parse('2026-03-23 09:00:00', 'UTC'),
        ['title' => 'New format'],
    );

    expect($before->fresh()->recurrence_parent_id)->toBe($master->id)
        ->and($after->fresh()->recurrence_parent_id)->toBe($tail->id);
});

test('an override dragged backwards across the split follows its RECURRENCE-ID, not its new time', function () {
    $master = weeklySeries();

    // The instance of 2026-03-30 moved back to the 20th - earlier than the
    // split - but it still stands in for an instance the tail generates.
    // Keying the move off starts_at would send it to the wrong series.
    $override = editor()->updateThisOccurrence(
        $master,
        CarbonImmutable::parse('2026-03-30 09:00:00', 'UTC'),
        [
            'title' => 'Moved earlier',
            'starts_at' => CarbonImmutable::parse('2026-03-20 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-03-20 10:00:00', 'UTC'),
        ],
    );

    $tail = editor()->updateThisAndFollowing(
        $master,
        CarbonImmutable::parse('2026-03-23 09:00:00', 'UTC'),
        ['title' => 'New format'],
    );

    expect($override->fresh()->recurrence_parent_id)->toBe($tail->id);
});

test('the split can change the cadence from that point onward', function () {
    $master = weeklySeries();

    $tail = editor()->updateThisAndFollowing(
        $master,
        CarbonImmutable::parse('2026-03-23 09:00:00', 'UTC'),
        ['recurrence_rule' => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO'],
    );

    expect($tail->recurrence_rule)->toBe('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO')
        // The head keeps the original cadence, capped.
        ->and($master->fresh()->recurrence_rule)->toStartWith('FREQ=WEEKLY;BYDAY=MO');
});

test('EXDATEs are partitioned across the split', function () {
    $master = weeklySeries();

    editor()->deleteThisOccurrence($master, CarbonImmutable::parse('2026-03-09 09:00:00', 'UTC'));
    editor()->deleteThisOccurrence($master, CarbonImmutable::parse('2026-03-30 09:00:00', 'UTC'));

    $tail = editor()->updateThisAndFollowing(
        $master->fresh(),
        CarbonImmutable::parse('2026-03-23 09:00:00', 'UTC'),
        ['title' => 'New format'],
    );

    // Each exclusion belongs to the series that would otherwise generate it;
    // leaving both on the head would resurrect the later one.
    expect($master->fresh()->recurrence_exdates)->toHaveCount(1)
        ->and($tail->recurrence_exdates)->toHaveCount(1);
});

test('the editor refuses a non-recurring event rather than silently accepting it', function () {
    // The three-way scope question is meaningless for a one-off, and the
    // controllers only route here once an event actually has a rule. Failing
    // loudly beats quietly treating a plain update as a series operation.
    $group = Group::factory()->create();
    $owner = User::factory()->create();
    asGroupRole($group, $owner, 'admin');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $event = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'created_by' => $owner->id,
        'title' => 'One off',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    expect(fn () => editor()->updateAll($event, ['title' => 'Still one off']))
        ->toThrow(InvalidArgumentException::class);
});
