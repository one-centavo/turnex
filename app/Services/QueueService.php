<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QueueTicketStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Offering;
use App\Models\QueueTicket;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class QueueService
{
    public const int MAX_ITEM_QUANTITY = 50;

    /**
     * Generate the next daily sequential ticket number for the business (e.g. TK-001).
     */
    public function generateTicketNumber(Business $business): string
    {
        $todayStart = Carbon::today();

        /** @var QueueTicket|null $lastTicket */
        $lastTicket = QueueTicket::query()
            ->where('business_id', $business->id)
            ->where('created_at', '>=', $todayStart)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $nextSequence = 1;

        if ($lastTicket !== null && preg_match('/TK-(\d+)/', $lastTicket->ticket_number, $matches) === 1) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return sprintf('TK-%03d', $nextSequence);
    }

    /**
     * Generate a unique, secure URL-safe ticket code.
     */
    public function generateTicketCode(): string
    {
        do {
            $code = Str::random(32);
        } while (QueueTicket::query()->where('ticket_code', $code)->exists());

        return $code;
    }

    /**
     * Retrieve the active queue ticket for the given customer at the specified business.
     */
    public function getActiveTicketForCustomer(Business $business, Customer $customer): ?QueueTicket
    {
        /** @var QueueTicket|null */
        return QueueTicket::query()
            ->where('business_id', $business->id)
            ->where('customer_id', $customer->id)
            ->active()
            ->first();
    }

    /**
     * Create a new queue ticket for the given customer and business with offerings.
     *
     * @param  list<array{offering_id: int, quantity: int}>  $items
     *
     * @throws DomainException
     * @throws Throwable
     */
    public function createTicket(Business $business, Customer $customer, array $items): QueueTicket
    {
        if ($items === []) {
            throw new DomainException('Ticket must contain at least one item.');
        }

        /** @var QueueTicket */
        return DB::transaction(function () use ($business, $customer, $items): QueueTicket {
            if ($this->getActiveTicketForCustomer($business, $customer) !== null) {
                throw new DomainException('Customer already has an active ticket for this business.');
            }

            $offeringIds = array_column($items, 'offering_id');
            $uniqueOfferingIds = array_values(array_unique($offeringIds));

            /** @var Collection<int, Offering> $offerings */
            $offerings = Offering::query()
                ->where('business_id', $business->id)
                ->where('is_active', true)
                ->whereIn('id', $uniqueOfferingIds)
                ->get()
                ->keyBy('id');

            if ($offerings->count() !== count($uniqueOfferingIds)) {
                throw new DomainException('One or more selected offerings are invalid, inactive, or do not belong to this business.');
            }

            $totalPrice = 0.0;
            $ticketItemsData = [];

            foreach ($items as $item) {
                $quantity = $item['quantity'];
                if ($quantity < 1) {
                    throw new DomainException('Offering quantity must be at least 1.');
                }

                if ($quantity > self::MAX_ITEM_QUANTITY) {
                    throw new DomainException('Offering quantity cannot exceed '.self::MAX_ITEM_QUANTITY.'.');
                }

                $offering = $offerings->get($item['offering_id']);
                if ($offering === null) {
                    throw new DomainException('Offering not found.');
                }

                $unitPrice = (float) $offering->price;
                $totalPrice += $unitPrice * $quantity;

                $ticketItemsData[] = [
                    'offering_id' => $offering->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ];
            }

            /** @var QueueTicket $ticket */
            $ticket = QueueTicket::query()->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'ticket_number' => $this->generateTicketNumber($business),
                'ticket_code' => $this->generateTicketCode(),
                'status' => QueueTicketStatus::Pending,
                'total_price' => round($totalPrice, 2),
            ]);

            foreach ($ticketItemsData as $itemData) {
                $ticket->items()->create($itemData);
            }

            /** @var QueueTicket */
            return $ticket->load(['items.offering', 'business', 'customer']);
        });
    }

    /**
     * Calculate the number of pending tickets ahead of the given ticket in the queue.
     */
    public function calculateQueuePosition(QueueTicket $ticket): int
    {
        if ($ticket->status !== QueueTicketStatus::Pending || ! $ticket->exists) {
            return 0;
        }

        return QueueTicket::query()
            ->where('business_id', $ticket->business_id)
            ->where('status', QueueTicketStatus::Pending)
            ->where(function (Builder $query) use ($ticket): void {
                $query->where('created_at', '<', $ticket->created_at)
                    ->orWhere(function (Builder $subQuery) use ($ticket): void {
                        $subQuery->where('created_at', $ticket->created_at)
                            ->where('id', '<', $ticket->id);
                    });
            })
            ->count();
    }

    /**
     * Voluntarily cancel an active queue ticket owned by the given customer.
     *
     * @throws DomainException
     */
    public function cancelTicket(QueueTicket $ticket, Customer $customer): bool
    {
        if ($ticket->customer_id !== $customer->id) {
            throw new DomainException('You are not authorized to cancel this ticket.');
        }

        if (! $ticket->isActive()) {
            throw new DomainException('Ticket cannot be cancelled because it is already in a terminal state.');
        }

        $ticket->status = QueueTicketStatus::Cancelled;

        return $ticket->save();
    }

    /**
     * Retrieve all active queue tickets for the given customer sorted by urgency.
     *
     * @return Collection<int, QueueTicket>
     */
    public function getActiveTicketsForCustomer(Customer $customer): Collection
    {
        /** @var Collection<int, QueueTicket> $tickets */
        $tickets = QueueTicket::query()
            ->active()
            ->where('customer_id', $customer->id)
            ->with([
                'business',
                'items.offering' => function ($query): void {
                    $query->withTrashed();
                },
            ])
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->turns_ahead = $this->calculateQueuePosition($ticket);
        }

        /** @var Collection<int, QueueTicket> */
        return $tickets->sort(function (QueueTicket $a, QueueTicket $b): int {
            $tierA = $this->getUrgencyTier($a);
            $tierB = $this->getUrgencyTier($b);

            if ($tierA !== $tierB) {
                return $tierA <=> $tierB;
            }

            if ($tierA === 1) {
                $subA = $a->status === QueueTicketStatus::Called ? 1 : 2;
                $subB = $b->status === QueueTicketStatus::Called ? 1 : 2;

                if ($subA !== $subB) {
                    return $subA <=> $subB;
                }

                return $a->id <=> $b->id;
            }

            if ($tierA === 2) {
                $turnsA = $a->turns_ahead ?? 0;
                $turnsB = $b->turns_ahead ?? 0;

                if ($turnsA !== $turnsB) {
                    return $turnsA <=> $turnsB;
                }

                $statusPriority = fn (QueueTicketStatus $status): int => match ($status) {
                    QueueTicketStatus::InService => 1,
                    QueueTicketStatus::Preparing => 2,
                    QueueTicketStatus::Pending => 3,
                    default => 4,
                };

                $prioA = $statusPriority($a->status);
                $prioB = $statusPriority($b->status);

                if ($prioA !== $prioB) {
                    return $prioA <=> $prioB;
                }

                return $a->id <=> $b->id;
            }

            return $a->id <=> $b->id;
        })->values();
    }

    /**
     * Retrieve paginated historical queue tickets for the given customer.
     *
     * @return LengthAwarePaginator<int, QueueTicket>
     */
    public function getHistoricalTicketsForCustomer(Customer $customer, int $perPage = 10): LengthAwarePaginator
    {
        return QueueTicket::query()
            ->terminal()
            ->where('customer_id', $customer->id)
            ->with([
                'business',
                'items.offering' => function ($query): void {
                    $query->withTrashed();
                },
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Determine the urgency tier for an active ticket.
     *
     * Tier 1: Actionable states (Called, Ready)
     * Tier 2: In-progress and waiting states (InService, Preparing, Pending)
     * Tier 3: Postponed (Skipped)
     */
    private function getUrgencyTier(QueueTicket $ticket): int
    {
        return match ($ticket->status) {
            QueueTicketStatus::Called, QueueTicketStatus::Ready => 1,
            QueueTicketStatus::InService, QueueTicketStatus::Preparing, QueueTicketStatus::Pending => 2,
            QueueTicketStatus::Skipped => 3,
            default => 4,
        };
    }
}
