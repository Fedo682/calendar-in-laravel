<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the per-event visibility flag.
     *
     * Stored as a plain string rather than an `enum` column: MySQL's native
     * ENUM needs a table rebuild to gain a case, and SQLite (used by the test
     * suite) has no ENUM at all, so the set of allowed values would drift
     * between environments. `App\Enums\EventVisibility` is the single source
     * of truth and the model cast is what enforces it.
     *
     * The DB default is deliberately 'public' and stays that way even though
     * personal calendars default their events to 'private': the default here
     * is a backstop for rows inserted outside the request cycle, while the
     * per-calendar default is applied by Calendar::defaultEventVisibility()
     * in the store request, where the calendar is actually known.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('visibility', 16)->default('public')->after('all_day');

            // Paired with the existing (calendar_id, starts_at) index: the ICS
            // feed and the Google pusher both filter a calendar's events by
            // visibility before touching the date range.
            $table->index(['calendar_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['calendar_id', 'visibility']);
            $table->dropColumn('visibility');
        });
    }
};
