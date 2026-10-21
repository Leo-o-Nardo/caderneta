<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'user_id' => User::factory(),
            'name' => 'Cartão '.fake()->word(),
            'closing_day' => 3,
            'due_day' => 10,
            'color' => '#4A6A9A',
        ];
    }
}
