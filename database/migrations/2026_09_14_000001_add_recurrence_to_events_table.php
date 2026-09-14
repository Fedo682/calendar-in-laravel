<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recurrence, stored as the raw RRULE string rather than normalised into
     * columns.
     *
     * A normalised schema cannot express BYSETPOS or rule sets, needs a
     * serialiser anyway, and every boundary this data crosses - the ICS feed,
     * Google's recurrence[] array - speaks raw RRULE. Normalising would mean
     * translating three times, and the three translations would drift.
     *
     * recurrence_timezone is not decoration. A rule anchored at 09:00
     * Europe/Berlin must stay at 09:00 across a DST transition, and that
     * cannot be recovered from a UTC instant alone: expanding from the UTC
     * value alone silently moves every occurrence after the transition by an
     * hour. This column is the fix for the single most common recurrence bug.
     *
     * recurrence_until is denormalised on save by EventObserver - the last
     * possible occurrence *end*, or NULL for an infinite series. It is what
     * makes "does this series touch the requested window" expressible in SQL,
     * so the candidate fetch stays one indexed query instead of loading every
     * recurring row and expanding it in PHP to find out.
     *
     * Per-occurrence overrides ("this event only") are child rows carrying
     * recurrence_parent_id plus recurrence_id - the *original* start instant
     * of the occurrence they replace. That mirrors iCalendar's RECURRENCE-ID
     * and Google's recurringEventId + originalStartTime 1:1, so the later
     * boundary crossings are passthroughs rather than conversions.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('recurrence_rule', 512)->nullable()->after('visibility');
            $table->string('recurrence_timezone', 64)->nullable()->after('recurrence_rule');
            $table->json('recurrence_exdates')->nullable()->after('recurrence_timezone');
            $table->json('recurrence_rdates')->nullable()->after('recurrence_exdates');
            $table->dateTime('recurrence_until')->nullable()->after('recurrence_rdates');
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_until')
                ->constrained('events')->cascadeOnDelete();
            $table->dateTime('recurrence_id')->nullable()->after('recurrence_parent_id');

            // Serves the recurring half of the candidate predicate: a series
            // is a candidate for a window when it starts before the window
            // ends and has not already finished before the window begins.
            $table->index(['calendar_id', 'recurrence_until']);

            // Serves the second query - fetching the overrides belonging to
            // the masters just found - and the point lookup that resolves one
            // named instance.
            $table->index(['recurrence_parent_id', 'recurrence_id']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['recurrence_parent_id', 'recurrence_id']);
            $table->dropIndex(['calendar_id', 'recurrence_until']);
            $table->dropForeign(['recurrence_parent_id']);
            $table->dropColumn([
                'recurrence_rule',
                'recurrence_timezone',
                'recurrence_exdates',
                'recurrence_rdates',
                'recurrence_until',
                'recurrence_parent_id',
                'recurrence_id',
            ]);
        });
    }
};
