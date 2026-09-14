<?php

namespace Database\Factories;

use App\Enums\EventMessageType;
use App\Models\Event;
use App\Models\EventMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventMessage>
 */
class EventMessageFactory extends Factory
{
    protected $model = EventMessage::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'sender_id' => User::factory(),
            'type' => EventMessageType::General->value,
            'occurrence_start' => now(),
            'body' => fake()->sentence(),
        ];
    }
}
