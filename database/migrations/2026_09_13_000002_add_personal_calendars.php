<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give every user a calendar that belongs to no group.
     *
     * `calendars.group_id` has to become nullable for that, which on every
     * supported driver means dropping the foreign key first, changing the
     * column, then putting the key back. Each step is its own Schema::table
     * call: SQLite rebuilds the whole table for a ->change(), and folding the
     * drop/change/add into one closure makes it rebuild from a definition
     * that still carries the constraint it is in the middle of removing.
     */
    public function up(): void
    {
        Schema::table('calendars', function (Blueprint $table) {
            $table->string('type', 16)->default('group')->after('id');
            $table->foreignId('owner_id')->nullable()->after('group_id')
                ->constrained('users')->cascadeOnDelete();
            $table->index('type');
            // NULLs are exempt from UNIQUE, so this enforces one personal
            // calendar per user without affecting the many group calendars
            // where owner_id IS NULL.
            $table->unique('owner_id');
        });

        Schema::table('calendars', fn (Blueprint $table) => $table->dropForeign(['group_id']));
        Schema::table('calendars', fn (Blueprint $table) => $table->foreignId('group_id')->nullable()->change());
        Schema::table('calendars', fn (Blueprint $table) => $table->foreign('group_id')
            ->references('id')->on('groups')->cascadeOnDelete());

        $this->backfillPersonalCalendars();
    }

    /**
     * Reverse the migrations.
     *
     * Personal calendars cannot survive a non-nullable group_id, so they are
     * deleted before the column is tightened back up. Their events go with
     * them through the existing ON DELETE CASCADE.
     */
    public function down(): void
    {
        DB::table('calendars')->whereNull('group_id')->delete();

        Schema::table('calendars', fn (Blueprint $table) => $table->dropForeign(['group_id']));
        Schema::table('calendars', fn (Blueprint $table) => $table->foreignId('group_id')->nullable(false)->change());
        Schema::table('calendars', fn (Blueprint $table) => $table->foreign('group_id')
            ->references('id')->on('groups')->cascadeOnDelete());

        Schema::table('calendars', function (Blueprint $table) {
            $table->dropUnique(['owner_id']);
            $table->dropIndex(['type']);
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn('type');
        });
    }

    /**
     * Create the "Personal" calendar every existing user is entitled to.
     *
     * Written with the query builder rather than the Calendar model on
     * purpose: a migration has to keep working after the model's fillable
     * list, casts or observers have moved on.
     */
    private function backfillPersonalCalendars(): void
    {
        $now = now();

        DB::table('users')
            ->whereNotIn('id', DB::table('calendars')->whereNotNull('owner_id')->pluck('owner_id'))
            ->orderBy('id')
            ->chunk(500, function ($users) use ($now) {
                DB::table('calendars')->insert(
                    collect($users)->map(fn ($user) => [
                        'type' => 'personal',
                        'group_id' => null,
                        'owner_id' => $user->id,
                        'name' => 'Personal',
                        'description' => 'Your private calendar. Only you can see what is on it.',
                        'color' => '#6366f1',
                        'created_by' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }
};
