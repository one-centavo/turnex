# 015 · Explore Businesses Directory — Tasks

Actionable, granular, and test-driven task breakdown derived from `spec.md` and `plan.md`. Check `[x]` upon completion.

---

## Phase 1: Domain & Query Infrastructure

- [x] **1.1. Add `QueueTicketStatus::activeStatuses()` Helper**
  - In `app/Enums/QueueTicketStatus.php`, implement `public static function activeStatuses(): array` returning all non-terminal statuses (`Pending`, `Preparing`, `Ready`, `Called`, `InService`, `Skipped`).
  - Add unit test in `tests/Unit/QueueTicketStatusTest.php` asserting that `activeStatuses()` returns the exact list of 6 active statuses and excludes `Completed`, `NoShow`, `Cancelled`.

- [x] **1.2. Implement Business Directory Query Scope / Service (`ExploreBusinessesService`)**
  - Create `app/Services/ExploreBusinessesService.php` (or query scope on `App\Models\Business`):
    - Filter only approved businesses: `where('verification_status', VerificationStatus::Approved)`.
    - Filter only businesses with active offerings: `whereHas('offerings', fn (Builder $q) => $q->active())`.
    - Subquery active queue occupancy: `withCount(['queueTickets as active_tickets_count' => fn ($q) => $q->whereIn('status', QueueTicketStatus::activeStatuses())])`.
    - Sanitize search input via `DataNormalizer::nullableText($search)` and escape wildcard SQL characters (`\`, `%`, `_`).
    - Conditional search filter on `business_name` OR `location` using case-insensitive `LIKE`.
    - Conditional category filter on `business_category` matching `BusinessCategory` enum values.
    - Default ordering by active tickets count descending or business name ascending.
    - Return paginated results (12 per page) with eager-loaded relations as needed.
  - Implement active customer tickets lookup method `getActiveCustomerBusinessIds(?int $customerId): array` returning an indexed array/set of business IDs where the authenticated customer holds a ticket with `status IN (activeStatuses())`.

---

## Phase 2: Routing, Layout & Navigation Integration

- [x] **2.1. Register Public Route in `routes/web.php`**
  - Register `Route::livewire('explore', 'pages::explore.index')->name('explore');` outside auth middleware groups so it is publicly accessible to guests and customers.

- [x] **2.2. Update Customer Layout (`resources/views/layouts/customer.blade.php`) for Guest Support**
  - Detect guest visitors when `auth()->guard('customer')->guest()`.
  - In desktop top navigation:
    - Display "Iniciar sesión" and "Registrarse" action buttons for guests instead of customer initials menu.
    - Highlight "Explorar" link when `request()->routeIs('explore*')`.
  - In mobile bottom navigation:
    - Highlight "Explorar" tab when active.
    - Direct "Mis Turnos" to customer login when accessed by a guest.

- [x] **2.3. Add Direct Navigation Links in Welcome Page (`resources/views/welcome.blade.php`)**
  - Add public "Explorar" link in the top navigation header pointing to `route('explore')`.
  - Ensure links work seamlessly with `wire:navigate`.

---

## Phase 3: Livewire Single-File Component (`pages::explore.index`)

- [x] **3.1. Create Livewire SFC at `resources/views/pages/explore/⚡index.blade.php`**
  - Setup PHP class block with `declare(strict_types=1);` using `WithPagination` trait.
  - Declare reactive public properties:
    - `#[Url] public string $search = '';`
    - `#[Url] public string $category = '';`
  - Lifecycle hooks:
    - `updatedSearch(): void` calling `$this->resetPage()`.
    - `updatedCategory(): void` calling `$this->resetPage()`.
  - Action methods:
    - `setCategory(string $category): void` setting the category and resetting page.
    - `resetFilters(): void` clearing `$search = ''`, `$category = ''`, and calling `$this->resetPage()`.
  - Query resolution in `rendering()` / `render()`:
    - Call `ExploreBusinessesService` to get `$businesses` paginator (12 items).
    - Query `$activeBusinessIds` if `auth()->guard('customer')->check()`.

- [x] **3.2. Build Responsive Search Bar & Category Filter UI**
  - Search input with clear action and debounce: `wire:model.live.debounce.300ms="search"`.
  - Clean input adornment (magnifying glass icon, clear button `x-mark` when `$search` is filled).
  - Horizontally scrollable category filter chips on mobile, wrapping on desktop:
    - "Todos" / All chip for resetting category filter.
    - One chip per `BusinessCategory::cases()` displaying `label()` and active selection styling with semantic tokens (`bg-brand-primary`, `bg-surface-card`, `border-border-subtle`).

- [x] **3.3. Build Responsive Establishment Card Grid**
  - Grid container: `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6`.
  - Each card linked to `route('storefront.show', ['slug' => $business->slug])` with `wire:navigate`.
  - Card components:
    - Cover banner: render `Storage::url($business->cover_photo_path)` with `loading="lazy"` or high-contrast brand fallback gradient/pattern if null.
    - Logo thumbnail: circular avatar with `Storage::url($business->logo_path)` or monogram fallback initials from `business_name`.
    - Commercial name and category badge (`$business->business_category->label()`).
    - Physical location with map-pin icon.
    - Queue occupancy badge:
      - When `active_tickets_count === 0`: display green/subtle "Sin fila".
      - When `active_tickets_count === 1`: display "1 persona en espera".
      - When `active_tickets_count > 1`: display ":count personas en espera".
    - Active turn badge: display prominent "Turno activo" badge if current customer's ID has an active ticket in `$activeBusinessIds`.

- [x] **3.4. Build Empty State & Pagination**
  - Informative empty state card when `$businesses->isEmpty()`:
    - Magnifying glass / search illustration icon.
    - Title: "No se encontraron negocios".
    - Subtitle: "Intenta ajustar tu búsqueda o limpiar los filtros seleccionados."
    - Button: "Limpiar filtros" calling `resetFilters()`.
  - Flux UI pagination links: `<flux:pagination :paginator="$businesses" />` adhering to semantic styling.

---

## Phase 4: Test Suite & Comprehensive Feature Verification (`tests/Feature/ExploreBusinessesTest.php`)

- [x] **4.1. Public & Customer Access Tests**
  - Guest visitor can access `/explore` without authentication (HTTP 200).
  - Authenticated customer can access `/explore` (HTTP 200).
  - Customer layout renders correctly for guest with login links, and for customer with user context.

- [x] **4.2. Business Approval & Offering Exclusion Tests**
  - Approved business (`VerificationStatus::Approved`) with active offerings appears in directory.
  - Unapproved businesses (`PendingVerification`, `UnderReview`, `ActionRequired`, `Rejected`) are excluded.
  - Business with 0 offerings is excluded from directory.
  - Business with only inactive offerings (`is_active = false`) is excluded.
  - Business with only soft-deleted offerings (`deleted_at != null`) is excluded.

- [x] **4.3. Real-Time Search Tests**
  - Search by commercial name matches business case-insensitively.
  - Search by physical address/location matches business.
  - Search matches partial keywords (e.g. "café" matches "Cafetería La Palma").
  - Search input trims leading/trailing whitespace and ignores whitespace-only strings (displays full listing).
  - Search safely handles special SQL characters (`%`, `_`, `'`, `\`) without wildcard explosion or syntax errors.

- [x] **4.4. Category Filter Tests**
  - Selecting category chip filters results to only matching `business_category`.
  - Selecting "Todos" resets category filter and displays all approved businesses.
  - Combining search and category filters applies `AND` logic correctly.

- [x] **4.5. URL Synchronization & Pagination Reset Tests**
  - Search query synchronizes with `?search=...` URL parameter.
  - Category filter synchronizes with `?category=...` URL parameter.
  - Changing search query while on page 2 resets pagination to page 1.
  - Changing category filter while on page 2 resets pagination to page 1.

- [x] **4.6. Queue Occupancy & Active Turn Badge Tests**
  - Venue with 0 active tickets displays "Sin fila".
  - Venue with 1 active ticket displays "1 persona en espera".
  - Venue with 5 active tickets displays "5 personas en espera".
  - Terminal tickets (`Completed`, `NoShow`, `Cancelled`) are NOT counted towards active occupancy.
  - Authenticated customer with active ticket in venue sees "Turno activo" badge on that venue's card.
  - Authenticated customer does NOT see "Turno activo" badge on venues where they have no active tickets.
  - Customer with only completed/cancelled tickets does NOT see "Turno activo" badge.
  - Guest visitor never sees "Turno activo" badge.

- [x] **4.7. Empty State & Fallback Asset Tests**
  - Non-matching search query displays empty state and "Limpiar filtros" action button.
  - Clicking "Limpiar filtros" action resets search and category properties and restores business list.
  - Business card without `cover_photo_path` and `logo_path` renders fallback SVG/initials without broken image tags.
  - Clicking business card points to `/b/{slug}` storefront route.

- [x] **4.8. Multi-Page Pagination Tests**
  - Creating >12 approved businesses with offerings paginates results into multiple pages.
  - Page 2 loads the next set of businesses cleanly.

---

## Phase 5: Code Quality & Pre-Commit Verification

- [x] **5.1. Code Styling & Formatting (Pint)**
  - Run Pint inside Docker: `docker compose exec turnex-app vendor/bin/pint --dirty`.
  - Ensure 0 formatting violations.

- [x] **5.2. Static Analysis (PHPStan / Larastan)**
  - Run Larastan inside Docker: `docker compose exec turnex-app vendor/bin/phpstan analyse`.
  - Resolve any typing issues, strict return types, or Docblock issues.

- [x] **5.3. Automated Pest Test Suite**
  - Run directory feature tests: `docker compose exec turnex-app php artisan test --filter=ExploreBusinessesTest --compact`.
  - Run full test suite: `docker compose exec turnex-app php artisan test --compact`.
  - Ensure all tests pass with 100% green status.

- [x] **5.4. Semantic Design Token & English Codebase Audit**
  - Verify zero arbitrary Tailwind classes (`class-[...]`, `text-[#...]`, `bg-[#...]`).
  - Verify all code, methods, variables, and comments are in English, and user-facing UI copy is strictly in Spanish.
