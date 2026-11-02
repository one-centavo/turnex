<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\Offering;
use App\Support\DataNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
                if (isset($data['estimated_duration_minutes']) && $data['estimated_duration_minutes'] !== '') {
                    $estimatedDuration = (int) $data['estimated_duration_minutes'];
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
            $query->whereRaw("name LIKE ? ESCAPE '\\\\'", ["%{$escapedSearch}%"]);
        }

        // 2. Category filtering (accepting either 'category' or 'category_id' key)
        $categoryId = $filters['category_id'] ?? ($filters['category'] ?? null);
        if ($categoryId !== null && $categoryId !== '' && $categoryId !== 'all') {
            $query->where('category_id', (int) $categoryId);
        }

        // 3. Status filtering ('all', 'active', 'inactive')
        $status = isset($filters['status'])
            ? strtolower(trim($filters['status']))
            : null;

        if ($status === 'active') {
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
}
