<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\Offering;
use App\Support\DataNormalizer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\SQLiteConnection;

class StorefrontService
{
    /**
     * Generate a unique URL slug for a business, resolving collisions with numerical suffixes.
     */
    public function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        return Business::generateUniqueSlug($name, $excludeId);
    }

    /**
     * Resolve a business by its URL slug using case-insensitive matching.
     */
    public function resolveBusiness(string $slug): ?Business
    {
        $sanitizedSlug = strtolower(trim($slug));

        if ($sanitizedSlug === '') {
            return null;
        }

        return Business::query()
            ->whereRaw('LOWER(slug) = ?', [$sanitizedSlug])
            ->first();
    }

    /**
     * Determine whether the given business storefront can be viewed by a visitor or authenticated user.
     *
     * Approved businesses are viewable by anyone. Unapproved businesses can only be viewed in preview
     * mode by their authenticated owner.
     */
    public function canBeViewedBy(Business $business, ?Authenticatable $user = null): bool
    {
        if ($business->verification_status === VerificationStatus::Approved) {
            return true;
        }

        if ($user instanceof Business && $user->id === $business->id) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve active, non-archived offerings for the business with optional search and category filters.
     *
     * @return Collection<int, Offering>
     */
    public function getActiveStorefrontOfferings(
        Business $business,
        ?string $search = null,
        ?int $categoryId = null,
    ): Collection {
        $query = $business->offerings()
            ->with('category')
            ->where('is_active', true);

        $sanitizedSearch = DataNormalizer::nullableText($search);
        if ($sanitizedSearch !== null) {
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $sanitizedSearch);
            $connection = $query->getConnection();

            if ($connection instanceof SQLiteConnection) {
                $query->whereRaw("name LIKE ? ESCAPE '\\'", ["%{$escapedSearch}%"]);
            } else {
                $query->where('name', 'like', "%{$escapedSearch}%");
            }
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Retrieve distinct categories that currently have active offerings in the given business.
     *
     * @return Collection<int, Category>
     */
    public function getActiveCategories(Business $business): Collection
    {
        return Category::query()
            ->whereHas('offerings', function (Builder $query) use ($business): void {
                $query->where('business_id', $business->id)
                    ->where('is_active', true);
            })
            ->orderBy('name')
            ->get();
    }
}
