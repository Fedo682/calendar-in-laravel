<?php

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;

function makeRoles(): void
{
    foreach (['super_admin', 'admin', 'member'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
}

function makeSuperAdmin(): User
{
    makeRoles();
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'super_admin')->value('id'));

    return $user;
}

function addMember(Group $group, User $user, string $role): void
{
    GroupUser::updateOrCreate(
        ['group_id' => $group->id, 'user_id' => $user->id],
        ['role_id' => Role::where('name', $role)->value('id')],
    );
}

test('non super admin cannot create a group', function () {
    makeRoles();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/groups/create');
    $response->assertForbidden();

    $response = $this->actingAs($user)->post('/groups', [
        'name' => 'Acme',
        'description' => 'desc',
    ]);
    $response->assertForbidden();

    expect(Group::count())->toBe(0);
});

test('super admin can create a group', function () {
    $admin = makeSuperAdmin();

    $response = $this->actingAs($admin)->post('/groups', [
        'name' => 'Acme',
        'description' => 'desc',
    ]);

    $response->assertRedirect('/groups');
    expect(Group::where('name', 'Acme')->exists())->toBeTrue();
});

test('index shows all groups for super admin and only member groups for regular users', function () {
    $admin = makeSuperAdmin();
    $member = User::factory()->create();
    $stranger = User::factory()->create();

    $group1 = Group::create(['name' => 'G1', 'created_by' => $admin->id]);
    $group2 = Group::create(['name' => 'G2', 'created_by' => $admin->id]);
    addMember($group1, $member, 'member');

    $adminResponse = $this->actingAs($admin)->get('/groups');
    $adminResponse->assertOk();
    $adminResponse->assertInertia(fn ($page) => $page->has('groups', 2));

    $memberResponse = $this->actingAs($member)->get('/groups');
    $memberResponse->assertOk();
    $memberResponse->assertInertia(fn ($page) => $page->has('groups', 1));

    $strangerResponse = $this->actingAs($stranger)->get('/groups');
    $strangerResponse->assertOk();
    $strangerResponse->assertInertia(fn ($page) => $page->has('groups', 0));
});

test('only members of a group (or super admin) can view it', function () {
    $admin = makeSuperAdmin();
    $member = User::factory()->create();
    $stranger = User::factory()->create();

    $group = Group::create(['name' => 'G1', 'created_by' => $admin->id]);
    addMember($group, $member, 'member');

    $this->actingAs($member)->get("/groups/{$group->id}")->assertOk();
    $this->actingAs($admin)->get("/groups/{$group->id}")->assertOk();
    $this->actingAs($stranger)->get("/groups/{$group->id}")->assertForbidden();
});

test('non super admin cannot update or delete a group', function () {
    $admin = makeSuperAdmin();
    $groupAdmin = User::factory()->create();

    $group = Group::create(['name' => 'G1', 'created_by' => $admin->id]);
    addMember($group, $groupAdmin, 'admin');

    $this->actingAs($groupAdmin)
        ->put("/groups/{$group->id}", ['name' => 'New name'])
        ->assertForbidden();

    $this->actingAs($groupAdmin)
        ->delete("/groups/{$group->id}")
        ->assertForbidden();

    $this->actingAs($admin)
        ->put("/groups/{$group->id}", ['name' => 'New name'])
        ->assertRedirect();

    expect($group->fresh()->name)->toBe('New name');
});

test('group admin can add and remove members but cannot assign the admin role', function () {
    $superAdmin = makeSuperAdmin();
    $groupAdmin = User::factory()->create();
    $newMember = User::factory()->create();

    $group = Group::create(['name' => 'G1', 'created_by' => $superAdmin->id]);
    addMember($group, $groupAdmin, 'admin');

    // Group admin can add a regular member.
    $this->actingAs($groupAdmin)
        ->post("/groups/{$group->id}/members", [
            'user_id' => $newMember->id,
            'role' => 'member',
        ])
        ->assertRedirect();

    expect(GroupUser::where('group_id', $group->id)->where('user_id', $newMember->id)->exists())->toBeTrue();

    // Group admin cannot assign the admin role.
    $another = User::factory()->create();
    $this->actingAs($groupAdmin)
        ->post("/groups/{$group->id}/members", [
            'user_id' => $another->id,
            'role' => 'admin',
        ])
        ->assertForbidden();

    // Group admin can remove the regular member.
    $this->actingAs($groupAdmin)
        ->delete("/groups/{$group->id}/members/{$newMember->id}")
        ->assertRedirect();

    expect(GroupUser::where('group_id', $group->id)->where('user_id', $newMember->id)->exists())->toBeFalse();
});

test('only super admin can assign the admin role', function () {
    $superAdmin = makeSuperAdmin();
    $groupAdmin = User::factory()->create();
    $promotee = User::factory()->create();

    $group = Group::create(['name' => 'G1', 'created_by' => $superAdmin->id]);
    addMember($group, $groupAdmin, 'admin');
    addMember($group, $promotee, 'member');

    $this->actingAs($superAdmin)
        ->post("/groups/{$group->id}/members", [
            'user_id' => $promotee->id,
            'role' => 'admin',
        ])
        ->assertRedirect();

    expect(GroupUser::where('group_id', $group->id)->where('user_id', $promotee->id)->first()->role_id)
        ->toBe(Role::where('name', 'admin')->value('id'));
});

test('a non member cannot manage group membership', function () {
    $superAdmin = makeSuperAdmin();
    $stranger = User::factory()->create();
    $target = User::factory()->create();

    $group = Group::create(['name' => 'G1', 'created_by' => $superAdmin->id]);
    addMember($group, $target, 'member');

    $this->actingAs($stranger)
        ->post("/groups/{$group->id}/members", [
            'user_id' => $target->id,
            'role' => 'member',
        ])
        ->assertForbidden();

    $this->actingAs($stranger)
        ->delete("/groups/{$group->id}/members/{$target->id}")
        ->assertForbidden();
});

test('membership mutation 404s when the user is not actually a member of the group (IDOR guard)', function () {
    $superAdmin = makeSuperAdmin();
    $groupAdmin = User::factory()->create();
    $notAMember = User::factory()->create();

    $group = Group::create(['name' => 'G1', 'created_by' => $superAdmin->id]);
    addMember($group, $groupAdmin, 'admin');

    $this->actingAs($groupAdmin)
        ->delete("/groups/{$group->id}/members/{$notAMember->id}")
        ->assertNotFound();

    $this->actingAs($groupAdmin)
        ->put("/groups/{$group->id}/members/{$notAMember->id}", ['role' => 'member'])
        ->assertNotFound();
});
