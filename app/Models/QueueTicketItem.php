<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QueueTicketItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $queue_ticket_id
 * @property int $offering_id
 * @property int $quantity
 * @property string $unit_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read QueueTicket $queueTicket
 * @property-read Offering $offering
 * @property-read float $subtotal
 */
#[Fillable([
    'queue_ticket_id',
    'offering_id',
    'quantity',
    'unit_price',
])]
class QueueTicketItem extends Model
{
    /** @use HasFactory<QueueTicketItemFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'unit_price' => 0.00,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    /**
     * Get the ticket that owns this item.
     *
     * @return BelongsTo<QueueTicket, $this>
     */
    public function queueTicket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class);
    }

    /**
     * Get the offering associated with this item.
     *
     * @return BelongsTo<Offering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    /**
     * Calculate the subtotal for this item.
     */
    public function subtotal(): float
    {
        return (float) ($this->quantity * (float) $this->unit_price);
    }

    /**
     * Get the subtotal attribute.
     */
    public function getSubtotalAttribute(): float
    {
        return $this->subtotal();
    }
}
