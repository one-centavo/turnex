<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BusinessCategory;
use App\Enums\QueueTicketStatus;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Offering;
use App\Models\QueueTicket;
use App\Support\DataNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\SQLiteConnection;

class ExploreBusinessesService
{
    /**
     * Retrieve paginated approved businesses with active offerings and queue ticket counts.
     *
     * @return LengthAwarePaginator<int, Business>
     */
    public function getPaginatedBusinesses(
        ?string $search = null,
        ?string $category = null,
        int $perPage = 12
    ): LengthAwarePaginator {
        $query = Business::query()
            ->where('verification_status', VerificationStatus::Approved)
            ->whereHas('offerings', function (Builder $offeringQuery): void {
                /** @var Builder<Offering> $offeringQuery */
                $offeringQuery->active();
            })
            ->withCount([
                'queueTickets as active_tickets_count' => function (Builder $ticketQuery): void {
                    /** @var Builder<QueueTicket> $ticketQuery */
                    $ticketQuery->whereIn('status', QueueTicketStatus::activeStatuses());
                },
            ]);

        $sanitizedSearch = DataNormalizer::nullableText($search);
        if ($sanitizedSearch !== null) {
            $escaped = addcslashes($sanitizedSearch, '%_\\');
            $connection = $query->getConnection();

            if ($connection instanceof SQLiteConnection) {
                $query->where(function (Builder $sub) use ($escaped): void {
                    $sub->whereRaw("business_name LIKE ? ESCAPE '\\'", ["%{$escaped}%"])
                        ->orWhereRaw("location LIKE ? ESCAPE '\\'", ["%{$escaped}%"]);
                });
            } else {
                $query->where(function (Builder $sub) use ($escaped): void {
                    $sub->where('business_name', 'like', "%{$escaped}%")
                        ->orWhere('location', 'like', "%{$escaped}%");
                });
            }
        }

        if ($category !== null && $category !== '' && BusinessCategory::tryFrom($category) !== null) {
            $query->where('business_category', $category);
        }

        /** @var LengthAwarePaginator<int, Business> */
        return $query
            ->orderByDesc('active_tickets_count')
            ->orderBy('business_name', 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Retrieve a flipped array of business IDs where the given customer has an active ticket.
     *
     * @return array<int, int>
     */
    public function getActiveCustomerBusinessIds(?int $customerId): array
    {
        if ($customerId === null) {
            return [];
        }

        /** @var array<int, int> */
        return QueueTicket::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', QueueTicketStatus::activeStatuses())
            ->pluck('business_id')
            ->flip()
            ->all();
    }
}
