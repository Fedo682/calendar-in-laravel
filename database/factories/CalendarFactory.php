<?php

namespace Database\Factories;

use App\Models\Calendar;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calendar>
 */
class CalendarFactory extends Factory
{
    protected $model = Calendar::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'created_by' => User::factory(),
        ];
    }
}
