<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the roles/permissions catalog and grant the bootstrap Super Admin.
     */
    public function run(): void
    {
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['description' => 'Platform-wide administrator: creates groups and assigns group admins.']
        );

        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Group administrator: manages that group\'s calendars, events, and members.']
        );

        $member = Role::firstOrCreate(
            ['name' => 'member'],
            ['description' => 'Group member: read-only access to that group\'s calendars and events.']
        );

        $permissions = collect([
            'groups.manage' => 'Create groups and assign group admins',
            'calendars.manage' => 'Create, edit, and delete calendars within a group',
            'calendars.view' => 'View calendars within a group',
            'events.manage' => 'Create, edit, and delete events within a calendar',
            'events.view' => 'View events within a calendar',
            'members.manage' => 'Add or remove members within a group',
        ])->map(fn (string $description, string $name) => Permission::firstOrCreate(
            ['name' => $name],
            ['description' => $description]
        ));

        $superAdmin->permissions()->sync($permissions->pluck('id'));

        $admin->permissions()->sync($permissions->only([
            'calendars.manage',
            'calendars.view',
            'events.manage',
            'events.view',
            'members.manage',
        ])->pluck('id'));

        $member->permissions()->sync($permissions->only([
            'calendars.view',
            'events.view',
        ])->pluck('id'));

        $bootstrapEmail = env('SUPER_ADMIN_EMAIL', 'test@example.com');
        $bootstrapUser = User::where('email', $bootstrapEmail)->first();

        if ($bootstrapUser) {
            $bootstrapUser->roles()->syncWithoutDetaching([$superAdmin->id]);
        }
    }
}
