<?php

use App\Enums\EventMessageType;
use App\Mail\EventMessageMail;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\EventMessage;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('a member can send a message to the group admins about a team event', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    asGroupRole($group, $member, 'member');

    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $response = $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}/message",
        ['body' => 'This slot never works for me.'],
    );

    $response->assertRedirect();
    expect(EventMessage::count())->toBe(1);
    expect(EventMessage::first()->type)->toBe(EventMessageType::General);
    expect(EventMessage::first()->body)->toBe('This slot never works for me.');

    Mail::assertQueued(EventMessageMail::class, function ($mail) use ($admin) {
        return $mail->hasTo($admin->email) && $mail->body === 'This slot never works for me.';
    });
});

test('a second message about the same event and occurrence is blocked', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}/message",
        ['body' => 'First message.'],
    );

    Mail::fake();

    $response = $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}/message",
        ['body' => 'Second message.'],
    );

    $response->assertSessionHas('error');
    expect(EventMessage::count())->toBe(1);
    Mail::assertNothingQueued();
});

test('a body is required', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $this->actingAs($member)
        ->post("/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}/message", ['body' => ''])
        ->assertSessionHasErrors('body');
});

test('a non-member cannot message about an event', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $outsider = User::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $this->actingAs($outsider)
        ->post("/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}/message", ['body' => 'Hi'])
        ->assertForbidden();

    Mail::assertNothingQueued();
});

test('a message and a conflict report about the same occurrence block each other', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    asGroupRole($group, $member, 'member');

    $calendarA = Calendar::factory()->create(['group_id' => $group->id]);
    $calendarB = Calendar::factory()->create(['group_id' => $group->id]);

    $start = now()->addDay()->setTime(10, 0);
    $eventA = Event::factory()->create([
        'calendar_id' => $calendarA->id,
        'title' => 'Standup',
        'starts_at' => $start,
        'ends_at' => (clone $start)->addHour(),
    ]);
    Event::factory()->create([
        'calendar_id' => $calendarB->id,
        'title' => 'Client Call',
        'starts_at' => (clone $start)->addMinutes(30),
        'ends_at' => (clone $start)->addMinutes(90),
    ]);

    // Message first.
    $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendarA->id}/events/{$eventA->id}/message",
        ['body' => 'This clashes with something.'],
    );

    Mail::fake();

    // Reporting the same occurrence's conflict afterward is blocked.
    $response = $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendarA->id}/events/{$eventA->id}/report-conflict",
    );

    $response->assertSessionHas('error');
    expect(EventMessage::count())->toBe(1);
    Mail::assertNothingQueued();
});
