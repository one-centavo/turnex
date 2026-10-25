<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BusinessCategory;
use App\Enums\VerificationStatus;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
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
            'reviewed_by' => null,
            'business_name' => fake()->company(),
            'business_category' => fake()->randomElement(BusinessCategory::cases()),
            'owner_first_name' => fake()->firstName(),
            'owner_middle_name' => fake()->optional()->firstName(),
            'owner_last_name' => fake()->lastName(),
            'owner_second_last_name' => fake()->optional()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => '+573'.fake()->numerify('#########'),
            'location' => fake()->address(),
            'logo_path' => null,
            'cover_photo_path' => null,
            'business_description' => fake()->optional()->sentence(),
            'verification_status' => VerificationStatus::PendingVerification,
            'rejection_reason' => null,
            'email_verified_at' => null,
            'phone_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'reviewed_at' => null,
        ];
    }

    /**
     * Indicate that the business's phone number is unverified.
     */
    public function unverifiedPhone(): static
    {
        return $this->state(fn (array $attributes): array => [
            'phone_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the business's email address is verified.
     */
    public function verifiedEmail(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the business is under review.
     */
    public function underReview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => VerificationStatus::UnderReview,
        ]);
    }

    /**
     * Indicate that the business is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => VerificationStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate that action is required from the business.
     */
    public function actionRequired(?string $reason = 'Document re-submission required.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => VerificationStatus::ActionRequired,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Indicate that the business verification has been rejected.
     */
    public function rejected(?string $reason = 'Business verification application rejected.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => VerificationStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
        ]);
    }
}
