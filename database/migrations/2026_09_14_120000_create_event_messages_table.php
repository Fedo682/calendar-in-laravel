<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16);
            // Always the resolved occurrence's actual start instant, never
            // null - even for a one-off event, this stores that event's own
            // starts_at. MySQL/MariaDB unique indexes treat NULL as distinct
            // from every other NULL, which would silently defeat the dedup
            // constraint below for every non-recurring event if this were
            // nullable.
            $table->dateTime('occurrence_start');
            $table->text('body');
            // Only populated for type='conflict'; null for a general message.
            $table->json('conflicting_titles')->nullable();
            $table->timestamps();

            // One message per sender per occurrence, regardless of type - a
            // member who already reported a conflict on this instance is
            // blocked from also sending a general message about it, and vice
            // versa.
            $table->unique(['event_id', 'sender_id', 'occurrence_start'], 'event_messages_dedup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_messages');
    }
};
