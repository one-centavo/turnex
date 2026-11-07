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
        if ($business->isQueuePaused()) {
            throw new DomainException('This business has temporarily paused its queue and is not accepting new turns.');
        }

        if ($items === []) {
            throw new DomainException('Ticket must contain at least one item.');
        }

        /** @var QueueTicket */
        return DB::transaction(function () use ($business, $customer, $items): QueueTicket {
            /** @var Business|null $lockedBusiness */
            $lockedBusiness = Business::query()->where('id', $business->id)->lockForUpdate()->first();
            if ($lockedBusiness !== null && $lockedBusiness->isQueuePaused()) {
                throw new DomainException('This business has temporarily paused its queue and is not accepting new turns.');
            }

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
     * Retrieve the active ticket currently being served or called in the counter spotlight.
     * Priority: IN_SERVICE first (actively attended), then CALLED.
     */
    public function getCurrentSpotlightTicket(Business $business): ?QueueTicket
    {
        /** @var QueueTicket|null */
        return QueueTicket::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [QueueTicketStatus::InService, QueueTicketStatus::Called])
            ->with([
                'customer',
                'items.offering' => fn ($query) => $query->withTrashed(),
            ])
            ->orderByRaw('CASE WHEN status = ? THEN 1 WHEN status = ? THEN 2 ELSE 3 END', [
                QueueTicketStatus::InService->value,
                QueueTicketStatus::Called->value,
            ])
            ->orderBy('called_at', 'asc')
            ->orderBy('id', 'asc')
            ->first();
    }

    /**
     * Call the next waiting customer in chronological order (FIFO).
     */
    public function callNextTicket(Business $business): ?QueueTicket
    {
        return DB::transaction(function () use ($business): ?QueueTicket {
            /** @var QueueTicket|null $ticket */
            $ticket = QueueTicket::query()
                ->where('business_id', $business->id)
                ->where('status', QueueTicketStatus::Pending)
                ->lockForUpdate()
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            if ($ticket === null) {
                return null;
            }

            $ticket->status = QueueTicketStatus::Called;
            $ticket->called_at = Carbon::now();
            $ticket->save();

            return $ticket->load([
                'customer',
                'items.offering' => fn ($query) => $query->withTrashed(),
            ]);
        });
    }

    /**
     * Call a specific ticket directly from the waiting list or postponed state.
     *
     * @throws DomainException
     */
    public function callSpecificTicket(Business $business, QueueTicket $ticket): QueueTicket
    {
        return DB::transaction(function () use ($business, $ticket): QueueTicket {
            if ($ticket->business_id !== $business->id) {
                throw new DomainException('Ticket does not belong to this business.');
            }

            /** @var QueueTicket $lockedTicket */
            $lockedTicket = QueueTicket::query()
                ->where('id', $ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTicket->business_id !== $business->id) {
                throw new DomainException('Ticket does not belong to this business.');
            }

            if (! in_array($lockedTicket->status, [QueueTicketStatus::Pending, QueueTicketStatus::Skipped], true)) {
                throw new DomainException('Ticket is not in a callable state.');
            }

            $lockedTicket->status = QueueTicketStatus::Called;
            $lockedTicket->called_at = Carbon::now();
            $lockedTicket->save();

            return $lockedTicket->load([
                'customer',
                'items.offering' => fn ($query) => $query->withTrashed(),
            ]);
        });
    }

    /**
     * Re-alert a currently called customer, updating called_at to the current timestamp.
     *
     * @throws DomainException
     */
    public function recallTicket(QueueTicket $ticket, Business $business): QueueTicket
    {
        if ($ticket->business_id !== $business->id) {
            throw new DomainException('Ticket does not belong to this business.');
        }

        if ($ticket->status !== QueueTicketStatus::Called) {
            throw new DomainException('Ticket is not currently in called state.');
        }

        $ticket->called_at = Carbon::now();
        $ticket->save();

        return $ticket->load([
            'customer',
            'items.offering' => fn ($query) => $query->withTrashed(),
        ]);
    }

    /**
     * Mark a called customer as arrived at the counter and begin service.
     *
     * @throws DomainException
     */
    public function startService(QueueTicket $ticket, Business $business): QueueTicket
    {
        if ($ticket->business_id !== $business->id) {
            throw new DomainException('Ticket does not belong to this business.');
        }

        if ($ticket->status !== QueueTicketStatus::Called) {
            throw new DomainException('Ticket is not currently in called state.');
        }

        $ticket->status = QueueTicketStatus::InService;
        $ticket->served_at = Carbon::now();
        $ticket->save();

        return $ticket->load([
            'customer',
            'items.offering' => fn ($query) => $query->withTrashed(),
        ]);
    }

    /**
     * Conclude the customer service visit.
     *
     * @throws DomainException
     */
    public function completeTicket(QueueTicket $ticket, Business $business): QueueTicket
    {
        if ($ticket->business_id !== $business->id) {
            throw new DomainException('Ticket does not belong to this business.');
        }

        if (! in_array($ticket->status, [QueueTicketStatus::InService, QueueTicketStatus::Called], true)) {
            throw new DomainException('Ticket is not in an active actionable state.');
        }

        $now = Carbon::now();
        $ticket->status = QueueTicketStatus::Completed;
        $ticket->completed_at = $now;

        if ($ticket->served_at === null) {
            $ticket->served_at = $now;
        }

        $ticket->save();

        return $ticket->load([
            'customer',
            'items.offering' => fn ($query) => $query->withTrashed(),
        ]);
    }

    /**
     * Mark a called or in-service customer as absent.
     *
     * @throws DomainException
     */
    public function markNoShow(QueueTicket $ticket, Business $business): QueueTicket
    {
        if ($ticket->business_id !== $business->id) {
            throw new DomainException('Ticket does not belong to this business.');
        }

        if (! in_array($ticket->status, [QueueTicketStatus::Called, QueueTicketStatus::InService], true)) {
            throw new DomainException('Ticket is not in an active callable or in-service state.');
        }

        $ticket->status = QueueTicketStatus::NoShow;
        $ticket->cancelled_at = Carbon::now();
        $ticket->save();

        return $ticket->load([
            'customer',
            'items.offering' => fn ($query) => $query->withTrashed(),
        ]);
    }

    /**
     * Temporarily postpone a customer who is not ready at the counter.
     *
     * @throws DomainException
     */
    public function postponeTicket(QueueTicket $ticket, Business $business): QueueTicket
    {
        if ($ticket->business_id !== $business->id) {
            throw new DomainException('Ticket does not belong to this business.');
        }

        if (! in_array($ticket->status, [QueueTicketStatus::Called, QueueTicketStatus::InService], true)) {
            throw new DomainException('Ticket is not in an active callable or in-service state.');
        }

        $ticket->status = QueueTicketStatus::Skipped;
        $ticket->save();

        return $ticket->load([
            'customer',
            'items.offering' => fn ($query) => $query->withTrashed(),
        ]);
    }

    /**
     * Resume a postponed customer back to the counter (Called) or to the waiting line (Pending).
     *
     * @throws DomainException
     */
    public function resumeTicket(QueueTicket $ticket, Business $business, bool $callImmediately = true): QueueTicket
    {
        if ($ticket->business_id !== $business->id) {
            throw new DomainException('Ticket does not belong to this business.');
        }

        if ($ticket->status !== QueueTicketStatus::Skipped) {
            throw new DomainException('Ticket is not in postponed state.');
        }

        if ($callImmediately) {
            $ticket->status = QueueTicketStatus::Called;
            $ticket->called_at = Carbon::now();
        } else {
            $ticket->status = QueueTicketStatus::Pending;
        }

        $ticket->save();

        return $ticket->load([
            'customer',
            'items.offering' => fn ($query) => $query->withTrashed(),
        ]);
    }

    /**
     * Cancel an active ticket on behalf of the business.
     *
     * @throws DomainException
     */
    public function cancelTicketByBusiness(QueueTicket $ticket, Business $business): QueueTicket
    {
        if ($ticket->business_id !== $business->id) {
            throw new DomainException('Ticket does not belong to this business.');
        }

        if (! $ticket->isActive()) {
            throw new DomainException('Ticket cannot be cancelled because it is already in a terminal state.');
        }

        $ticket->status = QueueTicketStatus::Cancelled;
        $ticket->cancelled_at = Carbon::now();
        $ticket->save();

        return $ticket->load([
            'customer',
            'items.offering' => fn ($query) => $query->withTrashed(),
        ]);
    }

    /**
     * Toggle the queue reception pause status for the business.
     */
    public function toggleQueuePause(Business $business): bool
    {
        $business->is_queue_paused = ! $business->isQueuePaused();
        $business->save();

        return $business->isQueuePaused();
    }

    /**
     * Retrieve all pending tickets waiting in line for the business, with optional search filter.
     * Preserves chronological order (created_at ASC, id ASC) regardless of midnight crossing.
     *
     * @return Collection<int, QueueTicket>
     */
    public function getWaitingTicketsForBusiness(Business $business, ?string $search = null): Collection
    {
        /** @var Collection<int, QueueTicket> */
        return QueueTicket::query()
            ->where('business_id', $business->id)
            ->where('status', QueueTicketStatus::Pending)
            ->when(filled($search), function (Builder $query) use ($search): void {
                $term = '%'.trim((string) $search).'%';
                $query->where(function (Builder $subQuery) use ($term): void {
                    $subQuery->where('ticket_number', 'like', $term)
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($term): void {
                            $customerQuery->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term);
                        });
                });
            })
            ->with([
                'customer',
                'items.offering' => fn ($query) => $query->withTrashed(),
            ])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Retrieve all postponed tickets (SKIPPED) for the business.
     *
     * @return Collection<int, QueueTicket>
     */
    public function getPostponedTicketsForBusiness(Business $business): Collection
    {
        /** @var Collection<int, QueueTicket> */
        return QueueTicket::query()
            ->where('business_id', $business->id)
            ->where('status', QueueTicketStatus::Skipped)
            ->with([
                'customer',
                'items.offering' => fn ($query) => $query->withTrashed(),
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Retrieve the log of tickets finalized today for the business (Completed, NoShow, Cancelled).
     *
     * @return Collection<int, QueueTicket>
     */
    public function getFinishedTicketsToday(Business $business, int $limit = 50): Collection
    {
        $today = Carbon::today();

        /** @var Collection<int, QueueTicket> */
        return QueueTicket::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [
                QueueTicketStatus::Completed,
                QueueTicketStatus::NoShow,
                QueueTicketStatus::Cancelled,
            ])
            ->where(function (Builder $query) use ($today): void {
                $query->where('updated_at', '>=', $today)
                    ->orWhere('created_at', '>=', $today)
                    ->orWhere('completed_at', '>=', $today)
                    ->orWhere('cancelled_at', '>=', $today);
            })
            ->with([
                'customer',
                'items.offering' => fn ($query) => $query->withTrashed(),
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Compute daily summary metrics for the business queue.
     *
     * @return array{
     *     waiting: int,
     *     waiting_count: int,
     *     served_today: int,
     *     completed_count: int,
     *     no_show_today: int,
     *     no_show_count: int,
     *     total_today: int
     * }
     */
    public function getDailyQueueStats(Business $business): array
    {
        $today = Carbon::today();

        $waitingCount = QueueTicket::query()
            ->where('business_id', $business->id)
            ->where('status', QueueTicketStatus::Pending)
            ->count();

        $completedCount = QueueTicket::query()
            ->where('business_id', $business->id)
            ->where('status', QueueTicketStatus::Completed)
            ->where(function (Builder $query) use ($today): void {
                $query->where('completed_at', '>=', $today)
                    ->orWhere(function (Builder $sub) use ($today): void {
                        $sub->whereNull('completed_at')->where('updated_at', '>=', $today);
                    });
            })
            ->count();

        $noShowCount = QueueTicket::query()
            ->where('business_id', $business->id)
            ->where('status', QueueTicketStatus::NoShow)
            ->where(function (Builder $query) use ($today): void {
                $query->where('cancelled_at', '>=', $today)
                    ->orWhere(function (Builder $sub) use ($today): void {
                        $sub->whereNull('cancelled_at')->where('updated_at', '>=', $today);
                    });
            })
            ->count();

        $totalToday = QueueTicket::query()
            ->where('business_id', $business->id)
            ->where('created_at', '>=', $today)
            ->count();

        return [
            'waiting' => $waitingCount,
            'waiting_count' => $waitingCount,
            'served_today' => $completedCount,
            'completed_count' => $completedCount,
            'no_show_today' => $noShowCount,
            'no_show_count' => $noShowCount,
            'total_today' => $totalToday,
        ];
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
