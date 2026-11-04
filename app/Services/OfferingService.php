<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\Offering;
use App\Support\DataNormalizer;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class OfferingService
{
    /**
     * Allowed column names for dynamic catalog sorting.
     *
     * @var array<int, string>
     */
    public const array ALLOWED_SORT_FIELDS = ['name', 'created_at'];

    /**
     * Allowed sort directions.
     *
     * @var array<int, string>
     */
    public const array ALLOWED_SORT_DIRECTIONS = ['asc', 'desc'];

    /**
     * Create a new commercial offering strictly scoped to the given business tenant.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function create(Business $business, array $data, ?UploadedFile $image = null): Offering
    {
        $uploadedFile = $image ?? ($data['reference_image'] ?? ($data['image'] ?? null));
        $storedImagePath = null;

        if ($uploadedFile instanceof UploadedFile) {
            $storedImagePath = $this->storeImage($uploadedFile);
        }

        try {
            return DB::transaction(function () use ($business, $data, $storedImagePath): Offering {
                $normalizedName = DataNormalizer::text((string) ($data['name'] ?? ''));

                $rawDescription = isset($data['description']) && is_string($data['description'])
                    ? $data['description']
                    : null;
                $normalizedDescription = DataNormalizer::nullableText($rawDescription);

                $price = 0.00;
                if (isset($data['price']) && $data['price'] !== '') {
                    $price = (float) $data['price'];
                }

                $estimatedDuration = null;
                if (isset($data['estimated_duration_minutes'])) {
                    $rawDuration = trim((string) $data['estimated_duration_minutes']);
                    $estimatedDuration = $rawDuration !== '' ? (int) $rawDuration : null;
                }

                $isActive = true;
                if (isset($data['is_active'])) {
                    $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
                }

                $referenceImage = $storedImagePath;
                if ($referenceImage === null && isset($data['reference_image']) && is_string($data['reference_image']) && trim($data['reference_image']) !== '') {
                    $referenceImage = trim($data['reference_image']);
                }

                /** @var Offering $offering */
                $offering = $business->offerings()->create([
                    'category_id' => (int) $data['category_id'],
                    'name' => $normalizedName,
                    'reference_image' => $referenceImage,
                    'description' => $normalizedDescription,
                    'price' => $price,
                    'estimated_duration_minutes' => $estimatedDuration,
                    'is_active' => $isActive,
                ]);

                return $offering;
            });
        } catch (Throwable $e) {
            if ($storedImagePath !== null) {
                $this->deleteImage($storedImagePath);
            }

            throw $e;
        }
    }

    /**
     * Update an existing commercial offering scoped to its business tenant.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function update(
        Offering $offering,
        array $data,
        ?UploadedFile $image = null,
        bool $removeImage = false,
    ): Offering {
        $uploadedFile = $image ?? ($data['reference_image'] ?? ($data['image'] ?? null));
        $newStoredImagePath = null;
        $oldImagePath = $offering->reference_image;
        $shouldDeleteOldImage = false;

        if ($uploadedFile instanceof UploadedFile) {
            $newStoredImagePath = $this->storeImage($uploadedFile);
            $referenceImage = $newStoredImagePath;
            $shouldDeleteOldImage = ($oldImagePath !== null && $oldImagePath !== $newStoredImagePath);
        } elseif ($removeImage) {
            $referenceImage = null;
            $shouldDeleteOldImage = ($oldImagePath !== null);
        } else {
            if (array_key_exists('reference_image', $data) && is_string($data['reference_image'])) {
                $trimmed = trim($data['reference_image']);
                $referenceImage = $trimmed !== '' ? $trimmed : null;
                $shouldDeleteOldImage = ($oldImagePath !== null && $oldImagePath !== $referenceImage);
            } else {
                $referenceImage = $offering->reference_image;
            }
        }

        try {
            DB::transaction(function () use ($offering, $data, $referenceImage): void {
                $attributes = [];

                if (array_key_exists('name', $data)) {
                    $attributes['name'] = DataNormalizer::text((string) $data['name']);
                }

                if (array_key_exists('category_id', $data)) {
                    $attributes['category_id'] = (int) $data['category_id'];
                }

                if (array_key_exists('description', $data)) {
                    $rawDescription = is_string($data['description']) ? $data['description'] : null;
                    $attributes['description'] = DataNormalizer::nullableText($rawDescription);
                }

                if (array_key_exists('price', $data)) {
                    $attributes['price'] = ($data['price'] !== null && $data['price'] !== '')
                        ? (float) $data['price']
                        : 0.00;
                }

                if (array_key_exists('estimated_duration_minutes', $data)) {
                    $rawDuration = $data['estimated_duration_minutes'] !== null ? trim((string) $data['estimated_duration_minutes']) : '';
                    $attributes['estimated_duration_minutes'] = $rawDuration !== '' ? (int) $rawDuration : null;
                }

                if (array_key_exists('is_active', $data)) {
                    $attributes['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
                }

                $attributes['reference_image'] = $referenceImage;

                $offering->update($attributes);
            });
        } catch (Throwable $e) {
            if ($newStoredImagePath !== null) {
                $this->deleteImage($newStoredImagePath);
            }

            throw $e;
        }

        if ($shouldDeleteOldImage) {
            $this->deleteImage($oldImagePath);
        }

        return $offering->refresh();
    }

    /**
     * Store an uploaded reference image on the public storage disk.
     */
    public function storeImage(UploadedFile $file): string
    {
        $storedPath = $file->store('offerings', 'public');

        if ($storedPath === false) {
            throw new RuntimeException('Failed to store offering reference image on public disk.');
        }

        return $storedPath;
    }

    /**
     * Delete an offering reference image from the public storage disk if present.
     */
    public function deleteImage(?string $path): bool
    {
        if ($path === null || trim($path) === '') {
            return false;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false;
    }

    /**
     * Retrieve a paginated list of offerings for the specified business tenant,
     * applying input sanitization, case-insensitive search, category filtering,
     * status filtering, and whitelisted dynamic sorting.
     *
     * @param  array{
     *     search?: string|null,
     *     category?: int|string|null,
     *     category_id?: int|string|null,
     *     status?: string|null,
     * }  $filters
     * @return LengthAwarePaginator<int, Offering>
     */
    public function listForBusiness(
        Business $business,
        array $filters = [],
        string $sortField = 'created_at',
        string $sortDirection = 'desc',
        int $perPage = 10,
    ): LengthAwarePaginator {
        $query = $business->offerings()
            ->with(['category' => fn ($categoryQuery) => $categoryQuery->withTrashed()]);

        // 1. Case-insensitive search on name with sanitized input and escaped wildcards
        $search = isset($filters['search'])
            ? DataNormalizer::nullableText($filters['search'])
            : null;

        if ($search !== null) {
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
            $connection = $query->getConnection();
            if ($connection instanceof SQLiteConnection) {
                $query->whereRaw("name LIKE ? ESCAPE '\\'", ["%{$escapedSearch}%"]);
            } else {
                $query->where('name', 'like', "%{$escapedSearch}%");
            }
        }

        // 2. Category filtering (accepting either 'category' or 'category_id' key)
        $categoryId = $filters['category_id'] ?? ($filters['category'] ?? null);
        if ($categoryId !== null && $categoryId !== '' && $categoryId !== 'all') {
            $query->where('category_id', (int) $categoryId);
        }

        // 3. Status filtering ('all', 'active', 'inactive', 'archived')
        $status = isset($filters['status'])
            ? strtolower(trim((string) $filters['status']))
            : null;

        if ($status === 'archived') {
            $query->onlyTrashed();
        } elseif ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // 4. Dynamic sorting strictly restricted to whitelisted columns and directions
        $sanitizedSortField = in_array(strtolower(trim($sortField)), self::ALLOWED_SORT_FIELDS, true)
            ? strtolower(trim($sortField))
            : 'created_at';

        $sanitizedSortDirection = in_array(strtolower(trim($sortDirection)), self::ALLOWED_SORT_DIRECTIONS, true)
            ? strtolower(trim($sortDirection))
            : 'desc';

        $query->orderBy($sanitizedSortField, $sanitizedSortDirection);
        $query->orderBy('id', $sanitizedSortDirection);

        // 5. Pagination
        $sanitizedPerPage = $perPage > 0 ? $perPage : 10;

        return $query->paginate($sanitizedPerPage);
    }

    /**
     * Archive an offering by deactivating it and applying soft delete within a database transaction.
     * The stored reference image is retained on disk.
     */
    public function archive(Offering $offering): bool
    {
        return DB::transaction(function () use ($offering): bool {
            $offering->is_active = false;
            $offering->save();

            return (bool) $offering->delete();
        });
    }

    /**
     * Restore a soft-deleted offering, returning it to the active/inactive catalog
     * with its existing attributes intact.
     */
    public function restore(Offering $offering): bool
    {
        if (! $offering->trashed()) {
            return false;
        }

        return (bool) $offering->restore();
    }

    /**
     * Determine if an offering has historical queue tickets, orders, or transactions.
     */
    public function hasHistoricalActivity(Offering $offering): bool
    {
        if (isset($offering->has_historical_activity) && (bool) $offering->has_historical_activity) {
            return true;
        }

        $relationMethods = ['queueTickets', 'tickets', 'orderItems', 'orders'];
        foreach ($relationMethods as $method) {
            if (method_exists($offering, $method)) {
                /** @var mixed $relation */
                $relation = $offering->{$method}();
                if (is_object($relation) && method_exists($relation, 'exists') && (bool) $relation->exists()) {
                    return true;
                }
            }
        }

        $historicalTables = [
            'queue_tickets',
            'ticket_offerings',
            'tickets',
            'order_items',
            'orders',
            'transactions',
        ];

        foreach ($historicalTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'offering_id')) {
                if (DB::table($table)->where('offering_id', $offering->id)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Permanently delete an offering, purging its stored image from disk and destroying
     * the database record. Guarded against historical queue or order activity.
     *
     * @throws DomainException
     */
    public function deletePermanently(Offering $offering): bool
    {
        if ($this->hasHistoricalActivity($offering)) {
            throw new DomainException('This service cannot be permanently deleted because it has historical queue or order activity. It must remain archived.');
        }

        $imagePath = $offering->reference_image;

        return DB::transaction(function () use ($offering, $imagePath): bool {
            $deleted = (bool) $offering->forceDelete();

            if ($deleted && $imagePath !== null && trim($imagePath) !== '') {
                $this->deleteImage($imagePath);
            }

            return $deleted;
        });
    }
}
