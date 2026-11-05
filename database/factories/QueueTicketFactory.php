<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QueueTicketStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\QueueTicket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QueueTicket>
 */
class QueueTicketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<QueueTicket>
     */
    protected $model = QueueTicket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'customer_id' => Customer::factory(),
            'ticket_number' => 'TK-'.str_pad((string) fake()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'ticket_code' => Str::lower(Str::random(16)),
            'status' => QueueTicketStatus::Pending,
            'total_price' => 0.00,
            'called_at' => null,
        ];
    }

    /**
     * Set a specific status on the ticket.
     */
    public function status(QueueTicketStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    /**
     * Indicate that the ticket is pending.
     */
    public function pending(): static
    {
        return $this->status(QueueTicketStatus::Pending);
    }

    /**
     * Indicate that the ticket is preparing.
     */
    public function preparing(): static
    {
        return $this->status(QueueTicketStatus::Preparing);
    }

    /**
     * Indicate that the ticket is ready.
     */
    public function ready(): static
    {
        return $this->status(QueueTicketStatus::Ready);
    }

    /**
     * Indicate that the ticket was called.
     */
    public function called(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => QueueTicketStatus::Called,
            'called_at' => now(),
        ]);
    }

    /**
     * Indicate that the ticket is in service.
     */
    public function inService(): static
    {
        return $this->status(QueueTicketStatus::InService);
    }

    /**
     * Indicate that the ticket was skipped.
     */
    public function skipped(): static
    {
        return $this->status(QueueTicketStatus::Skipped);
    }

    /**
     * Indicate that the ticket is completed.
     */
    public function completed(): static
    {
        return $this->status(QueueTicketStatus::Completed);
    }

    /**
     * Indicate that the ticket was marked as no show.
     */
    public function noShow(): static
    {
        return $this->status(QueueTicketStatus::NoShow);
    }

    /**
     * Indicate that the ticket was cancelled.
     */
    public function cancelled(): static
    {
        return $this->status(QueueTicketStatus::Cancelled);
    }
}
