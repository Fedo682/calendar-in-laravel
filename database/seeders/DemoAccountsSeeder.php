<?php

namespace Database\Seeders;

use App\Models\Calendar;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAccountsSeeder extends Seeder
{
    /**
     * Seed three accounts, one per role tier, so each can be tried by hand:
     *   - user1@example.com (Super Admin)
     *   - user2@example.com (Admin of "Demo Group")
     *   - user3@example.com (Member of "Demo Group")
     * All three use the password "password".
     */
    public function run(): void
    {
        // firstOrCreate() would skip UserFactory's default password hash,
        // so resolve-or-factory-create instead to guarantee every account
        // logs in with "password".
        $user1 = User::where('email', 'user1@example.com')->first()
            ?? User::factory()->create(['name' => 'User One', 'email' => 'user1@example.com']);

        $user2 = User::where('email', 'user2@example.com')->first()
            ?? User::factory()->create(['name' => 'User Two', 'email' => 'user2@example.com']);

        $user3 = User::where('email', 'user3@example.com')->first()
            ?? User::factory()->create(['name' => 'User Three', 'email' => 'user3@example.com']);

        $superAdminRoleId = Role::where('name', 'super_admin')->value('id');
        $user1->roles()->syncWithoutDetaching([$superAdminRoleId]);

        $group = Group::firstOrCreate(
            ['name' => 'Demo Group'],
            [
                'description' => 'A sample group for trying out roles and permissions.',
                'created_by' => $user1->id,
            ],
        );

        $adminRoleId = Role::where('name', 'admin')->value('id');
        $memberRoleId = Role::where('name', 'member')->value('id');

        GroupUser::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $user2->id],
            ['role_id' => $adminRoleId],
        );

        GroupUser::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $user3->id],
            ['role_id' => $memberRoleId],
        );

        $calendar = Calendar::firstOrCreate(
            ['group_id' => $group->id, 'name' => 'Team Calendar'],
            [
                'description' => 'Shared events for the demo group.',
                'color' => '#4f46e5',
                'created_by' => $user2->id,
            ],
        );

        $calendar->events()->firstOrCreate(
            ['title' => 'Sprint Planning'],
            [
                'description' => 'Kick off the next sprint.',
                'starts_at' => now()->addDay()->setTime(9, 0),
                'ends_at' => now()->addDay()->setTime(10, 0),
                'created_by' => $user2->id,
            ],
        );

        $calendar->events()->firstOrCreate(
            ['title' => 'Team Lunch'],
            [
                'description' => 'Casual team lunch.',
                'starts_at' => now()->addDays(3)->setTime(12, 0),
                'ends_at' => now()->addDays(3)->setTime(13, 0),
                'created_by' => $user2->id,
            ],
        );

        // A second calendar in the same group with an event that overlaps
        // Sprint Planning above, so the dashboard's conflict detection has
        // something real to show out of the box.
        $secondCalendar = Calendar::firstOrCreate(
            ['group_id' => $group->id, 'name' => 'Client Calendar'],
            [
                'description' => 'External meetings for the demo group.',
                'color' => '#ef4444',
                'created_by' => $user2->id,
            ],
        );

        $secondCalendar->events()->firstOrCreate(
            ['title' => 'Client Onboarding Call'],
            [
                'description' => 'Overlaps Sprint Planning on purpose - a demo conflict.',
                'starts_at' => now()->addDay()->setTime(9, 30),
                'ends_at' => now()->addDay()->setTime(10, 30),
                'created_by' => $user2->id,
            ],
        );
    }
}
