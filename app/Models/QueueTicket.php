<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QueueTicketStatus;
use Carbon\CarbonInterface;
use Database\Factories\QueueTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $customer_id
 * @property string $ticket_number
 * @property string $ticket_code
 * @property QueueTicketStatus $status
 * @property string $total_price
 * @property CarbonInterface|null $called_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $turns_ahead
 * @property-read Business $business
 * @property-read Customer $customer
 * @property-read Collection<int, QueueTicketItem> $items
 */
#[Fillable([
    'business_id',
    'customer_id',
    'ticket_number',
    'ticket_code',
    'status',
    'total_price',
    'called_at',
])]
class QueueTicket extends Model
{
    /** @use HasFactory<QueueTicketFactory> */
    use HasFactory;

    /**
     * The number of turns ahead in the queue (calculated dynamically).
     */
    public ?int $turns_ahead = null;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => QueueTicketStatus::Pending->value,
        'total_price' => 0.00,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QueueTicketStatus::class,
            'total_price' => 'decimal:2',
            'called_at' => 'datetime',
        ];
    }

    /**
     * Get the business that owns the ticket.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the customer that owns the ticket.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the items included in this ticket.
     *
     * @return HasMany<QueueTicketItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QueueTicketItem::class);
    }

    /**
     * Scope query to only include active tickets.
     *
     * @param  Builder<QueueTicket>  $query
     * @return Builder<QueueTicket>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', QueueTicketStatus::activeStatuses());
    }

    /**
     * Scope query to only include terminal tickets.
     *
     * @param  Builder<QueueTicket>  $query
     * @return Builder<QueueTicket>
     */
    public function scopeTerminal(Builder $query): Builder
    {
        return $query->whereIn('status', [
            QueueTicketStatus::Completed,
            QueueTicketStatus::NoShow,
            QueueTicketStatus::Cancelled,
        ]);
    }

    /**
     * Determine if the ticket is currently active in the queue.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Determine if the ticket has reached a terminal status.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }
}
