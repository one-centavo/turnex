<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => '+573'.fake()->numerify('#########'),
            'password' => static::$password ??= Hash::make('password'),
            'google_id' => null,
            'avatar_url' => null,
            'email_verified_at' => null,
            'phone_verified_at' => now(),
        ];
    }

    /**
     * Indicate that the customer's phone number is unverified.
     */
    public function unverifiedPhone(): static
    {
        return $this->state(fn (array $attributes): array => [
            'phone_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the customer's email address is verified.
     */
    public function verifiedEmail(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the customer authenticated via Google OAuth.
     */
    public function withGoogle(string $googleId = 'google-123456789'): static
    {
        return $this->state(fn (array $attributes): array => [
            'google_id' => $googleId,
            'password' => null,
        ]);
    }
}
