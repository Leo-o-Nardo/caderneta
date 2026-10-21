<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * A senha usada pela factory, calculada uma vez só.
     */
    protected static ?string $password;

    /**
     * Estado padrão do modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Nos testes, as contas já nascem liberadas (a aprovação tem testes próprios).
            'approved_at' => now(),
        ];
    }

    /** Uma conta que se cadastrou e ainda espera a liberação. */
    public function pending(): static
    {
        return $this->state(fn () => ['approved_at' => null, 'rejected_at' => null]);
    }

    /**
     * Usuário com o e-mail ainda não verificado.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
