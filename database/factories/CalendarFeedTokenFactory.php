<?php

namespace Database\Factories;

use App\Models\CalendarFeedToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarFeedToken>
 */
class CalendarFeedTokenFactory extends Factory
{
    protected $model = CalendarFeedToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', fake()->uuid()),
            'label' => null,
        ];
    }
}
