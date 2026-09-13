<?php

use App\Mail\ConflictReportedMail;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('reporting a real conflict emails the group admins', function () {
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

    $response = $this->actingAs($member)->post(
        "/groups/{$group->id}/calendars/{$calendarA->id}/events/{$eventA->id}/report-conflict",
    );

    $response->assertRedirect();
    Mail::assertQueued(ConflictReportedMail::class, function ($mail) use ($admin) {
        return $mail->hasTo($admin->email) && $mail->conflictingTitles->contains('Client Call');
    });
});

test('reporting a non-existent conflict does not send mail', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $this->actingAs($member)
        ->post("/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}/report-conflict")
        ->assertRedirect();

    Mail::assertNothingQueued();
});

test('a non-member cannot report a conflict', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $outsider = User::factory()->create();
    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $this->actingAs($outsider)
        ->post("/groups/{$group->id}/calendars/{$calendar->id}/events/{$event->id}/report-conflict")
        ->assertForbidden();

    Mail::assertNothingQueued();
});
