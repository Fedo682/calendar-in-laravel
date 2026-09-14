<?php

use App\Enums\EventMessageType;
use App\Enums\EventVisibility;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\EventMessage;
use App\Models\Group;
use App\Models\User;

test('an admin sees both messages and reports for their group, correctly redacted', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create(['name' => 'Jane Smith']);
    asGroupRole($group, $admin, 'admin');
    asGroupRole($group, $member, 'member');

    $calendar = Calendar::factory()->create(['group_id' => $group->id]);

    $publicEvent = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'title' => 'Sprint planning',
        'visibility' => EventVisibility::Public,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);
    $privateEvent = Event::factory()->create([
        'calendar_id' => $calendar->id,
        'title' => 'Sensitive 1:1',
        'visibility' => EventVisibility::Private,
        'created_by' => $member->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
    ]);

    $message = EventMessage::create([
        'event_id' => $publicEvent->id,
        'sender_id' => $member->id,
        'type' => EventMessageType::General,
        'occurrence_start' => $publicEvent->starts_at,
        'body' => 'This slot never works for me.',
    ]);
    $report = EventMessage::create([
        'event_id' => $privateEvent->id,
        'sender_id' => $member->id,
        'type' => EventMessageType::Conflict,
        'occurrence_start' => $privateEvent->starts_at,
        'body' => 'Reported a scheduling conflict.',
        'conflicting_titles' => ['Client Call'],
    ]);

    $response = $this->actingAs($admin)->get("/groups/{$group->id}/messages");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Groups/Messages')
        ->has('messages', 1)
        ->has('reports', 1)
        ->where('messages.0.id', $message->id)
        ->where('messages.0.sender_name', 'Jane Smith')
        ->where('messages.0.event_title', 'Sprint planning')
        ->where('messages.0.resolved', false)
        ->where('reports.0.id', $report->id)
        // The reported event is private and the admin is neither its
        // creator nor the calendar's owner (group calendars have none) -
        // normal redaction still applies, per the approved design.
        ->where('reports.0.event_title', 'Busy')
        ->where('reports.0.conflicting_titles', ['Client Call'])
    );
});

test('a non-admin member cannot view the group messages inbox', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');

    $this->actingAs($member)
        ->get("/groups/{$group->id}/messages")
        ->assertForbidden();
});

test('a super admin can view any groups messages inbox', function () {
    $group = Group::factory()->create();
    $superAdmin = asSuperAdmin();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');

    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    EventMessage::create([
        'event_id' => $event->id,
        'sender_id' => $member->id,
        'type' => EventMessageType::General,
        'occurrence_start' => $event->starts_at,
        'body' => 'Hello',
    ]);

    $this->actingAs($superAdmin)
        ->get("/groups/{$group->id}/messages")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('messages', 1));
});

test('an admin can mark a message resolved and unresolved', function () {
    $group = Group::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    asGroupRole($group, $member, 'member');

    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $message = EventMessage::create([
        'event_id' => $event->id,
        'sender_id' => $member->id,
        'type' => EventMessageType::General,
        'occurrence_start' => $event->starts_at,
        'body' => 'Hello',
    ]);

    $this->actingAs($admin)
        ->patch("/groups/{$group->id}/messages/{$message->id}", ['resolved' => true])
        ->assertRedirect();

    expect($message->fresh()->isResolved())->toBeTrue();

    $this->actingAs($admin)
        ->patch("/groups/{$group->id}/messages/{$message->id}", ['resolved' => false])
        ->assertRedirect();

    expect($message->fresh()->isResolved())->toBeFalse();
});

test('a non-admin member cannot change a messages resolved status', function () {
    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');

    $calendar = Calendar::factory()->create(['group_id' => $group->id]);
    $event = Event::factory()->create(['calendar_id' => $calendar->id]);

    $message = EventMessage::create([
        'event_id' => $event->id,
        'sender_id' => $member->id,
        'type' => EventMessageType::General,
        'occurrence_start' => $event->starts_at,
        'body' => 'Hello',
    ]);

    $this->actingAs($member)
        ->patch("/groups/{$group->id}/messages/{$message->id}", ['resolved' => true])
        ->assertForbidden();
});

test('a message belonging to a different group 404s rather than leaking', function () {
    $groupA = Group::factory()->create();
    $groupB = Group::factory()->create();
    $adminA = User::factory()->create();
    asGroupRole($groupA, $adminA, 'admin');

    $calendarB = Calendar::factory()->create(['group_id' => $groupB->id]);
    $eventB = Event::factory()->create(['calendar_id' => $calendarB->id]);

    $message = EventMessage::create([
        'event_id' => $eventB->id,
        'sender_id' => User::factory()->create()->id,
        'type' => EventMessageType::General,
        'occurrence_start' => $eventB->starts_at,
        'body' => 'Hello',
    ]);

    $this->actingAs($adminA)
        ->patch("/groups/{$groupA->id}/messages/{$message->id}", ['resolved' => true])
        ->assertNotFound();
});
