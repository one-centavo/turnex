<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Offering;
use App\Models\QueueTicket;
use App\Models\QueueTicketItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueTicketItem>
 */
class QueueTicketItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<QueueTicketItem>
     */
    protected $model = QueueTicketItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'queue_ticket_id' => QueueTicket::factory(),
            'offering_id' => Offering::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'unit_price' => fake()->randomFloat(2, 5, 100),
        ];
    }
}
