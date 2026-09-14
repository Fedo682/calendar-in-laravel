<?php

use App\Enums\EventMessageType;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\EventMessage;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\QueryException;

test('an event message casts its type and stores conflicting titles as an array', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);
    $sender = User::factory()->create();

    $message = EventMessage::create([
        'event_id' => $event->id,
        'sender_id' => $sender->id,
        'type' => EventMessageType::Conflict->value,
        'occurrence_start' => now(),
        'body' => 'Reported a scheduling conflict.',
        'conflicting_titles' => ['Client Call'],
    ]);

    $message->refresh();

    expect($message->type)->toBe(EventMessageType::Conflict);
    expect($message->conflicting_titles)->toBe(['Client Call']);
    expect($message->event->id)->toBe($event->id);
    expect($message->sender->id)->toBe($sender->id);
});

test('the same sender cannot message about the same event and occurrence twice', function () {
    $group = Group::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);
    $sender = User::factory()->create();
    $occurrenceStart = now();

    EventMessage::create([
        'event_id' => $event->id,
        'sender_id' => $sender->id,
        'type' => EventMessageType::General->value,
        'occurrence_start' => $occurrenceStart,
        'body' => 'First message.',
    ]);

    expect(fn () => EventMessage::create([
        'event_id' => $event->id,
        'sender_id' => $sender->id,
        'type' => EventMessageType::Conflict->value,
        'occurrence_start' => $occurrenceStart,
        'body' => 'Reported a scheduling conflict.',
    ]))->toThrow(QueryException::class);
});
