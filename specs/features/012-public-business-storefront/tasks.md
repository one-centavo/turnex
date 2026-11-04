# 012 · Public Business Storefront — Tasks

## Database & Persistence Layer

- [x] Create migration `add_slug_to_businesses_table` with `$table->string('slug', 160)->nullable()->unique()->after('business_name')`.
- [x] Implement data backfilling in migration for existing businesses to ensure valid slugs.
- [x] Update `App\Models\Business`:
  - [x] Add `slug` to `$fillable`.
  - [x] Add `@property string $slug` to model PHPDoc.
  - [x] Add automatic slug generation boot hook.
- [x] Update `Database\Factories\BusinessFactory` to populate `slug` by default.

## Domain & Service Layer

- [x] Create `App\Services\StorefrontService`:
  - [x] Implement `generateUniqueSlug(string $name, ?int $excludeId = null): string` with collision resolution.
  - [x] Implement `resolveBusiness(string $slug): ?Business` with case-insensitive matching.
  - [x] Implement `canBeViewedBy(Business $business, ?Authenticatable $user = null): bool` with owner preview isolation.
  - [x] Implement `getActiveStorefrontOfferings(Business $business, ?string $search = null, ?int $categoryId = null)` with whitespace sanitization.
  - [x] Implement `getActiveCategories(Business $business)`.
- [x] Create Artisan command `App\Console\Commands\ApproveBusinessCommand` (`php artisan business:approve {id}`).

## Presentation & Livewire Layer

- [x] Register public route in `routes/web.php`: `Route::livewire('b/{slug}', 'pages::storefront.show')->name('storefront.show')`.
- [x] Create Livewire Single-File Component in `resources/views/pages/storefront/⚡show.blade.php`:
  - [x] Implement `mount(string $slug)` resolving the business and enforcing 404 or inactive state checks.
  - [x] Implement preview mode indicator when viewed by authenticated owner before approval.
  - [x] Implement reactive search (`$search`) and category filter (`$selectedCategoryId`).
  - [x] Build responsive header displaying commercial name, category, address, description, cover photo, and logo.
  - [x] Build category filter tabs and debounced search input.
  - [x] Build offering cards displaying reference images, names, categories, prices, duration badges, and descriptions.
  - [x] Build empty states for search misses and businesses without active offerings.
  - [x] Style using Turnex semantic design tokens (`bg-surface-canvas`, `bg-surface-card`, `text-text-primary`, `border-border-subtle`).

## Verification & QA

- [x] Create Pest feature test suite in `tests/Feature/PublicBusinessStorefrontTest.php` covering:
  - [x] Guest access to an approved business storefront without authentication.
  - [x] 404 response for non-existent slugs.
  - [x] Case-insensitive slug matching.
  - [x] Unique slug generation and collision handling.
  - [x] Inactive notice for unapproved businesses viewed by guests.
  - [x] Owner preview access for their own unapproved business.
  - [x] Isolation preventing owners from previewing other unapproved businesses.
  - [x] Business identity and address rendering.
  - [x] Exclusion of inactive and soft-deleted offerings.
  - [x] Multi-tenant isolation for offerings.
  - [x] Category filtering and real-time name search.
  - [x] Empty state when no offerings match search query.
  - [x] Empty state when business has no active offerings.
  - [x] Artisan `business:approve` command execution.
- [x] Run test suite via Docker: `docker compose exec turnex-app php artisan test --filter=PublicBusinessStorefrontTest --compact`.
- [x] Run full test suite regression: `docker compose exec turnex-app php artisan test --compact`.
- [x] Run code formatter via Docker: `docker compose exec turnex-app vendor/bin/pint --dirty`.
- [x] Run static analysis via Docker: `docker compose exec turnex-app vendor/bin/phpstan analyse`.
- [x] Update `spec.md` and `tasks.md` with final completion status.
