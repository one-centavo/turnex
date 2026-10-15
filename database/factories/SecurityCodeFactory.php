<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactType;
use App\Models\SecurityCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<SecurityCode>
 */
class SecurityCodeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SecurityCode>
     */
    protected $model = SecurityCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_value' => fake()->safeEmail(),
            'contact_type' => ContactType::Email,
            'code' => Hash::make(sprintf('%06d', fake()->numberBetween(0, 999999))),
            'attempts_count' => 0,
            'expires_at' => now()->addMinutes(5),
            'used_at' => null,
        ];
    }

    /**
     * Indicate that the security code is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * Indicate that the security code has already been used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes): array => [
            'used_at' => now(),
        ]);
    }

    /**
     * Indicate that the security code has reached the maximum allowed attempts.
     */
    public function maxAttempts(): static
    {
        return $this->state(fn (array $attributes): array => [
            'attempts_count' => 5,
        ]);
    }

    /**
     * Indicate that the security code target is an SMS phone number.
     */
    public function sms(?string $phone = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'contact_type' => ContactType::Sms,
            'contact_value' => $phone ?? '+573001234567',
        ]);
    }

    /**
     * Indicate that the security code target is a WhatsApp phone number.
     */
    public function whatsapp(?string $phone = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'contact_type' => ContactType::WhatsApp,
            'contact_value' => $phone ?? '+573001234567',
        ]);
    }

    /**
     * Indicate that the security code target is an email address.
     */
    public function email(?string $email = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'contact_type' => ContactType::Email,
            'contact_value' => $email ?? fake()->safeEmail(),
        ]);
    }
}
