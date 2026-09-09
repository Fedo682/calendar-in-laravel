<?php

namespace Database\Factories;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+1 month');
        $end = (clone $start)->modify('+1 hour');

        return [
            'calendar_id' => Calendar::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'location' => fake()->optional()->city(),
            'starts_at' => $start,
            'ends_at' => $end,
            'all_day' => false,
            'created_by' => User::factory(),
        ];
    }
}
