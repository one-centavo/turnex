# 009 · Listing and Filtering Business Offerings — Tasks

## Domain & Service Layer

- [x] Add `listForBusiness(Business $business, array $filters, string $sortField, string $sortDirection, int $perPage = 10)` method in `App\Services\OfferingService`.
- [x] Implement query sanitization, case-insensitive name search, category filter, and status filter in `OfferingService`.
- [x] Enforce eager loading with `withTrashed()` on the `category` relation to eliminate N+1 queries and handle soft-deleted categories safely.
- [x] Enforce dynamic column sorting (`name`, `created_at`) with allowed directions (`asc`, `desc`).

## Presentation & Livewire Layer

- [x] Convert `resources/views/pages/business/offerings/index.blade.php` into Livewire 4 Single-File Component (SFC) `resources/views/pages/business/offerings/⚡index.blade.php`.
- [x] Update route in `routes/web.php` from `Route::view('business/offerings', ...)` to `Route::livewire('business/offerings', 'pages::business.offerings.index')` with `auth:business` middleware.
- [x] Implement component reactive state with `#[Url]` attributes: `$search`, `$category`, `$status`, `$sortField`, and `$sortDirection`.
- [x] Add `WithPagination` trait and implement lifecycle hooks (`updatedSearch`, `updatedCategory`, `updatedStatus`) to automatically reset pagination to page 1.
- [x] Implement `sortBy(string $field)` method with toggle direction and pagination reset.
- [x] Implement `resetFilters()` method to clear search and filters back to defaults.
- [x] Build search input with 300 ms debounce delay and icon using `<flux:input>`.
- [x] Build category filter dropdown querying active, non-archived categories using `<flux:select>`.
- [x] Build status filter selector (All, Active, Inactive) using `<flux:select>`.
- [x] Build responsive catalog table using `<flux:table>`:
  - [x] Offering column: compact thumbnail ($40\times 40\text{ px}$, `size-10 rounded-lg object-cover`) or neutral placeholder icon alongside offering name.
  - [x] Category column: category name with graceful fallback if archived.
  - [x] Price column: formatted currency, displaying `"Gratis"` with subtle text styling when price is 0.00.
  - [x] Duration column: formatted minutes (`30 min`) or `"-"` if unspecified.
  - [x] Status column: clean display without badges for active items; explicit `<flux:badge color="zinc">Inactivo</flux:badge>` for inactive items.
  - [x] Sortable table headers displaying active directional indicator icons.
- [x] Implement Livewire pagination navigation links.
- [x] Build empty states:
  - [x] Initial empty state when business has zero offerings with button linking to `business.offerings.create`.
  - [x] Filtered empty state when query yields zero matches with reset filters button.

## Verification & QA

- [x] Create Pest feature test suite in `tests/Feature/OfferingListingTest.php` covering:
  - [x] Unauthenticated guest redirection to `/business/login`.
  - [x] Strict multi-tenant isolation (offerings from other businesses never display).
  - [x] Table rendering with correct columns (thumbnail/placeholder, name, category, price, duration).
  - [x] Price formatting ($0.00 renders as "Gratis" / "Free").
  - [x] Status rendering: clean for active offerings vs "Inactive" badge for inactive ones.
  - [x] Real-time name search filtering.
  - [x] Category dropdown filtering (excluding archived categories from options).
  - [x] Graceful rendering of offerings assigned to soft-deleted categories.
  - [x] Status filtering (All, Active, Inactive).
  - [x] Pagination reset to page 1 upon filter/search changes.
  - [x] Sorting by name and creation date with ascending/descending toggling.
  - [x] Initial empty state vs search-filtered empty state with filter reset action.
  - [x] Query string parameter synchronization.
- [x] Run feature tests via Docker (`docker compose exec turnex-app php artisan test --compact --filter=OfferingListingTest`).
- [x] Run complete test suite regression (`docker compose exec turnex-app php artisan test --compact`).
- [x] Run Pint formatter (`docker compose exec turnex-app vendor/bin/pint --dirty`).
- [x] Run Larastan static analysis (`docker compose exec turnex-app vendor/bin/phpstan analyse`).
- [x] Mark completed tasks in `spec.md` and update `specs/constitution/roadmap.md`.