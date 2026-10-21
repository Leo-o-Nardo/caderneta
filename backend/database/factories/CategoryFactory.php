<?php

namespace Database\Factories;

use App\Enums\CategoryKind;
use App\Models\Category;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'kind' => CategoryKind::Expense,
            'name' => ucfirst(fake()->unique()->word()),
            'icon' => 'dots',
            'color' => '#7D786D',
        ];
    }

    public function income(): static
    {
        return $this->state(['kind' => CategoryKind::Income]);
    }
}
