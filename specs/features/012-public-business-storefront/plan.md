# 012 · Public Business Storefront — Plan

## Approach

Implement a public, responsive commercial storefront allowing customers and venue visitors to discover businesses and browse their active catalog of offerings via human-friendly URLs (`/b/{slug}`). 

The solution enforces domain legitimacy rules by requiring an approved verification status for public access, provides a preview mode for authenticated business owners, introduces persistent URL slugs for the `businesses` table, and encapsulates storefront queries within `StorefrontService`. The interface is built as a Livewire 4 Single-File Component (`pages/storefront/⚡show.blade.php`) styled with Turnex semantic design tokens and Flux UI primitives.

## Implementation Steps

1. **Database Schema & Persistence**:
   - Create migration `add_slug_to_businesses_table`:
     - Add `string('slug', 160)->nullable()->unique()->after('business_name')`.
     - Backfill existing businesses with unique slugs derived from `business_name`.
     - Add database index for fast route lookups.
   - Update `app/Models/Business.php`:
     - Add `slug` to `$fillable`.
     - Add `@property string $slug` to PHPDoc.
     - Add model boot hook to generate a unique slug on creation if not provided.
   - Update `database/factories/BusinessFactory.php` to generate unique slugs by default.

2. **Domain & Service Layer (`app/Services/StorefrontService.php`)**:
   - Implement `generateUniqueSlug(string $name, ?int $excludeId = null): string`:
     - Generates base slug via `Str::slug($name)`.
     - Checks collision in `businesses` table and appends numerical suffixes (`-1`, `-2`, etc.) until unique.
   - Implement `resolveBusiness(string $slug): ?Business`:
     - Finds business by exact `slug` (case-insensitive).
     - Returns null if not found.
   - Implement `canBeViewedBy(Business $business, ?Authenticatable $user = null): bool`:
     - Returns `true` if `$business->verification_status === VerificationStatus::Approved`.
     - Returns `true` if `$user` is the authenticated owner of the business (preview mode).
     - Returns `false` otherwise.
   - Implement `getActiveStorefrontOfferings(Business $business, ?string $search = null, ?int $categoryId = null)`:
     - Scoped strictly to `$business->offerings()`.
     - Enforces `is_active = true` and `deleted_at IS NULL`.
     - Trims whitespace and safely applies optional search filter across offering names.
     - Applies optional category ID filter.
     - Eager loads `category` to prevent N+1 queries.
   - Implement `getActiveCategories(Business $business)`:
     - Retrieves distinct categories associated with the business's active offerings.

3. **Development & Testing Helper Tooling**:
   - Create Artisan command `app/Console/Commands/ApproveBusinessCommand.php` (`php artisan business:approve {id}`):
     - Sets business `verification_status` to `Approved` and updates `reviewed_at`.
     - Facilitates manual and automated testing in development prior to the SuperAdmin portal implementation.

4. **Presentation & Livewire Layer (`resources/views/pages/storefront/⚡show.blade.php`)**:
   - Register public route in `routes/web.php`:
     - `Route::livewire('b/{slug}', 'pages::storefront.show')->name('storefront.show');`
   - Component logic:
     - Mounts with route parameter `$slug`.
     - Resolves business via `StorefrontService::resolveBusiness($slug)`.
     - If business does not exist, triggers 404 (`abort(404)`).
     - Verifies visibility via `StorefrontService::canBeViewedBy($business, Auth::guard('business')->user())`. If false, renders an inactive/under-review state.
     - Reactive properties:
       - `$search = ''`
       - `$selectedCategoryId = null`
   - UI Composition (Responsive & Design Tokens):
     - **Header / Identity**:
       - Cover area displaying `cover_photo_path` or subtle brand texture.
       - Business avatar displaying `logo_path` or initials graphic.
       - Commercial name, category badge, address with location icon, and description.
       - Preview indicator when viewed by the authenticated business owner before approval.
     - **Catalog & Search**:
       - Category filter tabs ("All", followed by active categories).
       - Real-time debounced search bar.
       - Responsive offering cards displaying image/placeholder, name, category, price/free indicator, duration, and description.
     - **Empty States**:
       - Informative message when no offerings match search/filter with reset option.
       - Informative message when business has no active offerings published.

5. **Verification & Testing**:
   - Create Pest test suite `tests/Feature/PublicBusinessStorefrontTest.php` covering:
     - Public access to an approved business via slug without authentication.
     - 404 response for non-existent slugs.
     - Case-insensitive slug resolution.
     - Unique slug collision resolution with numerical suffixes.
     - Inactive notice when visiting an unapproved business as a guest.
     - Preview mode allowing authenticated owners to view their unapproved business.
     - Strict isolation preventing authenticated owners from previewing other unapproved businesses.
     - Business identity rendering (name, category, address, description, visuals).
     - Exclusion of inactive and archived offerings.
     - Multi-tenant offerings isolation.
     - Category filtering and real-time name search.
     - Empty states for search misses and zero offerings.
     - Artisan approval command execution.
