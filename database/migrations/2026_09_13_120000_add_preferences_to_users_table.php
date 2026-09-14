<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user display preferences.
 *
 * Timestamps stay in UTC everywhere (config/app.php keeps 'timezone' => 'UTC');
 * these columns only decide how a stored instant is rendered for one viewer -
 * in the UI now, and in the subscribable ICS feed later, where a wrong zone
 * would show every event at the wrong hour on the subscriber's phone.
 *
 * All four are NOT NULL with a default, so existing rows come out of the
 * migration with the same preferences a freshly registered user gets.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC')->after('email_verified_at');
            $table->string('theme', 10)->default('system')->after('timezone');        // system|light|dark
            $table->unsignedTinyInteger('week_starts_on')->default(0)->after('theme'); // 0=Sunday
            $table->string('time_format', 4)->default('12h')->after('week_starts_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'theme', 'week_starts_on', 'time_format']);
        });
    }
};
