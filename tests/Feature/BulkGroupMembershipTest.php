<?php

use App\Mail\AddedToGroupMail;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('group admin can bulk add members by email and each gets queued a notification', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');

    $alice = User::factory()->create(['email' => 'alice@example.com']);
    $bob = User::factory()->create(['email' => 'bob@example.com']);

    $response = $this->actingAs($admin)->post("/groups/{$group->id}/members/bulk", [
        'emails' => "alice@example.com, bob@example.com\nghost@nowhere.com",
        'role' => 'member',
    ]);

    $response->assertRedirect();

    expect(GroupUser::where('group_id', $group->id)->where('user_id', $alice->id)->exists())->toBeTrue();
    expect(GroupUser::where('group_id', $group->id)->where('user_id', $bob->id)->exists())->toBeTrue();

    // AddedToGroupMail implements ShouldQueue, so Mail::fake() buckets it
    // under "queued" rather than "sent" - assertQueued is the correct
    // assertion here (assertSent would check the synchronous-send bucket,
    // which stays empty for a queued mailable).
    Mail::assertQueued(AddedToGroupMail::class, 2);
    Mail::assertQueued(AddedToGroupMail::class, function ($mail) use ($alice) {
        return $mail->hasTo($alice->email);
    });
});

test('bulk add reports emails with no matching account without failing the request', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');

    $response = $this->actingAs($admin)->post("/groups/{$group->id}/members/bulk", [
        'emails' => 'ghost@nowhere.com',
        'role' => 'member',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', function ($message) {
        return str_contains($message, '0 member(s) added') && str_contains($message, 'ghost@nowhere.com');
    });

    Mail::assertNothingQueued();
});

test('group member cannot bulk add members', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $member = User::factory()->create();
    asGroupRole($group, $member, 'member');
    $target = User::factory()->create(['email' => 'target@example.com']);

    $this->actingAs($member)
        ->post("/groups/{$group->id}/members/bulk", [
            'emails' => 'target@example.com',
            'role' => 'member',
        ])
        ->assertForbidden();

    expect(GroupUser::where('group_id', $group->id)->where('user_id', $target->id)->exists())->toBeFalse();
    Mail::assertNothingQueued();
});

test('group admin cannot bulk assign the admin role', function () {
    Mail::fake();

    $group = Group::factory()->create();
    $admin = User::factory()->create();
    asGroupRole($group, $admin, 'admin');
    $target = User::factory()->create(['email' => 'target@example.com']);

    $this->actingAs($admin)
        ->post("/groups/{$group->id}/members/bulk", [
            'emails' => 'target@example.com',
            'role' => 'admin',
        ])
        ->assertForbidden();

    Mail::assertNothingQueued();
});
