<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offering>
 */
class OfferingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Offering>
     */
    protected $model = Offering::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'category_id' => Category::factory(),
            'name' => mb_substr(rtrim(fake()->sentence(fake()->numberBetween(2, 4)), '.'), 0, 50),
            'reference_image' => null,
            'description' => fake()->optional()->text(200),
            'price' => fake()->randomFloat(2, 5, 250),
            'estimated_duration_minutes' => fake()->numberBetween(15, 120),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the offering is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the offering is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the offering is free.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes): array => [
            'price' => 0.00,
        ]);
    }
}
