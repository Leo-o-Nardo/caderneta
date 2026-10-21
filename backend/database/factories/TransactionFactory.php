<?php

namespace Database\Factories;

use App\Enums\CategoryKind;
use App\Enums\PaymentType;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Para os testes. Passe sempre a casa e uma categoria dela: a categoria padrão da factory é
 * criada numa casa nova, só para o registro ser válido.
 *
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'created_by' => User::factory(),
            'kind' => CategoryKind::Expense,
            'amount_cents' => fake()->numberBetween(100, 50000),
            'description' => ucfirst(fake()->words(2, true)),
            'occurred_on' => now()->toDateString(),
            'category_id' => Category::factory(),
            'payment_type' => PaymentType::Pix,
            'visibility' => 'household',
            'source' => 'app',
        ];
    }
}
